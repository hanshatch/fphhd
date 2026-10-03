<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Goal;
use App\Models\GoalContribution;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Planeación de metas de ahorro (viajes, proyectos, compras).
 *
 * Regla de cálculo: el dinero debe estar completo al INICIO del mes de la
 * meta. Lo que falta se reparte en partes iguales entre el mes actual y el
 * mes anterior al de la meta (redondeado hacia arriba al centavo; el último
 * mes ajusta la diferencia). Todo con bcmath.
 */
class GoalService
{
    /** Meses calendario entre dos fechas (ignora el día) */
    public static function monthsBetween(Carbon $from, Carbon $to): int
    {
        return ($to->year - $from->year) * 12 + ($to->month - $from->month);
    }

    /** Lo apartado: usa withSum si ya viene cargado para no hacer N+1 */
    public function saved(Goal $goal): string
    {
        $sum = $goal->contributions_sum_amount ?? $goal->contributions()->sum('amount');

        return bcadd((string) ($sum ?: 0), '0', 2);
    }

    /**
     * Cifras de una meta: apartado, faltante, cuota mensual y estado.
     *
     * @return array{saved:string, remaining:string, months_left:int, quota:string,
     *               pct:int, expected:string, status:string, payout_index:int}
     */
    public function summary(Goal $goal, ?Carbon $today = null): array
    {
        $today   = ($today ?? Carbon::today())->copy()->startOfMonth();
        $target  = (string) $goal->target_amount;
        $saved   = $this->saved($goal);
        $diff    = bcsub($target, $saved, 2);
        $remaining = bccomp($diff, '0', 2) > 0 ? $diff : '0.00';

        // Índice del mes en que se gasta (0 = este mes; vencida también cuenta como 0)
        $payoutIndex = max(0, self::monthsBetween($today, $goal->target_date->copy()->startOfMonth()));
        $monthsLeft  = max(1, $payoutIndex);

        $quota = bccomp($remaining, '0', 2) === 0 ? '0.00' : $this->ceilDiv($remaining, $monthsLeft);

        // Avance esperado lineal desde que se creó la meta hasta el mes objetivo
        $created = ($goal->created_at ?? Carbon::now())->copy()->startOfMonth();
        $total   = max(1, self::monthsBetween($created, $goal->target_date->copy()->startOfMonth()));
        $elapsed = min($total, max(0, self::monthsBetween($created, $today)));
        $expected = bcdiv(bcmul($target, (string) $elapsed, 2), (string) $total, 2);

        $status = match (true) {
            $goal->isCompleted()                          => 'completed',
            bccomp($remaining, '0', 2) === 0              => 'funded',
            $goal->target_date->lt(Carbon::today())       => 'overdue',
            bccomp($saved, $expected, 2) < 0              => 'behind',
            default                                       => 'on_track',
        };

        $pct = bccomp($target, '0', 2) > 0
            ? min(100, (int) bcdiv(bcmul($saved, '100', 2), $target, 0))
            : 0;

        return [
            'saved'        => $saved,
            'remaining'    => $remaining,
            'months_left'  => $monthsLeft,
            'quota'        => $quota,
            'pct'          => max(0, $pct),
            'expected'     => $expected,
            'status'       => $status,
            'payout_index' => $payoutIndex,
        ];
    }

    /**
     * Calendario mes a mes de las metas activas: cuánto aportar, cuánto
     * debes tener apartado al cierre y qué metas se pagan ese mes.
     *
     * @return array<int, array{month:Carbon, contribute:string, reserved:string, payout:string, due:array}>
     */
    public function schedule(Collection $goals, ?Carbon $today = null, int $minMonths = 6, int $maxMonths = 24): array
    {
        $today     = ($today ?? Carbon::today())->copy()->startOfMonth();
        $summaries = $goals->mapWithKeys(fn (Goal $g) => [$g->id => $this->summary($g, $today)]);

        $horizon = (int) $summaries->max('payout_index') + 1;
        $horizon = min($maxMonths, max($minMonths, $horizon));

        $rows = [];
        for ($k = 0; $k < $horizon; $k++) {
            $contribute = '0.00';
            $reserved   = '0.00';
            $payout     = '0.00';
            $due        = [];

            foreach ($goals as $goal) {
                $s = $summaries[$goal->id];
                $n = $s['months_left'];
                $p = $s['payout_index'];

                // Aportación de este mes para esta meta
                $quotaK = '0.00';
                if ($k < $n && bccomp($s['remaining'], '0', 2) > 0) {
                    $quotaK = $k < $n - 1
                        ? $s['quota']
                        : bcsub($s['remaining'], bcmul($s['quota'], (string) ($n - 1), 2), 2);
                }
                $contribute = bcadd($contribute, $quotaK, 2);

                if ($p === $k) {
                    $payout = bcadd($payout, (string) $goal->target_amount, 2);
                    $due[]  = $goal;
                } elseif ($p > $k) {
                    // Apartado acumulado al cierre del mes k (lo de hoy + cuotas hasta k)
                    $paid = min($k + 1, $n);
                    $acc  = $paid >= $n
                        ? (string) $goal->target_amount
                        : bcadd($s['saved'], bcmul($s['quota'], (string) $paid, 2), 2);
                    $reserved = bcadd($reserved, $acc, 2);
                }
            }

            $rows[] = [
                'month'      => $today->copy()->addMonths($k),
                'contribute' => $contribute,
                'reserved'   => $reserved,
                'payout'     => $payout,
                'due'        => $due,
            ];
        }

        return $rows;
    }

    /**
     * Todo lo que necesita la pantalla de metas.
     */
    public function overview(?Carbon $today = null): array
    {
        $today = $today ?? Carbon::today();

        $active = Goal::active()->with('account')->withSum('contributions', 'amount')
            ->orderBy('target_date')->get();
        $completed = Goal::where('status', Goal::STATUS_COMPLETED)->withSum('contributions', 'amount')
            ->orderByDesc('completed_at')->get();

        $summaries = $active->mapWithKeys(fn (Goal $g) => [$g->id => $this->summary($g, $today)]);

        $totals = [
            'target'    => bcsum($active->pluck('target_amount')),
            'saved'     => bcsum($summaries->pluck('saved')),
            'remaining' => bcsum($summaries->pluck('remaining')),
            'month'     => bcsum($summaries->pluck('quota')),
            'behind'    => $summaries->whereIn('status', ['behind', 'overdue'])->count(),
        ];

        return [
            'active'    => $active,
            'completed' => $completed,
            'summaries' => $summaries,
            'totals'    => $totals,
            'schedule'  => $active->isEmpty() ? [] : $this->schedule($active, $today),
            'byAccount' => $this->committedByAccount($active, $summaries),
        ];
    }

    /**
     * Cuánto de cada cuenta está comprometido en metas, contra su saldo real.
     */
    public function committedByAccount(Collection $active, Collection $summaries): Collection
    {
        $grouped = $active->filter(fn (Goal $g) => $g->account_id)->groupBy('account_id');
        if ($grouped->isEmpty()) {
            return collect();
        }

        $accounts = Account::whereIn('id', $grouped->keys())->get()->keyBy('id');
        $balances = app(AccountService::class)->balances($accounts->values());

        return $grouped->map(function (Collection $goals, $accountId) use ($accounts, $balances, $summaries) {
            $committed = bcsum($goals->map(fn (Goal $g) => $summaries[$g->id]['saved']));
            $balance   = $balances[$accountId] ?? '0.00';

            return [
                'account'   => $accounts[$accountId],
                'committed' => $committed,
                'balance'   => $balance,
                'short'     => bccomp($committed, $balance, 2) > 0,
                'goals'     => $goals->count(),
            ];
        })->values();
    }

    /** Crea o actualiza la meta con su desglose; con partidas, el total es su suma */
    public function save(Goal $goal, array $data, array $items): Goal
    {
        return DB::transaction(function () use ($goal, $data, $items) {
            $items = collect($items)
                ->filter(fn ($i) => trim((string) ($i['description'] ?? '')) !== '' && ($i['amount'] ?? null) !== null)
                ->values();

            if ($items->isNotEmpty()) {
                $data['target_amount'] = bcsum($items->pluck('amount'));
            }

            $goal->fill($data)->save();

            $goal->items()->delete();
            foreach ($items as $pos => $item) {
                $goal->items()->create([
                    'description' => trim($item['description']),
                    'amount'      => $item['amount'],
                    'position'    => $pos,
                ]);
            }

            return $goal;
        });
    }

    /** Aparta (monto positivo) o retira (negativo) dinero de la meta */
    public function contribute(Goal $goal, string $amount, string $date, ?string $note = null): GoalContribution
    {
        return $goal->contributions()->create([
            'amount' => $amount,
            'date'   => $date,
            'note'   => $note,
        ]);
    }

    public function complete(Goal $goal): void
    {
        $goal->update(['status' => Goal::STATUS_COMPLETED, 'completed_at' => now()]);
    }

    public function reopen(Goal $goal): void
    {
        $goal->update(['status' => Goal::STATUS_ACTIVE, 'completed_at' => null]);
    }

    /** División redondeada hacia arriba al centavo: mejor apartar de más que de menos */
    private function ceilDiv(string $amount, int $parts): string
    {
        $q = bcdiv($amount, (string) $parts, 2);

        return bccomp(bcmul($q, (string) $parts, 2), $amount, 2) < 0 ? bcadd($q, '0.01', 2) : $q;
    }
}
