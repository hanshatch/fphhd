<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Category;
use App\Models\RecurringCharge;
use App\Services\RecurringChargeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class RecurringChargeController extends Controller
{
    public function __construct(private RecurringChargeService $service) {}

    public function index(): View
    {
        $active    = RecurringCharge::active()->with('account', 'category')->orderBy('next_application_date')->get();
        $completed = RecurringCharge::where('is_active', false)->with('account', 'category')->orderByDesc('updated_at')->limit(20)->get();
        $upcoming  = $this->service->upcoming(30);

        return view('pages.recurring.index', compact('active', 'completed', 'upcoming'));
    }

    public function create(): View
    {
        return view('pages.recurring.form', [
            'charge'     => new RecurringCharge(),
            'accounts'   => Account::where('is_active', true)->orderBy('name')->get(),
            'categories' => Category::active()->with('children')->orderBy('kind')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateCharge($request);

        // Calcular next_application_date desde start_date y day_of_month
        $data['next_application_date'] = $this->computeFirstDate(
            $data['start_date'],
            (int) $data['day_of_month']
        );

        RecurringCharge::create($data);

        return redirect()->route('recurring.index')->with('status', 'Cargo recurrente creado.');
    }

    public function edit(RecurringCharge $recurring): View
    {
        return view('pages.recurring.form', [
            'charge'     => $recurring,
            'accounts'   => Account::where('is_active', true)->orderBy('name')->get(),
            'categories' => Category::active()->with('children')->orderBy('kind')->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, RecurringCharge $recurring): RedirectResponse
    {
        $data = $this->validateCharge($request);
        $recurring->update($data);

        return redirect()->route('recurring.index')->with('status', 'Cargo actualizado.');
    }

    public function destroy(RecurringCharge $recurring): RedirectResponse
    {
        $recurring->delete();
        return redirect()->route('recurring.index')->with('status', 'Cargo eliminado.');
    }

    /** Aplicar manualmente un cargo ahora */
    public function applyNow(RecurringCharge $recurring): RedirectResponse
    {
        $this->service->applyCharge($recurring);
        return redirect()->route('recurring.index')->with('status', "Cargo «{$recurring->name}» aplicado manualmente.");
    }

    /** Pausar / reactivar */
    public function toggleActive(RecurringCharge $recurring): RedirectResponse
    {
        $recurring->update(['is_active' => ! $recurring->is_active]);
        $msg = $recurring->is_active ? 'Cargo reactivado.' : 'Cargo pausado.';
        return redirect()->route('recurring.index')->with('status', $msg);
    }

    // ── Helpers ───────────────────────────────────────────────────────

    private function validateCharge(Request $request): array
    {
        $data = $request->validate([
            'name'               => 'required|string|max:150',
            'description'        => 'nullable|string|max:500',
            'account_id'         => 'required|exists:accounts,id',
            'category_id'        => 'nullable|exists:categories,id',
            'type'               => 'required|in:expense,income',
            'amount'             => 'required|numeric|min:0.01',
            'day_of_month'       => 'required|integer|min:1|max:31',
            'start_date'         => 'required|date',
            'end_date'           => 'nullable|date|after_or_equal:start_date',
            'is_msi'             => 'boolean',
            'total_installments' => 'nullable|integer|min:2|max:360',
            'original_amount'    => 'nullable|numeric|min:0',
            'notes'              => 'nullable|string|max:500',
        ]);

        $data['amount']          = number_format((float) $data['amount'], 2, '.', '');
        $data['original_amount'] = $data['original_amount']
            ? number_format((float) $data['original_amount'], 2, '.', '')
            : null;
        $data['is_msi']          = $request->boolean('is_msi');

        // Si es MSI, calcular end_date automáticamente
        if ($data['is_msi'] && $data['total_installments']) {
            $end = Carbon::parse($data['start_date'])->addMonths($data['total_installments'] - 1);
            $data['end_date'] = $end->toDateString();
        }

        return $data;
    }

    private function computeFirstDate(string $startDate, int $day): string
    {
        $start = Carbon::parse($startDate);
        $day   = min($day, $start->daysInMonth);
        return $start->setDay($day)->toDateString();
    }
}
