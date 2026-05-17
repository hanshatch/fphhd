<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Category;
use App\Models\Transaction;
use App\Services\ImportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ImportController extends Controller
{
    public function __construct(private ImportService $importer) {}

    /** Formulario de carga */
    public function create(): View
    {
        $accounts = Account::where('is_active', true)->orderBy('name')->get();

        return view('pages.import.create', compact('accounts'));
    }

    /** Procesar archivo → revisar */
    public function upload(Request $request): RedirectResponse
    {
        $request->validate([
            'account_id' => 'required|exists:accounts,id',
            'file'       => 'required|file|max:5120',
        ], [
            'file.max' => 'El archivo no puede superar 5 MB.',
        ]);

        $ext = strtolower($request->file('file')->getClientOriginalExtension());
        if (! in_array($ext, ['csv', 'txt', 'xlsx', 'xls', 'ods'])) {
            return back()->withErrors(['file' => 'Solo se aceptan archivos CSV, TXT o Excel (XLSX/XLS).']);
        }

        $rows = $this->importer->parse($request->file('file'));

        if ($rows->isEmpty()) {
            return back()->withErrors(['file' => 'No se encontraron movimientos en el archivo.']);
        }

        session([
            'import_rows'       => $rows->toArray(),
            'import_account_id' => $request->account_id,
        ]);

        return redirect()->route('import.review');
    }

    /** Pantalla de revisión y categorización */
    public function review(): View|RedirectResponse
    {
        if (! session('import_rows')) {
            return redirect()->route('import.create')->with('status', 'Sube un archivo primero.');
        }

        $rows       = collect(session('import_rows'));
        $accountId  = session('import_account_id');
        $account    = Account::find($accountId);
        $categories = Category::active()
            ->with('children')
            ->roots()
            ->orderBy('kind')
            ->orderBy('name')
            ->get();

        return view('pages.import.review', compact('rows', 'account', 'categories'));
    }

    /** Confirmar e importar */
    public function confirm(Request $request): RedirectResponse
    {
        $accountId = session('import_account_id');
        $rows      = collect(session('import_rows'));

        if (! $accountId || $rows->isEmpty()) {
            return redirect()->route('import.create');
        }

        $included   = $request->input('include', []);
        $types      = $request->input('type', []);
        $categories = $request->input('category_id', []);
        $descs      = $request->input('description', []);

        $created = 0;

        foreach ($rows as $row) {
            $id = $row['row_id'];

            // Solo importar filas que tengan el checkbox marcado
            if (! in_array((string) $id, $included)) {
                continue;
            }

            Transaction::create([
                'date'        => $row['date'],
                'type'        => $types[$id] ?? $row['type'],
                'amount'      => $row['amount'],
                'account_id'  => $accountId,
                'category_id' => ($categories[$id] ?? '') ?: null,
                'description' => ($descs[$id] ?? '') ?: $row['description'],
            ]);

            $created++;
        }

        session()->forget(['import_rows', 'import_account_id']);

        return redirect()->route('transactions.index')
            ->with('status', "Se importaron {$created} movimientos correctamente.");
    }
}
