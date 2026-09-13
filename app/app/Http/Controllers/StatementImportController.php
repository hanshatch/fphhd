<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Category;
use App\Services\StatementImportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StatementImportController extends Controller
{
    public function __construct(private StatementImportService $service) {}

    /** Recibe las capturas, las analiza y manda a la pantalla de revisión */
    public function upload(Request $request, Account $account): RedirectResponse
    {
        $request->validate([
            'images'   => 'required|array|min:1|max:4',
            'images.*' => 'image|max:10240',
        ]);

        if (! $this->service->isConfigured()) {
            return redirect()->route('accounts.show', $account)
                ->with('status', 'El análisis de imágenes no está configurado (falta la API key de visión).');
        }

        $token = $this->service->analyze($account, $request->file('images'));

        if ($token === null) {
            return redirect()->route('accounts.show', $account)
                ->with('status', 'No pude analizar la imagen. Inténtalo de nuevo en un momento.');
        }

        return redirect()->route('accounts.import.review', [$account, $token]);
    }

    /** Tabla de revisión: Hans marca qué entra y asigna categoría */
    public function review(Account $account, string $token): View|RedirectResponse
    {
        $rows = $this->service->draft($account, $token);

        if ($rows === null) {
            return redirect()->route('accounts.show', $account)
                ->with('status', 'La importación expiró. Sube la captura de nuevo.');
        }

        return view('pages.accounts.import', [
            'account'    => $account,
            'token'      => $token,
            'rows'       => $rows,
            'categories' => Category::active()->with('children')->orderBy('kind')->orderBy('name')->get(),
        ]);
    }

    /** Crea las transacciones seleccionadas */
    public function store(Request $request, Account $account, string $token): RedirectResponse
    {
        if ($this->service->draft($account, $token) === null) {
            return redirect()->route('accounts.show', $account)
                ->with('status', 'La importación expiró. Sube la captura de nuevo.');
        }

        $data = $request->validate([
            'rows'               => 'required|array',
            'rows.*.include'     => 'nullable|boolean',
            'rows.*.date'        => 'required|date',
            'rows.*.description' => 'required|string|max:500',
            'rows.*.amount'      => 'required|string',
            'rows.*.type'        => 'required|in:expense,income',
            'rows.*.category_id' => 'nullable|exists:categories,id',
        ]);

        $created = $this->service->store($account, $token, $data['rows']);

        $msg = match (true) {
            $created === 0 => 'No se registró ningún movimiento.',
            $created === 1 => 'Se registró 1 movimiento.',
            default        => "Se registraron {$created} movimientos.",
        };

        return redirect()->route('accounts.show', $account)->with('status', $msg);
    }
}
