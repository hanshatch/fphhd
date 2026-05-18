<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\CreditCard;
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
        $accounts = Account::orderBy('name')->get()
            ->map(fn ($a) => [
                'account' => $a,
                'balance' => $this->service->balance($a),
            ]);

        return view('pages.accounts.index', compact('accounts'));
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
            'institution'     => 'required|in:banamex,mercadopago,nu,revolut,amex,other',
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
            'institution'     => 'required|in:banamex,mercadopago,nu,revolut,amex,other',
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
