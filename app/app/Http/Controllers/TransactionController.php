<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Category;
use App\Models\Source;
use App\Models\Transaction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TransactionController extends Controller
{
    public function index(Request $request): View
    {
        $query = Transaction::with(['account', 'category', 'source', 'counterpartyAccount'])
            ->orderBy('date', 'desc')
            ->orderBy('id', 'desc');

        $hasFilter = $request->hasAny(['account_id', 'type', 'from', 'to', 'search']);

        if ($request->filled('account_id')) {
            $query->where('account_id', $request->account_id);
        }
        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }
        if ($request->filled('search')) {
            $query->where('description', 'like', '%' . $request->search . '%');
        }

        // Con filtro de fecha explícito usa ese rango; sin filtro muestra últimos 90 días
        if ($request->filled('from') || $request->filled('to')) {
            if ($request->filled('from')) $query->whereDate('date', '>=', $request->from);
            if ($request->filled('to'))   $query->whereDate('date', '<=', $request->to);
        } elseif (! $hasFilter) {
            $query->whereDate('date', '>=', now()->subDays(90)->toDateString());
        }

        $grouped  = $query->get()->groupBy(fn ($tx) => $tx->date->format('Y-m'));
        $accounts = Account::where('is_active', true)->orderBy('name')->get();

        return view('pages.transactions.index', compact('grouped', 'accounts', 'hasFilter'));
    }

    public function create(Request $request): View
    {
        $accounts   = Account::where('is_active', true)->orderBy('name')->get();
        $categories = Category::active()->with('children')->orderBy('kind')->orderBy('name')->get();
        $sources    = Source::active()->orderBy('name')->get();

        return view('pages.transactions.form', [
            'transaction' => new Transaction(['date' => now()->format('Y-m-d'), 'type' => $request->type ?? 'expense']),
            'accounts'    => $accounts,
            'categories'  => $categories,
            'sources'     => $sources,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->normalizeMoney($request, ['amount']);

        $data = $request->validate([
            'date'                    => 'required|date',
            'type'                    => 'required|in:income,expense,transfer,interest,fee',
            'amount'                  => 'required|numeric|min:0.01',
            'account_id'              => 'required|exists:accounts,id',
            'category_id'             => 'nullable|exists:categories,id',
            'source_id'               => 'nullable|exists:sources,id',
            'counterparty_account_id' => 'required_if:type,transfer|nullable|exists:accounts,id|different:account_id',
            'description'             => 'nullable|string|max:500',
        ]);

        Transaction::create($data);

        if ($request->has('save_and_new')) {
            return redirect()->route('transactions.create')->with('status', 'Movimiento guardado.');
        }

        return redirect()->route('transactions.index')->with('status', 'Movimiento guardado.');
    }

    public function edit(Transaction $transaction): View
    {
        $accounts   = Account::where('is_active', true)->orderBy('name')->get();
        $categories = Category::active()->with('children')->orderBy('kind')->orderBy('name')->get();
        $sources    = Source::active()->orderBy('name')->get();

        return view('pages.transactions.form', compact('transaction', 'accounts', 'categories', 'sources'));
    }

    public function update(Request $request, Transaction $transaction): RedirectResponse
    {
        $this->normalizeMoney($request, ['amount']);

        $data = $request->validate([
            'date'                    => 'required|date',
            'type'                    => 'required|in:income,expense,transfer,interest,fee',
            'amount'                  => 'required|numeric|min:0.01',
            'account_id'              => 'required|exists:accounts,id',
            'category_id'             => 'nullable|exists:categories,id',
            'source_id'               => 'nullable|exists:sources,id',
            'counterparty_account_id' => 'required_if:type,transfer|nullable|exists:accounts,id|different:account_id',
            'description'             => 'nullable|string|max:500',
        ]);

        $transaction->update($data);

        return redirect()->route('transactions.index')->with('status', 'Movimiento actualizado.');
    }

    public function duplicate(Transaction $transaction): RedirectResponse
    {
        // Copia el movimiento con la fecha de hoy y redirige al form de edición
        $copy = $transaction->replicate(['created_at', 'updated_at']);
        $copy->date = now()->toDateString();
        $copy->save();

        return redirect()->route('transactions.edit', $copy)
            ->with('status', 'Movimiento duplicado. Ajusta los datos y guarda.');
    }

    public function destroy(Transaction $transaction): RedirectResponse
    {
        $transaction->delete();

        return redirect()->route('transactions.index')->with('status', 'Movimiento eliminado.');
    }
}
