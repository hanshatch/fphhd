<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Services\AccountService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AccountController extends Controller
{
    public function __construct(private AccountService $service) {}

    public function index(): View
    {
        $accounts = Account::orderBy('type')->orderBy('name')->get()
            ->map(fn ($a) => [
                'account' => $a,
                'balance' => $this->service->balance($a),
            ]);

        return view('pages.accounts.index', compact('accounts'));
    }

    public function create(): View
    {
        return view('pages.accounts.form', ['account' => new Account()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name'            => 'required|string|max:100',
            'type'            => 'required|in:debit,credit,savings,investment,cash',
            'institution'     => 'required|in:banamex,mercadopago,nu,revolut,amex,other',
            'initial_balance' => 'required|numeric|min:0',
            'color'           => 'required|regex:/^#[0-9A-Fa-f]{6}$/',
            'invest_apr'      => 'nullable|numeric|min:0|max:100',
            'notes'           => 'nullable|string|max:500',
        ]);

        $data['initial_balance'] = number_format((float) $data['initial_balance'], 2, '.', '');

        Account::create($data);

        return redirect()->route('accounts.index')->with('status', 'Cuenta creada correctamente.');
    }

    public function edit(Account $account): View
    {
        return view('pages.accounts.form', compact('account'));
    }

    public function update(Request $request, Account $account): RedirectResponse
    {
        $data = $request->validate([
            'name'            => 'required|string|max:100',
            'type'            => 'required|in:debit,credit,savings,investment,cash',
            'institution'     => 'required|in:banamex,mercadopago,nu,revolut,amex,other',
            'initial_balance' => 'required|numeric|min:0',
            'color'           => 'required|regex:/^#[0-9A-Fa-f]{6}$/',
            'is_active'       => 'boolean',
            'invest_apr'      => 'nullable|numeric|min:0|max:100',
            'notes'           => 'nullable|string|max:500',
        ]);

        $data['initial_balance'] = number_format((float) $data['initial_balance'], 2, '.', '');
        $data['is_active']       = $request->boolean('is_active', true);

        $account->update($data);

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
