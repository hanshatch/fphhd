<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Category;
use App\Models\Source;
use App\Models\Transaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TransactionController extends Controller
{
    public function index(Request $request): View
    {
        $query = Transaction::with(['account', 'category', 'source', 'counterpartyAccount'])
            ->orderBy('date', 'desc')
            ->orderBy('position', 'asc')
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
        $accounts = Account::where('is_active', true)->get()
            ->sortBy(fn (Account $a) => mb_strtolower($a->institutionLabel() . '·' . $a->name))
            ->values();
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

        $back = $this->safeRedirectTo($request->input('redirect_to'));

        if ($request->has('save_and_new')) {
            // Desde una cuenta: vuelve ahí con el modal listo para el siguiente
            $target = $back
                ? $back . (str_contains($back, '?') ? '&' : '?') . 'new=1'
                : route('transactions.create');

            return redirect($target)->with('status', 'Movimiento guardado.');
        }

        return redirect($back ?: route('transactions.index'))->with('status', 'Movimiento guardado.');
    }

    public function edit(Transaction $transaction): View
    {
        $accounts = Account::where('is_active', true)->get()
            ->sortBy(fn (Account $a) => mb_strtolower($a->institutionLabel() . '·' . $a->name))
            ->values();
        $categories = Category::active()->with('children')->orderBy('kind')->orderBy('name')->get();
        $sources    = Source::active()->orderBy('name')->get();

        return view('pages.transactions.form', compact('transaction', 'accounts', 'categories', 'sources'));
    }

    /**
     * Formulario de edición sin layout, para inyectarlo en el modal que se
     * abre desde la vista de cuenta (no saca a Hans de /accounts/{id}).
     */
    public function editModal(Request $request, Transaction $transaction): View
    {
        return $this->modalForm($request, $transaction);
    }

    /** Igual que editModal pero para un movimiento nuevo */
    public function createModal(Request $request): View
    {
        return $this->modalForm($request, new Transaction([
            'date'       => now()->format('Y-m-d'),
            'type'       => $request->query('type', 'expense'),
            'account_id' => $request->query('account_id'),
        ]));
    }

    private function modalForm(Request $request, Transaction $transaction): View
    {
        $accounts = Account::where('is_active', true)->get()
            ->sortBy(fn (Account $a) => mb_strtolower($a->institutionLabel() . '·' . $a->name))
            ->values();

        return view('pages.transactions.modal-form', [
            'transaction' => $transaction,
            'accounts'    => $accounts,
            'categories'  => Category::active()->with('children')->orderBy('kind')->orderBy('name')->get(),
            'sources'     => Source::active()->orderBy('name')->get(),
            'redirectTo'  => $this->safeRedirectTo($request->query('redirect_to')),
        ]);
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

        $back = $this->safeRedirectTo($request->input('redirect_to'));

        return redirect($back ?: route('transactions.index'))->with('status', 'Movimiento actualizado.');
    }

    /**
     * Solo acepta rutas internas ("/accounts/2"), nunca URLs externas ni
     * protocol-relative, para no convertir el form en un open redirect.
     */
    private function safeRedirectTo(?string $target): ?string
    {
        if (! is_string($target) || $target === '') {
            return null;
        }

        return preg_match('#^/(?!/)[\w\-/?=&%.]*$#', $target) === 1 ? $target : null;
    }

    /**
     * Reordena manualmente los movimientos de un mismo día (conciliación
     * visual contra el estado de cuenta). Solo acepta ids de la misma fecha.
     */
    public function reorder(Request $request): JsonResponse
    {
        $data = $request->validate([
            'ids'   => 'required|array|min:1',
            'ids.*' => 'integer|exists:transactions,id',
        ]);

        $transactions = Transaction::whereIn('id', $data['ids'])->get();

        if ($transactions->count() !== count($data['ids'])) {
            return response()->json(['message' => 'Movimientos no encontrados.'], 422);
        }

        if ($transactions->pluck('date')->map(fn ($d) => $d->toDateString())->unique()->count() > 1) {
            return response()->json(['message' => 'Solo se puede reordenar dentro del mismo día.'], 422);
        }

        $byId = $transactions->keyBy('id');

        foreach ($data['ids'] as $index => $id) {
            // 1-based: el 0 queda reservado para «sin acomodar»
            $byId[$id]->update(['position' => $index + 1]);
        }

        return response()->json(['ok' => true]);
    }

    public function duplicate(Request $request, Transaction $transaction): RedirectResponse
    {
        // Copia el movimiento con la fecha de hoy y redirige al form de edición
        $copy = $transaction->replicate(['created_at', 'updated_at']);
        $copy->date = now()->toDateString();
        $copy->save();

        $back = $this->safeRedirectTo($request->input('redirect_to'));

        // Desde una cuenta: vuelve ahí con el modal abierto sobre la copia
        $target = $back
            ? $back . (str_contains($back, '?') ? '&' : '?') . 'edit=' . $copy->id
            : route('transactions.edit', $copy);

        return redirect($target)->with('status', 'Movimiento duplicado. Ajusta los datos y guarda.');
    }

    public function destroy(Request $request, Transaction $transaction): RedirectResponse
    {
        $transaction->delete();

        $back = $this->safeRedirectTo($request->input('redirect_to'));

        return redirect($back ?: route('transactions.index'))->with('status', 'Movimiento eliminado.');
    }
}
