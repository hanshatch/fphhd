<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Category;
use App\Services\StatementImportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
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
            'others'     => Account::where('is_active', true)->where('id', '<>', $account->id)->get()
                ->sortBy(fn (Account $a) => mb_strtolower($a->displayLabel()))->values(),
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
            'rows.*.type'        => 'required|in:expense,income,transfer_out,transfer_in',
            'rows.*.category_id' => 'nullable|exists:categories,id',
            'rows.*.counterparty_account_id' => 'nullable|integer|exists:accounts,id',
            'rows.*.twin_id'     => 'nullable|integer',
            'rows.*.recurring_id' => 'nullable|integer',
            'rows.*.adjust_tx_id' => 'nullable|integer',
        ]);

        // Una transferencia marcada necesita la otra cuenta (y que no sea esta misma)
        foreach ($data['rows'] as $i => $row) {
            if (! empty($row['include'])
                && in_array($row['type'], StatementImportService::TYPES_TRANSFER, true)
                && (empty($row['counterparty_account_id']) || (int) $row['counterparty_account_id'] === $account->id)) {
                throw ValidationException::withMessages([
                    "rows.{$i}.counterparty_account_id" => '«' . $row['description'] . '»: elige la otra cuenta de la transferencia.',
                ]);
            }
        }

        $r = $this->service->store($account, $token, $data['rows']);

        $parts = array_filter([
            $r['created'] === 1 ? 'Se registró 1 movimiento.' : ($r['created'] > 1 ? "Se registraron {$r['created']} movimientos." : null),
            $r['applied'] === 1 ? '1 cargo recurrente aplicado con el monto real.' : ($r['applied'] > 1 ? "{$r['applied']} cargos recurrentes aplicados con el monto real." : null),
            $r['adjusted'] === 1 ? '1 cargo recurrente ajustado al monto real.' : ($r['adjusted'] > 1 ? "{$r['adjusted']} cargos recurrentes ajustados al monto real." : null),
            $r['linked'] === 1 ? '1 movimiento que ya existía en otra cuenta quedó como transferencia.' : ($r['linked'] > 1 ? "{$r['linked']} movimientos que ya existían en otra cuenta quedaron como transferencias." : null),
        ]);

        $msg = $parts === [] ? 'No se registró ningún movimiento.' : implode(' ', $parts);

        return redirect()->route('accounts.show', $account)->with('status', $msg);
    }
}
