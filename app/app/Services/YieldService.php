<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Transaction;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class YieldService
{
    public function __construct(private AccountService $accountService) {}

    /**
     * Reporte de rendimientos de cuentas de ahorro/inversión.
     * Por cuenta y mes: interés capturado, saldo promedio y APR efectivo
     * anualizado (intereses observados sobre saldo promedio, glosario §7).
     * Todo en bcmath; float solo al serializar series para Chart.js.
     */
    public function report(int $months = 12): array
    {
        $accounts = $this->yieldAccounts();

        $monthKeys   = [];
        $monthLabels = [];
        for ($i = $months - 1; $i >= 0; $i--) {
            $m = now()->subMonthsNoOverflow($i);
            $monthKeys[]   = $m->format('Y-m');
            $monthLabels[] = $m->translatedFormat('M y');
        }

        $from = Carbon::createFromFormat('Y-m', $monthKeys[0])->startOfMonth()->toDateString();
        $to   = now()->endOfMonth()->toDateString();

        // Interés por cuenta/mes agrupado en PHP (agnóstico al driver de BD)
        $interestByAccount = Transaction::where('type', 'interest')
            ->whereIn('account_id', $accounts->pluck('id'))
            ->whereBetween('date', [$from, $to])
            ->get()
            ->groupBy('account_id')
            ->map(fn ($txs) => $txs
                ->groupBy(fn ($tx) => $tx->date->format('Y-m'))
                ->map(fn ($g) => bcsum($g->pluck('amount'))));

        $lastCaptures = Transaction::where('type', 'interest')
            ->whereIn('account_id', $accounts->pluck('id'))
            ->selectRaw('account_id, MAX(date) as last_date')
            ->groupBy('account_id')
            ->pluck('last_date', 'account_id');

        $prevMonthKey  = now()->subMonthNoOverflow()->format('Y-m');
        $pendingCutoff = now()->subMonthNoOverflow()->startOfMonth();

        $rows          = [];
        $chartDatasets = [];

        foreach ($accounts as $account) {
            $byMonth = $interestByAccount[$account->id] ?? collect();

            // Saldos en las fronteras de mes: el promedio del mes m es
            // (saldo al cierre de m-1 + saldo al cierre de m) / 2
            $boundaries = [];
            $dayBefore  = Carbon::createFromFormat('Y-m', $monthKeys[0])->startOfMonth()->subDay()->toDateString();
            $boundaries[] = $this->accountService->balance($account, $dayBefore);
            foreach ($monthKeys as $key) {
                $monthEnd     = Carbon::createFromFormat('Y-m', $key)->endOfMonth()->toDateString();
                $boundaries[] = $this->accountService->balance($account, $monthEnd);
            }

            $monthly       = [];
            $sumInterest   = '0.00';
            $sumAvgBalance = '0.00';

            foreach ($monthKeys as $i => $key) {
                $interest = bcadd((string) ($byMonth[$key] ?? '0'), '0', 2);
                $avg      = bcdiv(bcadd($boundaries[$i], $boundaries[$i + 1], 2), '2', 2);

                $apr = bccomp($avg, '0', 2) > 0
                    ? bcmul(bcdiv($interest, $avg, 6), '1200', 2)
                    : null;

                $monthly[$key] = ['interest' => $interest, 'avg' => $avg, 'apr' => $apr];

                $sumInterest = bcadd($sumInterest, $interest, 2);
                if (bccomp($avg, '0', 2) > 0) {
                    $sumAvgBalance = bcadd($sumAvgBalance, $avg, 2);
                }
            }

            // APR efectivo del periodo: interés total sobre la suma de saldos
            // promedio mensuales (equivale a promedio ponderado anualizado)
            $aprEffective = bccomp($sumAvgBalance, '0', 2) > 0
                ? bcmul(bcdiv($sumInterest, $sumAvgBalance, 6), '1200', 2)
                : null;

            $lastCapture = isset($lastCaptures[$account->id])
                ? Carbon::parse($lastCaptures[$account->id])
                : null;

            $rows[] = [
                'account'       => $account,
                'monthly'       => $monthly,
                'interest_prev' => bcadd((string) ($byMonth[$prevMonthKey] ?? '0'), '0', 2),
                'interest_sum'  => $sumInterest,
                'apr_nominal'   => $account->invest_apr,
                'apr_effective' => $aprEffective,
                'last_capture'  => $lastCapture,
                'pending'       => $lastCapture === null || $lastCapture->lt($pendingCutoff),
            ];

            $chartDatasets[] = [
                'label'           => $account->name,
                'data'            => array_map(fn ($key) => (float) $monthly[$key]['interest'], $monthKeys),
                'backgroundColor' => ($account->color ?? '#76a72b') . 'cc',
                'borderColor'     => $account->color ?? '#76a72b',
                'borderWidth'     => 1.5,
                'borderRadius'    => 4,
            ];
        }

        $rowsCollection = collect($rows)->sortByDesc(fn ($r) => (float) $r['interest_sum'])->values();

        return [
            'yieldRows'      => $rowsCollection,
            'yieldLabels'    => $monthLabels,
            'yieldDatasets'  => $chartDatasets,
            'yieldTotal'     => bcsum($rowsCollection->pluck('interest_sum')),
            'yieldPrevTotal' => bcsum($rowsCollection->pluck('interest_prev')),
            'yieldAvgMonth'  => bcdiv(bcsum($rowsCollection->pluck('interest_sum')), (string) $months, 2),
            'yieldMonths'    => $months,
            'prevMonthName'  => now()->subMonthNoOverflow()->translatedFormat('F Y'),
        ];
    }

    /**
     * Cuentas de ahorro/inversión sin interés capturado desde el inicio
     * del mes anterior — candidatas a "falta capturar rendimiento".
     */
    public function pendingCaptures(): Collection
    {
        $accounts = $this->yieldAccounts();
        $cutoff   = now()->subMonthNoOverflow()->startOfMonth();

        $lastCaptures = Transaction::where('type', 'interest')
            ->whereIn('account_id', $accounts->pluck('id'))
            ->selectRaw('account_id, MAX(date) as last_date')
            ->groupBy('account_id')
            ->pluck('last_date', 'account_id');

        return $accounts
            ->map(fn ($account) => [
                'account' => $account,
                'last'    => isset($lastCaptures[$account->id])
                    ? Carbon::parse($lastCaptures[$account->id])
                    : null,
            ])
            ->filter(fn ($row) => $row['last'] === null || $row['last']->lt($cutoff))
            ->values();
    }

    private function yieldAccounts(): Collection
    {
        return Account::where('is_active', true)
            ->whereIn('type', [Account::TYPE_SAVINGS, Account::TYPE_INVESTMENT])
            ->orderBy('name')
            ->get();
    }
}
