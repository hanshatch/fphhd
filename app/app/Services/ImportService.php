<?php

namespace App\Services;

use App\Models\Category;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\IOFactory;

class ImportService
{
    private array $monthMap = [
        'ene' => '01', 'feb' => '02', 'mar' => '03', 'abr' => '04',
        'may' => '05', 'jun' => '06', 'jul' => '07', 'ago' => '08',
        'sep' => '09', 'oct' => '10', 'nov' => '11', 'dic' => '12',
    ];

    // Patrones de descripción → tipo de transacción + sugerencia de categoría
    private array $patterns = [
        ['regex' => '/ABONO.?NOMINA|NOMINA/i',             'type' => 'income',  'hint' => 'nomina'],
        ['regex' => '/DEPOSITO INTERBANCARIO|DEPOSITO CAN/i','type' => 'income',  'hint' => 'deposito'],
        ['regex' => '/TRASPASO.*DE/i',                      'type' => 'income',  'hint' => 'traspaso'],
        ['regex' => '/PCOMP/i',                             'type' => 'income',  'hint' => 'rendimiento'],
        ['regex' => '/SEGUROS MONTERREY|DOMI.*SEGURO/i',    'type' => 'expense', 'hint' => 'seguro'],
        ['regex' => '/DOMI\s/i',                            'type' => 'expense', 'hint' => 'domiciliacion'],
        ['regex' => '/OXXO|SORIANA|WALMART|CHEDRAUI/i',    'type' => 'expense', 'hint' => 'super'],
        ['regex' => '/DIS\.?EFE|EFECTIVO/i',               'type' => 'expense', 'hint' => 'efectivo'],
        ['regex' => '/PAGO DE SERVICIO|PAGO INTERBANCARIO/i','type' => 'expense','hint' => 'pago'],
        ['regex' => '/TRASPASO.*A/i',                       'type' => 'expense', 'hint' => 'traspaso_salida'],
    ];

    /**
     * Parsea un archivo CSV o XLSX y retorna filas normalizadas.
     */
    public function parse(UploadedFile $file): Collection
    {
        $ext = strtolower($file->getClientOriginalExtension());

        $rawRows = match (true) {
            in_array($ext, ['xlsx', 'xls', 'ods']) => $this->parseSpreadsheet($file->getRealPath()),
            default                                  => $this->parseCsv($file->getRealPath()),
        };

        return $this->normalize($rawRows);
    }

    /**
     * Parsea directamente desde una ruta (para el archivo de ejemplo).
     */
    public function parseFromPath(string $path): Collection
    {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        $rawRows = match (true) {
            in_array($ext, ['xlsx', 'xls', 'ods']) => $this->parseSpreadsheet($path),
            default                                  => $this->parseCsv($path),
        };

        return $this->normalize($rawRows);
    }

    private function parseSpreadsheet(string $path): array
    {
        $spreadsheet = IOFactory::load($path);
        return $spreadsheet->getActiveSheet()->toArray(null, true, true, false);
    }

    private function parseCsv(string $path): array
    {
        $rows = [];
        if (($fh = fopen($path, 'r')) !== false) {
            while (($row = fgetcsv($fh, 1000, ',', '"')) !== false) {
                $rows[] = $row;
            }
            fclose($fh);
        }
        return $rows;
    }

    private function normalize(array $rawRows): Collection
    {
        if (empty($rawRows)) {
            return collect();
        }

        // Detectar fila de encabezado
        $header = array_map('strtolower', array_map('trim', $rawRows[0]));
        $dataRows = array_slice($rawRows, 1);

        // Mapear columnas por nombre
        $colFecha = $this->findCol($header, ['fecha', 'date']);
        $colDesc  = $this->findCol($header, ['descripción', 'descripcion', 'description', 'concepto']);
        $colDep   = $this->findCol($header, ['depósitos', 'depositos', 'deposito', 'abono', 'cargo_abono']);
        $colRet   = $this->findCol($header, ['retiros', 'retiro', 'cargo', 'débitos']);

        $rows = collect();

        foreach ($dataRows as $i => $raw) {
            $fecha = trim($raw[$colFecha] ?? '');
            $desc  = trim($raw[$colDesc]  ?? '');
            $dep   = $this->parseMoney($raw[$colDep] ?? '');
            $ret   = $this->parseMoney($raw[$colRet] ?? '');

            if (! $fecha || ! $desc) {
                continue;
            }

            $date   = $this->parseDate($fecha);
            $amount = $dep > 0 ? $dep : $ret;
            $type   = $dep > 0 ? 'income' : 'expense';

            if (! $date || $amount <= 0) {
                continue;
            }

            ['type' => $suggestedType, 'category_id' => $suggestedCat] = $this->suggest($desc, $type);

            $rows->push([
                'row_id'       => $i,
                'date'         => $date,
                'description'  => $this->cleanDesc($desc),
                'amount'       => number_format($amount, 2, '.', ''),
                'type'         => $suggestedType,
                'category_id'  => $suggestedCat,
                'skip'         => false,
            ]);
        }

        return $rows;
    }

    private function findCol(array $header, array $names): int
    {
        foreach ($names as $name) {
            foreach ($header as $i => $h) {
                if (str_contains($h, $name)) {
                    return $i;
                }
            }
        }
        return 0;
    }

    private function parseMoney(mixed $value): float
    {
        if ($value === null || $value === '') {
            return 0.0;
        }
        $clean = preg_replace('/[^0-9.]/', '', str_replace(',', '', (string) $value));
        return $clean !== '' ? (float) $clean : 0.0;
    }

    private function parseDate(string $raw): ?string
    {
        // "04 May 2026" o "04 mayo 2026"
        if (preg_match('/(\d{1,2})\s+([a-záéíóú]{3,})\s+(\d{4})/iu', $raw, $m)) {
            $month = $this->monthMap[strtolower(substr($m[2], 0, 3))] ?? null;
            if ($month) {
                return "{$m[3]}-{$month}-" . str_pad($m[1], 2, '0', STR_PAD_LEFT);
            }
        }
        // Intentar parseo genérico
        $ts = strtotime($raw);
        return $ts ? date('Y-m-d', $ts) : null;
    }

    private function cleanDesc(string $desc): string
    {
        // Quitar espacios múltiples internos
        return preg_replace('/\s+/', ' ', $desc);
    }

    private function suggest(string $desc, string $baseType): array
    {
        $type       = $baseType;
        $categoryId = null;

        foreach ($this->patterns as $p) {
            if (preg_match($p['regex'], $desc)) {
                $type = $p['type'];
                $categoryId = $this->categoryIdFromHint($p['hint'], $type);
                break;
            }
        }

        return ['type' => $type, 'category_id' => $categoryId];
    }

    private function categoryIdFromHint(string $hint, string $type): ?int
    {
        $kind = $type === 'income' ? 'income' : 'expense';

        $map = [
            'nomina'        => ['Honorarios', 'Docencia'],
            'deposito'      => ['Honorarios'],
            'rendimiento'   => [],
            'seguro'        => ['Finanzas'],
            'domiciliacion' => ['Hogar'],
            'super'         => ['Alimentación'],
            'efectivo'      => ['Otros gastos'],
            'pago'          => ['Otros gastos'],
            'traspaso_salida'=> [],
        ];

        $names = $map[$hint] ?? [];

        foreach ($names as $name) {
            $cat = Category::where('kind', $kind)->where('name', $name)->first();
            if ($cat) {
                return $cat->id;
            }
        }

        return null;
    }
}
