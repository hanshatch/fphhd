<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Goal;
use App\Models\GoalContribution;
use App\Services\GoalService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class GoalController extends Controller
{
    public function __construct(private GoalService $service) {}

    public function index(): View
    {
        return view('pages.goals.index', $this->service->overview());
    }

    public function create(Request $request): View
    {
        return view('pages.goals.form', [
            'goal'     => new Goal(['kind' => $request->query('kind', Goal::KIND_TRIP), 'color' => '#76a72b']),
            'accounts' => $this->accounts(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        [$data, $items] = $this->validated($request);
        $goal = $this->service->save(new Goal(), $data, $items);

        return redirect()->route('goals.show', $goal)->with('status', "Meta «{$goal->name}» creada.");
    }

    public function show(Goal $goal): View
    {
        $goal->load(['items', 'contributions', 'account'])->loadSum('contributions', 'amount');

        return view('pages.goals.show', [
            'goal'    => $goal,
            'summary' => $this->service->summary($goal),
        ]);
    }

    public function edit(Goal $goal): View
    {
        return view('pages.goals.form', [
            'goal'     => $goal->load('items'),
            'accounts' => $this->accounts(),
        ]);
    }

    public function update(Request $request, Goal $goal): RedirectResponse
    {
        [$data, $items] = $this->validated($request);
        $this->service->save($goal, $data, $items);

        return redirect()->route('goals.show', $goal)->with('status', 'Meta actualizada.');
    }

    public function destroy(Goal $goal): RedirectResponse
    {
        $name = $goal->name;
        $goal->delete();

        return redirect()->route('goals.index')->with('status', "Meta «{$name}» eliminada.");
    }

    /** Apartar o retirar dinero de la meta */
    public function contribute(Request $request, Goal $goal): RedirectResponse
    {
        $this->normalizeMoney($request, ['amount']);

        $data = $request->validate([
            'amount'    => 'required|numeric|min:0.01',
            'direction' => 'required|in:add,withdraw',
            'date'      => 'required|date',
            'note'      => 'nullable|string|max:255',
        ]);

        $amount = $data['direction'] === 'withdraw' ? bcmul((string) $data['amount'], '-1', 2) : (string) $data['amount'];
        $this->service->contribute($goal, $amount, $data['date'], $data['note'] ?? null);

        $msg = $data['direction'] === 'withdraw'
            ? 'Retiraste ' . format_currency($data['amount']) . ' de la meta.'
            : 'Apartaste ' . format_currency($data['amount']) . ' para «' . $goal->name . '».';

        return redirect()->route('goals.show', $goal)->with('status', $msg);
    }

    public function destroyContribution(Goal $goal, GoalContribution $contribution): RedirectResponse
    {
        abort_unless($contribution->goal_id === $goal->id, 404);
        $contribution->delete();

        return redirect()->route('goals.show', $goal)->with('status', 'Movimiento de la meta eliminado.');
    }

    public function complete(Goal $goal): RedirectResponse
    {
        $this->service->complete($goal);

        return redirect()->route('goals.index')->with('status', "«{$goal->name}» marcada como realizada.");
    }

    public function reopen(Goal $goal): RedirectResponse
    {
        $this->service->reopen($goal);

        return redirect()->route('goals.show', $goal)->with('status', 'Meta reactivada.');
    }

    // ── helpers ──────────────────────────────────────────────────────────

    private function accounts()
    {
        return Account::where('is_active', true)->where('type', '!=', Account::TYPE_CREDIT)->get()
            ->sortBy(fn (Account $a) => mb_strtolower($a->displayLabel()))->values();
    }

    /** @return array{0: array, 1: array} datos de la meta y partidas del desglose */
    private function validated(Request $request): array
    {
        $this->normalizeMoney($request, ['target_amount']);

        // Las partidas aceptan «1,234.56» igual que cualquier monto
        $items = collect($request->input('items', []))->map(fn ($i) => [
            'description' => $i['description'] ?? null,
            'amount'      => isset($i['amount']) && $i['amount'] !== '' ? parse_money((string) $i['amount']) : null,
        ])->all();
        $request->merge(['items' => $items]);

        $data = $request->validate([
            'name'                => 'required|string|max:150',
            'kind'                => ['required', Rule::in(array_keys(Goal::KINDS))],
            'target_date'         => 'required|date',
            'account_id'          => 'nullable|exists:accounts,id',
            'color'               => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'notes'               => 'nullable|string|max:1000',
            'target_amount'       => 'nullable|numeric|min:0.01',
            'items'               => 'array',
            'items.*.description' => 'nullable|string|max:150',
            'items.*.amount'      => 'nullable|numeric|min:0',
        ]);

        $items = collect($data['items'] ?? [])
            ->filter(fn ($i) => trim((string) $i['description']) !== '' && $i['amount'] !== null)
            ->values()->all();

        if ($items === [] && empty($data['target_amount'])) {
            throw ValidationException::withMessages([
                'target_amount' => 'Escribe el costo total o agrega al menos una partida del desglose.',
            ]);
        }

        unset($data['items']);
        $data['color'] ??= '#76a72b';

        return [$data, $items];
    }
}
