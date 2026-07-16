<?php

namespace App\Http\Controllers;

use App\Models\Budget;
use App\Models\Category;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class BudgetController extends Controller
{
    public function index(Request $request): View
    {
        $month = $request->filled('month')
            ? Carbon::createFromFormat('Y-m', $request->month)->startOfMonth()
            : now()->startOfMonth();

        $budgets = Budget::with('category')
            ->get()
            ->sortBy(fn ($b) => $b->category?->name)
            ->map(fn ($b) => [
                'budget'  => $b,
                'spent'   => $b->spent($month),
                'percent' => $b->percentUsed($month),
            ]);

        $totalLimit = $budgets->sum(fn ($b) => (float) $b['budget']->amount);
        $totalSpent = $budgets->sum(fn ($b) => (float) $b['spent']);

        // Categorías de egreso sin presupuesto aún (para el selector del form)
        $usedIds = Budget::pluck('category_id');
        $available = Category::active()
            ->where('kind', 'expense')
            ->whereNotIn('id', $usedIds)
            ->orderBy('name')
            ->get();

        return view('pages.budgets.index', compact(
            'budgets', 'totalLimit', 'totalSpent', 'available', 'month'
        ));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->normalizeMoney($request, ['amount']);

        $data = $request->validate([
            'category_id' => 'required|exists:categories,id|unique:budgets,category_id',
            'amount'      => 'required|numeric|min:1',
            'notes'       => 'nullable|string|max:255',
        ]);

        Budget::create($data);

        return redirect()->route('budgets.index')->with('status', 'Presupuesto creado.');
    }

    public function update(Request $request, Budget $budget): RedirectResponse
    {
        $this->normalizeMoney($request, ['amount']);

        $data = $request->validate([
            'amount' => 'required|numeric|min:1',
            'notes'  => 'nullable|string|max:255',
        ]);

        $budget->update($data);

        return redirect()->route('budgets.index')->with('status', 'Presupuesto actualizado.');
    }

    public function destroy(Budget $budget): RedirectResponse
    {
        $budget->delete();

        return redirect()->route('budgets.index')->with('status', 'Presupuesto eliminado.');
    }
}
