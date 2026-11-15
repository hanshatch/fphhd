<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Transaction;
use App\Services\TransferReconcileService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TransferReconcileController extends Controller
{
    public function __construct(private TransferReconcileService $service) {}

    public function index(): View
    {
        return view('pages.transfers.pending', [
            'items'    => $this->service->pendingWithCandidates(),
            'accounts' => Account::where('is_active', true)->get()
                ->sortBy(fn (Account $a) => mb_strtolower($a->displayLabel()))->values(),
        ]);
    }

    /** Vuelve a buscar pares sin ambigüedad */
    public function auto(): RedirectResponse
    {
        $n = $this->service->autoMatch();

        return back()->with('status', match (true) {
            $n === 0 => 'No encontré pares claros. Carga la otra cuenta o resuélvelas aquí.',
            $n === 1 => '1 transferencia conciliada.',
            default  => "{$n} transferencias conciliadas.",
        });
    }

    /** Resuelve un pendiente: con un candidato, con una cuenta, o como gasto/ingreso */
    public function resolve(Request $request, Transaction $transaction): RedirectResponse
    {
        abort_unless($transaction->isPendingTransfer(), 404);

        $data = $request->validate([
            'match_id'   => 'nullable|integer|exists:transactions,id',
            'account_id' => 'nullable|integer|exists:accounts,id',
            'as_movement' => 'nullable|boolean',
        ]);

        if (! empty($data['match_id'])) {
            $ok = $this->service->matchWith($transaction, Transaction::findOrFail($data['match_id']));

            return back()->with('status', $ok ? 'Transferencia conciliada.' : 'Ese movimiento no corresponde a esta transferencia.');
        }

        if (! empty($data['account_id']) && (int) $data['account_id'] !== $transaction->account_id) {
            $this->service->assign($transaction, Account::findOrFail($data['account_id']));

            return back()->with('status', 'Transferencia conciliada.');
        }

        if (! empty($data['as_movement'])) {
            $out = $transaction->transfer_pending === Transaction::PENDING_OUT;
            $this->service->toMovement($transaction);

            return back()->with('status', $out ? 'Quedó como gasto.' : 'Quedó como ingreso.');
        }

        return back()->with('status', 'Elige la otra cuenta de la transferencia.');
    }
}
