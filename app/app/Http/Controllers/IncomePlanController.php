<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Category;
use App\Models\IncomePlan;
use App\Models\Source;
use App\Services\IncomePlanService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class IncomePlanController extends Controller
{
    public function __construct(private IncomePlanService $service) {}

    public function index(): View
    {
        $plans    = IncomePlan::with('account', 'source')->orderBy('next_expected_date')->get();
        $upcoming = $this->service->upcoming(30);
        $summary  = $this->service->monthlySummary();

        return view('pages.income-plans.index', compact('plans', 'upcoming', 'summary'));
    }

    public function create(): View
    {
        return view('pages.income-plans.form', [
            'plan'       => new IncomePlan(),
            'accounts'   => Account::where('is_active', true)->orderBy('name')->get(),
            'sources'    => Source::active()->orderBy('name')->get(),
            'categories' => Category::active()->ofKind('income')->with('children')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validate($request);
        IncomePlan::create($data);

        return redirect()->route('income-plans.index')->with('status', 'Ingreso esperado creado.');
    }

    public function edit(IncomePlan $incomePlan): View
    {
        return view('pages.income-plans.form', [
            'plan'       => $incomePlan,
            'accounts'   => Account::where('is_active', true)->orderBy('name')->get(),
            'sources'    => Source::active()->orderBy('name')->get(),
            'categories' => Category::active()->ofKind('income')->with('children')->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, IncomePlan $incomePlan): RedirectResponse
    {
        $data = $this->validate($request);
        $incomePlan->update($data);

        return redirect()->route('income-plans.index')->with('status', 'Ingreso esperado actualizado.');
    }

    public function destroy(IncomePlan $incomePlan): RedirectResponse
    {
        $incomePlan->delete();
        return redirect()->route('income-plans.index')->with('status', 'Ingreso eliminado.');
    }

    /** Mostrar formulario de registro con monto real */
    public function registerShow(IncomePlan $incomePlan): View
    {
        return view('pages.income-plans.register', [
            'plan'     => $incomePlan,
            'accounts' => Account::where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    /** Guardar el ingreso real como transacción */
    public function registerStore(Request $request, IncomePlan $incomePlan): RedirectResponse
    {
        $this->normalizeMoney($request, ['amount']);

        $request->validate([
            'amount'      => 'required|numeric|min:0.01',
            'date'        => 'required|date',
            'description' => 'nullable|string|max:500',
        ]);

        $this->service->register(
            $incomePlan,
            $request->amount,
            $request->date,
            $request->description
        );

        return redirect()->route('income-plans.index')
            ->with('status', "Ingreso «{$incomePlan->name}» registrado correctamente.");
    }

    /** Pausar / reactivar */
    public function toggle(IncomePlan $incomePlan): RedirectResponse
    {
        $incomePlan->update(['is_active' => ! $incomePlan->is_active]);
        return redirect()->route('income-plans.index')
            ->with('status', $incomePlan->is_active ? 'Ingreso reactivado.' : 'Ingreso pausado.');
    }

    // ── helpers ──────────────────────────────────────────────────────────

    private function validate(Request $request): array
    {
        $this->normalizeMoney($request, ['expected_amount']);

        $data = $request->validate([
            'name'               => 'required|string|max:150',
            'notes'              => 'nullable|string|max:500',
            'account_id'         => 'required|exists:accounts,id',
            'source_id'          => 'nullable|exists:sources,id',
            'category_id'        => 'nullable|exists:categories,id',
            'expected_amount'    => 'required|numeric|min:0.01',
            'frequency'          => 'required|in:biweekly,monthly,weekly',
            'day_1'              => 'nullable|integer|min:1|max:31',
            'day_2'              => 'nullable|integer|min:1|max:31',
            'next_expected_date' => 'required|date',
        ]);

        return $data;
    }
}
