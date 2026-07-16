<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\CreditCard;
use App\Models\Transaction;
use App\Services\AccountService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class AccountController extends Controller
{
    public function __construct(private AccountService $service) {}

    public function index(): View
    {
        // Orden de grupos como MoneyWiz: activos primero, deuda al final
        $typeOrder = ['debit' => 0, 'savings' => 1, 'investment' => 2, 'cash' => 3, 'credit' => 4];

        $all = Account::orderBy('name')->get()
            ->map(fn ($a) => [
                'account' => $a,
                'balance' => $this->service->balance($a),
            ]);

        // Agrupar por tipo respetando el orden definido
        $groups = $all
            ->groupBy(fn ($item) => $item['account']->type)
            ->sortBy(fn ($items, $type) => $typeOrder[$type] ?? 99);

        // Totales por grupo y patrimonio neto global
        $groupTotals = $groups->map(fn ($items) =>
            $items->sum(fn ($i) => (float) $i['balance'])
        );

        $netWorth = $this->service->netWorth();

        return view('pages.accounts.index', compact('groups', 'groupTotals', 'netWorth'));
    }

    public function show(Account $account): View
    {
        $balance = $this->service->balance($account);

        // Cargamos ASC para calcular saldo corrido, luego invertimos para mostrar.
        // Incluye transferencias entrantes (counterparty), que también mueven el saldo.
        $transactions = Transaction::with(['account', 'category', 'source', 'counterpartyAccount'])
            ->where(fn ($q) => $q
                ->where('account_id', $account->id)
                ->orWhere(fn ($q2) => $q2
                    ->where('counterparty_account_id', $account->id)
                    ->where('type', 'transfer')))
            ->orderBy('date', 'asc')
            ->orderBy('id', 'asc')
            ->get();

        // Saldo corrido con bcmath (nunca float), espejo exacto de AccountService::balance()
        $running = (string) $account->initial_balance;
        $runningBalances = [];

        foreach ($transactions as $tx) {
            $amount     = (string) $tx->amount;
            $isIncoming = $tx->type === 'transfer' && $tx->counterparty_account_id === $account->id;

            if ($account->isCredit()) {
                // Deuda: sube con gastos/comisiones, baja con pagos y reembolsos
                $running = match (true) {
                    in_array($tx->type, ['expense', 'fee'])      => bcadd($running, $amount, 2),
                    $isIncoming                                  => bcsub($running, $amount, 2),
                    in_array($tx->type, ['income', 'interest'])  => bcsub($running, $amount, 2),
                    default                                      => $running,
                };
            } else {
                $running = match (true) {
                    in_array($tx->type, ['income', 'interest'])  => bcadd($running, $amount, 2),
                    $isIncoming                                  => bcadd($running, $amount, 2),
                    default                                      => bcsub($running, $amount, 2),
                };
            }

            $runningBalances[$tx->id] = $running;
        }

        // Invertir y agrupar por mes para mostrar (más reciente primero)
        $grouped = $transactions->reverse()->values()
            ->groupBy(fn ($tx) => $tx->date->format('Y-m'));

        return view('pages.accounts.show', compact(
            'account', 'balance', 'grouped', 'runningBalances'
        ));
    }

    public function adjustShow(Account $account): View
    {
        $balance = $this->service->balance($account);
        return view('pages.accounts.adjust', compact('account', 'balance'));
    }

    public function adjustStore(Request $request, Account $account): RedirectResponse
    {
        $request->validate([
            'target_balance' => 'required|numeric',
            'date'           => 'required|date',
            'description'    => 'nullable|string|max:255',
        ]);

        $current  = $this->service->balance($account);
        $target   = number_format((float) $request->target_balance, 2, '.', '');
        $diff     = bcsub($target, $current, 2);

        if ($diff == '0.00') {
            return back()->with('status', 'El saldo ya es correcto, no se creó ningún movimiento.');
        }

        // En TDC el saldo es deuda: subirla = expense, bajarla = income (reembolso/ajuste).
        // En cuentas normales es al revés.
        $increase = bccomp($diff, '0', 2) > 0;
        $type     = $account->isCredit()
            ? ($increase ? 'expense' : 'income')
            : ($increase ? 'income' : 'expense');

        Transaction::create([
            'date'        => $request->date,
            'type'        => $type,
            'amount'      => ltrim($diff, '-'),
            'account_id'  => $account->id,
            'description' => $request->description ?: 'Ajuste de saldo',
        ]);

        return redirect()->route('accounts.show', $account)
            ->with('status', 'Saldo ajustado correctamente.');
    }

    public function create(): View
    {
        return view('pages.accounts.form', [
            'account'    => new Account(),
            'creditCard' => new CreditCard(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name'            => 'required|string|max:100',
            'type'            => 'required|in:debit,credit,savings,investment,cash',
            'institution'     => 'required|in:banamex,mercadopago,nu,revolut,amex,efectivo,other',
            'initial_balance' => 'required|numeric|min:0',
            'color'           => 'required|regex:/^#[0-9A-Fa-f]{6}$/',
            'invest_apr'      => 'nullable|numeric|min:0|max:100',
            'notes'           => 'nullable|string|max:500',
            'statement_day'   => 'nullable|integer|min:1|max:31',
            'payment_day'     => 'nullable|integer|min:1|max:31',
            'credit_limit'    => 'nullable|numeric|min:0',
            'logo'            => 'nullable|image|max:2048',
        ]);

        $data['initial_balance'] = number_format((float) $data['initial_balance'], 2, '.', '');

        if ($request->hasFile('logo')) {
            $data['logo_path'] = $request->file('logo')->store('account-logos', 'public');
        }

        unset($data['logo']);
        $account = Account::create($data);

        if ($data['type'] === 'credit') {
            $account->creditCard()->create([
                'statement_day'   => $request->input('statement_day') ?? 1,
                'payment_day'     => $request->input('payment_day')   ?? 20,
                'credit_limit'    => number_format((float) ($request->input('credit_limit') ?? 0), 2, '.', ''),
                'apr'             => null,
                'min_payment_pct' => 1.5,
            ]);
        }

        return redirect()->route('accounts.index')->with('status', 'Cuenta creada correctamente.');
    }

    public function edit(Account $account): View
    {
        return view('pages.accounts.form', [
            'account'    => $account,
            'creditCard' => $account->creditCard ?? new CreditCard(),
        ]);
    }

    public function update(Request $request, Account $account): RedirectResponse
    {
        $data = $request->validate([
            'name'            => 'required|string|max:100',
            'type'            => 'required|in:debit,credit,savings,investment,cash',
            'institution'     => 'required|in:banamex,mercadopago,nu,revolut,amex,efectivo,other',
            'initial_balance' => 'required|numeric|min:0',
            'color'           => 'required|regex:/^#[0-9A-Fa-f]{6}$/',
            'is_active'       => 'boolean',
            'invest_apr'      => 'nullable|numeric|min:0|max:100',
            'notes'           => 'nullable|string|max:500',
            'statement_day'   => 'nullable|integer|min:1|max:31',
            'payment_day'     => 'nullable|integer|min:1|max:31',
            'credit_limit'    => 'nullable|numeric|min:0',
            'logo'            => 'nullable|image|max:2048',
            'remove_logo'     => 'nullable|boolean',
        ]);

        $data['initial_balance'] = number_format((float) $data['initial_balance'], 2, '.', '');
        $data['is_active']       = $request->boolean('is_active', true);

        // Eliminar logo si se pidió
        if ($request->boolean('remove_logo') && $account->logo_path) {
            Storage::disk('public')->delete($account->logo_path);
            $data['logo_path'] = null;
        }

        // Subir nuevo logo
        if ($request->hasFile('logo')) {
            if ($account->logo_path) {
                Storage::disk('public')->delete($account->logo_path);
            }
            $data['logo_path'] = $request->file('logo')->store('account-logos', 'public');
        }

        unset($data['logo'], $data['remove_logo']);
        $account->update($data);

        if ($data['type'] === 'credit') {
            // ?? 1/20 como fallback porque ConvertEmptyStringsToNull convierte '' a null
            $account->creditCard()->updateOrCreate(
                ['account_id' => $account->id],
                [
                    'statement_day' => $request->input('statement_day') ?? 1,
                    'payment_day'   => $request->input('payment_day')   ?? 20,
                    'credit_limit'  => number_format((float) ($request->input('credit_limit') ?? 0), 2, '.', ''),
                ]
            );
        }

        return redirect()->route('accounts.index')->with('status', 'Cuenta actualizada.');
    }

    public function destroy(Account $account): RedirectResponse
    {
        if ($account->transactions()->exists()) {
            $account->update(['is_active' => false]);

            return redirect()->route('accounts.index')->with('status', 'Cuenta desactivada (tiene movimientos asociados).');
        }

        $account->delete();

        return redirect()->route('accounts.index')->with('status', 'Cuenta eliminada.');
    }
}
