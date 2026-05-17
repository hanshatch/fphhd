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

    // Solo sugieren CATEGORÍA — el tipo lo dicta la columna (Depósitos/Retiros)
    private array $categoryPatterns = [
        // Ingresos
        ['regex' => '/ABONO.?NOMINA|NOMINA/i',              'kind' => 'income',  'hint' => 'nomina'],
        ['regex' => '/DEPOSITO INTERBANCARIO|DEP\.? INTER/i','kind' => 'income',  'hint' => 'deposito'],
        ['regex' => '/DEPOSITO CANALES|DEP\.? CANAL/i',      'kind' => 'income',  'hint' => 'deposito'],
        ['regex' => '/TRASPASO.*DE/i',                       'kind' => 'income',  'hint' => 'traspaso'],
        ['regex' => '/PCOMP/i',                              'kind' => 'income',  'hint' => 'rendimiento'],
        // Egresos
        ['regex' => '/SEGUROS MONTERREY|DOMI.*SEGURO/i',     'kind' => 'expense', 'hint' => 'seguro'],
        ['regex' => '/DOMI\s/i',                             'kind' => 'expense', 'hint' => 'domiciliacion'],
        ['regex' => '/OXXO|SORIANA|WALMART|CHEDRAUI|SEVEN/i','kind' => 'expense', 'hint' => 'super'],
        ['regex' => '/DIS\.?EFE|RETIRO.*EFE/i',             'kind' => 'expense', 'hint' => 'efectivo'],
        ['regex' => '/PAGO DE SERVICIO|PAGO INTER/i',        'kind' => 'expense', 'hint' => 'pago'],
        ['regex' => '/ESPERANZA ECHEGARAY/i',                'kind' => 'expense', 'hint' => 'pago'],
    ];

    public function parse(UploadedFile $file): Collection
    {
        $ext = strtolower($file->getClientOriginalExtension());
        $rawRows = $this->loadRows($file->getRealPath(), $ext);
        return $this->normalize($rawRows);
    }

    public function parseFromPath(string $path): Collection
    {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $rawRows = $this->loadRows($path, $ext);
        return $this->normalize($rawRows);
    }

    private function loadRows(string $path, string $ext): array
    {
        return in_array($ext, ['xlsx', 'xls', 'ods'])
            ? $this->parseSpreadsheet($path)
            : $this->parseCsv($path);
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
            while (($row = fgetcsv($fh, 2000, ',', '"', '\\')) !== false) {
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

        // Normalizar encabezado: sin acentos, sin mayúsculas
        $header   = array_map(fn($h) => $this->ascii(mb_strtolower(trim((string) $h))), $rawRows[0]);
        $dataRows = array_slice($rawRows, 1);

        // Detectar columnas por posición o nombre
        $colFecha = $this->findCol($header, ['fecha', 'date']);
        $colDesc  = $this->findCol($header, ['descripcion', 'description', 'concepto', 'detalle']);
        $colDep   = $this->findCol($header, ['depositos', 'deposito', 'abono', 'credito', 'entrada']);
        $colRet   = $this->findCol($header, ['retiros', 'retiro', 'cargo', 'debito', 'salida']);

        // Si no detecta columnas, usar posiciones fijas del formato Banamex
        // Banamex: 0=Fecha, 1=Descripción, 2=Depósitos, 3=Retiros, 4=Saldo
        if ($colDep === $colRet) {
            $colFecha = 0;
            $colDesc  = 1;
            $colDep   = 2;
            $colRet   = 3;
        }

        $rows = collect();

        foreach ($dataRows as $i => $raw) {
            $fecha = trim((string) ($raw[$colFecha] ?? ''));
            $desc  = trim((string) ($raw[$colDesc]  ?? ''));

            if (! $fecha || ! $desc) {
                continue;
            }

            $dep = $this->parseMoney($raw[$colDep] ?? null);
            $ret = $this->parseMoney($raw[$colRet] ?? null);

            // El tipo lo determina EXCLUSIVAMENTE la columna que tiene valor
            $amount = $dep > 0 ? $dep : $ret;
            $type   = $dep > 0 ? 'income' : 'expense';

            $date = $this->parseDate($fecha);

            if (! $date || $amount <= 0) {
                continue;
            }

            // La categoría se sugiere por descripción, SIN tocar el tipo
            $categoryId = $this->suggestCategory($desc, $type);

            $rows->push([
                'row_id'      => $i,
                'date'        => $date,
                'description' => $this->cleanDesc($desc),
                'amount'      => number_format($amount, 2, '.', ''),
                'type'        => $type,
                'category_id' => $categoryId,
            ]);
        }

        return $rows;
    }

    /** Quita acentos para comparación robusta de encabezados */
    private function ascii(string $str): string
    {
        $from = ['á','é','í','ó','ú','ü','ñ','à','è','ì','ò','ù'];
        $to   = ['a','e','i','o','u','u','n','a','e','i','o','u'];
        return str_replace($from, $to, $str);
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
        if ($value === null || $value === '' || $value === 0) {
            return 0.0;
        }
        // Remover símbolo $, espacios y comas de miles
        $clean = preg_replace('/[^0-9.]/', '', str_replace(',', '', (string) $value));
        return $clean !== '' ? (float) $clean : 0.0;
    }

    private function parseDate(string $raw): ?string
    {
        // "04 May 2026" / "04 mayo 2026"
        if (preg_match('/(\d{1,2})\s+([a-záéíóú]{3,})\s+(\d{4})/iu', $raw, $m)) {
            $abbr  = mb_strtolower(mb_substr($m[2], 0, 3));
            $month = $this->monthMap[$abbr] ?? null;
            if ($month) {
                return "{$m[3]}-{$month}-" . str_pad($m[1], 2, '0', STR_PAD_LEFT);
            }
        }
        $ts = strtotime($raw);
        return $ts ? date('Y-m-d', $ts) : null;
    }

    private function cleanDesc(string $desc): string
    {
        return preg_replace('/\s+/', ' ', trim($desc));
    }

    /** Sugiere categoría por descripción. El tipo ya viene determinado por columna. */
    private function suggestCategory(string $desc, string $type): ?int
    {
        $kind = $type === 'income' ? 'income' : 'expense';

        foreach ($this->categoryPatterns as $p) {
            if ($p['kind'] !== $kind) {
                continue;
            }
            if (preg_match($p['regex'], $desc)) {
                return $this->categoryIdFromHint($p['hint'], $kind);
            }
        }

        return null;
    }

    private function categoryIdFromHint(string $hint, string $kind): ?int
    {
        $map = [
            'nomina'        => ['Honorarios', 'Docencia'],
            'deposito'      => ['Honorarios'],
            'rendimiento'   => [],
            'traspaso'      => [],
            'seguro'        => ['Finanzas'],
            'domiciliacion' => ['Hogar'],
            'super'         => ['Alimentación'],
            'efectivo'      => ['Otros gastos'],
            'pago'          => ['Otros gastos'],
        ];

        foreach ($map[$hint] ?? [] as $name) {
            $cat = Category::where('kind', $kind)->where('name', $name)->first();
            if ($cat) {
                return $cat->id;
            }
        }

        return null;
    }
}
