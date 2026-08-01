<x-app-layout title="Reportes">

<x-page-header title="Reportes" />

{{-- ── Tabs de tipo de reporte ──────────────────────────────────── --}}
<div class="flex gap-1 mb-5 bg-white dark:bg-[#2a2a2a] rounded-xl p-1 border border-[#ababab]/15 shadow-sm">
    @foreach(['annual' => ['Anual', 'M3 3v18h18'], 'categories' => ['Categorías', 'M7 7h.01M7 3H5a2 2 0 00-2 2v2a2 2 0 00.586 1.414l9 9A2 2 0 0014 19l5-5a2 2 0 000-2.828l-9-9A2 2 0 007 3z'], 'sources' => ['Fuentes', 'M13 7h8m0 0v8m0-8l-8 8-4-4-6 6'], 'yields' => ['Rendimientos', 'M9 8h6m-5 4h4m-6.5 8L12 18l4.5 2V6a2 2 0 00-2-2h-5a2 2 0 00-2 2v14zM9 8l6 8']] as $key => [$label, $iconPath])
    <a href="?type={{ $key }}&year={{ $year }}&month={{ $month }}@if($accountId)&account_id={{ $accountId }}@endif"
       class="flex-1 flex items-center justify-center gap-1.5 py-2 rounded-lg text-xs font-bold transition-all
           {{ $type === $key ? 'bg-[#76a72b] text-white shadow-sm' : 'text-[#878787] hover:text-[#373737] hover:bg-[#efeded]' }}">
        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $iconPath }}"/>
        </svg>
        {{ $label }}
    </a>
    @endforeach
</div>

{{-- ── Filtros comunes (rendimientos no usa filtros) ────────────── --}}
@if($type !== 'yields')
<form method="GET" class="flex gap-2 mb-5 flex-wrap">
    <input type="hidden" name="type" value="{{ $type }}">

    @if($type === 'annual')
    <select name="year" onchange="this.form.submit()"
        class="rounded-xl border border-[#ababab]/40 bg-white dark:bg-white/5 px-3 py-2 text-sm text-[#373737] dark:text-white focus:outline-none focus:ring-2 focus:ring-[#76a72b]">
        @for($y = now()->year; $y >= now()->year - 4; $y--)
        <option value="{{ $y }}" {{ $year == $y ? 'selected' : '' }}>{{ $y }}</option>
        @endfor
    </select>
    @else
    <input type="month" name="month" value="{{ $month }}" onchange="this.form.submit()"
        class="rounded-xl border border-[#ababab]/40 bg-white dark:bg-white/5 px-3 py-2 text-sm text-[#373737] dark:text-white focus:outline-none focus:ring-2 focus:ring-[#76a72b]">
    @endif

    <select name="account_id" onchange="this.form.submit()"
        class="rounded-xl border border-[#ababab]/40 bg-white dark:bg-white/5 px-3 py-2 text-sm text-[#373737] dark:text-white focus:outline-none focus:ring-2 focus:ring-[#76a72b]">
        <option value="">Todas las cuentas</option>
        @foreach($accounts as $a)
        <option value="{{ $a->id }}" {{ $accountId == $a->id ? 'selected' : '' }}>{{ $a->name }}</option>
        @endforeach
    </select>
</form>
@endif

{{-- ════════════════════════════════════════════════════════════════
     REPORTE ANUAL
     ════════════════════════════════════════════════════════════════ --}}
@if($type === 'annual')

{{-- Resumen del año --}}
<div class="grid grid-cols-3 gap-3 mb-5">
    <div class="bg-white dark:bg-[#2a2a2a] rounded-xl border border-[#ababab]/20 p-4 text-center">
        <p class="text-[10px] text-[#ababab] uppercase tracking-wider mb-1">Ingresos {{ $year }}</p>
        <p class="text-lg font-bold text-[#76a72b] tabular-nums">${{ number_format($totalIncome, 2) }}</p>
    </div>
    <div class="bg-white dark:bg-[#2a2a2a] rounded-xl border border-[#ababab]/20 p-4 text-center">
        <p class="text-[10px] text-[#ababab] uppercase tracking-wider mb-1">Egresos {{ $year }}</p>
        <p class="text-lg font-bold text-red-500 tabular-nums">${{ number_format($totalExpense, 2) }}</p>
    </div>
    <div class="rounded-xl p-4 text-center {{ $netBalance >= 0 ? 'bg-[#76a72b]/10 border border-[#76a72b]/20' : 'bg-red-50 border border-red-100' }}">
        <p class="text-[10px] text-[#ababab] uppercase tracking-wider mb-1">Balance</p>
        <p class="text-lg font-bold tabular-nums {{ $netBalance >= 0 ? 'text-[#76a72b]' : 'text-red-500' }}">
            {{ $netBalance >= 0 ? '+' : '' }}${{ number_format($netBalance, 2) }}
        </p>
    </div>
</div>

{{-- Gráfica de barras --}}
<div class="bg-white dark:bg-[#2a2a2a] rounded-2xl border border-[#ababab]/15 shadow-sm p-4 mb-5">
    <h3 class="text-sm font-bold text-[#373737] dark:text-white mb-4">Ingresos vs Egresos por mes</h3>
    <div class="relative" style="height:240px">
        <canvas id="annualChart"></canvas>
    </div>
</div>

{{-- Tabla mensual --}}
<div class="bg-white dark:bg-[#2a2a2a] rounded-2xl border border-[#ababab]/15 shadow-sm overflow-hidden">
    <table class="w-full text-sm">
        <thead>
            <tr class="border-b border-[#ababab]/10">
                <th class="px-4 py-3 text-left text-[10px] font-bold text-[#ababab] uppercase tracking-wider">Mes</th>
                <th class="px-4 py-3 text-right text-[10px] font-bold text-[#ababab] uppercase tracking-wider">Ingresos</th>
                <th class="px-4 py-3 text-right text-[10px] font-bold text-[#ababab] uppercase tracking-wider">Egresos</th>
                <th class="px-4 py-3 text-right text-[10px] font-bold text-[#ababab] uppercase tracking-wider">Neto</th>
                <th class="px-4 py-3 text-right text-[10px] font-bold text-[#ababab] uppercase tracking-wider hidden sm:table-cell">Acumulado</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-[#ababab]/08">
            @foreach($rows as $row)
            @php $hasData = $row['income'] > 0 || $row['expense'] > 0; @endphp
            <tr class="{{ $hasData ? '' : 'opacity-40' }} hover:bg-[#fafafa] dark:hover:bg-white/5 transition-colors">
                <td class="px-4 py-3 font-medium text-[#373737] dark:text-white capitalize">{{ $row['label'] }}</td>
                <td class="px-4 py-3 text-right tabular-nums text-[#76a72b] font-semibold">
                    {{ $row['income'] > 0 ? '+$'.number_format($row['income'], 2) : '—' }}
                </td>
                <td class="px-4 py-3 text-right tabular-nums text-red-500 font-semibold">
                    {{ $row['expense'] > 0 ? '-$'.number_format($row['expense'], 2) : '—' }}
                </td>
                <td class="px-4 py-3 text-right tabular-nums font-bold {{ $row['net'] >= 0 ? 'text-[#76a72b]' : 'text-red-500' }}">
                    {{ $row['net'] != 0 ? ($row['net'] > 0 ? '+' : '').'$'.number_format($row['net'], 2) : '—' }}
                </td>
                <td class="px-4 py-3 text-right tabular-nums text-[#878787] hidden sm:table-cell">
                    ${{ number_format($row['cumulative'], 2) }}
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>

{{-- ════════════════════════════════════════════════════════════════
     REPORTE POR CATEGORÍA
     ════════════════════════════════════════════════════════════════ --}}
@elseif($type === 'categories')

@php $monthCarbon = \Illuminate\Support\Carbon::createFromFormat('Y-m', $month); @endphp

<div class="grid grid-cols-2 gap-3 mb-5">
    <div class="bg-white dark:bg-[#2a2a2a] rounded-xl border border-[#ababab]/20 p-4 text-center">
        <p class="text-[10px] text-[#ababab] uppercase tracking-wider mb-1">Egresos {{ $monthCarbon->translatedFormat('M Y') }}</p>
        <p class="text-xl font-bold text-red-500 tabular-nums">${{ number_format($totalExpense, 2) }}</p>
    </div>
    <div class="bg-white dark:bg-[#2a2a2a] rounded-xl border border-[#ababab]/20 p-4 text-center">
        <p class="text-[10px] text-[#ababab] uppercase tracking-wider mb-1">Ingresos {{ $monthCarbon->translatedFormat('M Y') }}</p>
        <p class="text-xl font-bold text-[#76a72b] tabular-nums">${{ number_format($totalIncome, 2) }}</p>
    </div>
</div>

@if($byCategory->isEmpty())
<x-card class="text-center py-12">
    <p class="text-[#878787] text-sm">Sin egresos registrados en {{ $monthCarbon->translatedFormat('F Y') }}</p>
</x-card>
@else

{{-- Gráfica doughnut --}}
<div class="bg-white dark:bg-[#2a2a2a] rounded-2xl border border-[#ababab]/15 shadow-sm p-4 mb-5">
    <h3 class="text-sm font-bold text-[#373737] dark:text-white mb-4">Distribución de egresos</h3>
    <div class="flex flex-col sm:flex-row items-center gap-4">
        <div class="relative w-48 h-48 flex-shrink-0">
            <canvas id="catChart"></canvas>
        </div>
        <div class="flex-1 space-y-1.5 w-full">
            @foreach($byCategory->take(8) as $cat)
            @php $pct = $totalExpense > 0 ? ($cat['total'] / $totalExpense * 100) : 0; @endphp
            <div>
                <div class="flex items-center justify-between mb-0.5">
                    <div class="flex items-center gap-2">
                        <div class="w-2.5 h-2.5 rounded-full flex-shrink-0" style="background-color: {{ $cat['color'] }}"></div>
                        <span class="text-xs font-semibold text-[#373737] dark:text-white truncate max-w-[120px]">{{ $cat['name'] }}</span>
                    </div>
                    <span class="text-xs font-bold tabular-nums text-[#373737] dark:text-white ml-2">${{ number_format($cat['total'], 2) }}</span>
                </div>
                <div class="h-1.5 bg-[#efeded] dark:bg-white/10 rounded-full overflow-hidden">
                    <div class="h-full rounded-full" style="width:{{ number_format($pct, 1) }}%;background-color:{{ $cat['color'] }}"></div>
                </div>
            </div>
            @endforeach
        </div>
    </div>
</div>

{{-- Tabla de categorías --}}
<div class="bg-white dark:bg-[#2a2a2a] rounded-2xl border border-[#ababab]/15 shadow-sm overflow-hidden">
    <table class="w-full text-sm">
        <thead>
            <tr class="border-b border-[#ababab]/10">
                <th class="px-4 py-3 text-left text-[10px] font-bold text-[#ababab] uppercase tracking-wider">Categoría</th>
                <th class="px-4 py-3 text-right text-[10px] font-bold text-[#ababab] uppercase tracking-wider">Total</th>
                <th class="px-4 py-3 text-right text-[10px] font-bold text-[#ababab] uppercase tracking-wider">%</th>
                <th class="px-4 py-3 text-right text-[10px] font-bold text-[#ababab] uppercase tracking-wider hidden sm:table-cell">Movs.</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-[#ababab]/08">
            @foreach($byCategory as $cat)
            @php $pct = $totalExpense > 0 ? ($cat['total'] / $totalExpense * 100) : 0; @endphp
            <tr class="hover:bg-[#fafafa] dark:hover:bg-white/5 transition-colors">
                <td class="px-4 py-3">
                    <div class="flex items-center gap-2">
                        <div class="w-7 h-7 rounded-lg flex items-center justify-center text-white flex-shrink-0"
                             style="background-color:{{ $cat['color'] }}">
                            <x-category-icon :name="$cat['icon'] ?? 'tag'" class="w-3.5 h-3.5" />
                        </div>
                        <span class="font-semibold text-[#373737] dark:text-white">{{ $cat['name'] }}</span>
                    </div>
                </td>
                <td class="px-4 py-3 text-right tabular-nums font-bold text-red-500">-${{ number_format($cat['total'], 2) }}</td>
                <td class="px-4 py-3 text-right tabular-nums text-[#878787]">{{ number_format($pct, 1) }}%</td>
                <td class="px-4 py-3 text-right text-[#ababab] hidden sm:table-cell">{{ $cat['count'] }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>

@endif

{{-- ════════════════════════════════════════════════════════════════
     REPORTE DE RENDIMIENTOS (sofipos / cajas de ahorro)
     ════════════════════════════════════════════════════════════════ --}}
@elseif($type === 'yields')

{{-- Resumen --}}
<div class="grid grid-cols-3 gap-3 mb-5">
    <div class="bg-white dark:bg-[#2a2a2a] rounded-xl border border-[#ababab]/20 p-4 text-center">
        <p class="text-[10px] text-[#ababab] uppercase tracking-wider mb-1">Últimos {{ $yieldMonths }} meses</p>
        <p class="text-lg font-bold text-[#76a72b] tabular-nums">${{ number_format($yieldTotal, 2) }}</p>
    </div>
    <div class="bg-white dark:bg-[#2a2a2a] rounded-xl border border-[#ababab]/20 p-4 text-center">
        <p class="text-[10px] text-[#ababab] uppercase tracking-wider mb-1 capitalize">{{ $prevMonthName }}</p>
        <p class="text-lg font-bold text-[#76a72b] tabular-nums">${{ number_format($yieldPrevTotal, 2) }}</p>
    </div>
    <div class="bg-[#76a72b]/10 border border-[#76a72b]/20 rounded-xl p-4 text-center">
        <p class="text-[10px] text-[#ababab] uppercase tracking-wider mb-1">Promedio mensual</p>
        <p class="text-lg font-bold text-[#76a72b] tabular-nums">${{ number_format($yieldAvgMonth, 2) }}</p>
    </div>
</div>

@if($yieldRows->isEmpty())
<x-card class="text-center py-12">
    <p class="text-[#878787] text-sm">No hay cuentas de ahorro o inversión activas.</p>
    <a href="{{ route('accounts.create') }}" class="mt-3 inline-block text-[#4a7018] dark:text-[#76a72b] text-sm hover:underline">Crear cuenta de ahorro →</a>
</x-card>
@else

{{-- Pendientes de captura --}}
@php $yieldPendings = $yieldRows->where('pending', true); @endphp
@if($yieldPendings->isNotEmpty())
<div class="mb-5 p-4 rounded-xl bg-amber-50 dark:bg-amber-500/10 border border-amber-200 dark:border-amber-500/20">
    <p class="text-xs font-bold text-amber-600 dark:text-amber-400 uppercase tracking-wider mb-1.5">Falta capturar rendimiento</p>
    <div class="flex flex-wrap gap-x-4 gap-y-1">
        @foreach($yieldPendings as $row)
        <a href="{{ route('transactions.create') }}" class="text-sm text-amber-700 dark:text-amber-300 hover:underline">
            {{ $row['account']->name }}
            <span class="text-xs text-amber-500">({{ $row['last_capture'] ? 'último: '.$row['last_capture']->translatedFormat('j M Y') : 'sin capturas' }})</span>
        </a>
        @endforeach
    </div>
</div>
@endif

{{-- Gráfica de rendimiento mensual por cuenta --}}
<div class="bg-white dark:bg-[#2a2a2a] rounded-2xl border border-[#ababab]/15 shadow-sm p-4 mb-5">
    <h3 class="text-sm font-bold text-[#373737] dark:text-white mb-4">Rendimiento mensual por cuenta</h3>
    <div class="relative" style="height:240px">
        <canvas id="yieldsChart"></canvas>
    </div>
</div>

{{-- Comparativa por cuenta --}}
<div class="bg-white dark:bg-[#2a2a2a] rounded-2xl border border-[#ababab]/15 shadow-sm overflow-hidden mb-5">
    <div class="overflow-x-auto">
    <table class="w-full text-sm">
        <thead>
            <tr class="border-b border-[#ababab]/10">
                <th class="px-4 py-3 text-left text-[10px] font-bold text-[#ababab] uppercase tracking-wider">Cuenta</th>
                <th class="px-4 py-3 text-right text-[10px] font-bold text-[#ababab] uppercase tracking-wider capitalize">{{ $prevMonthName }}</th>
                <th class="px-4 py-3 text-right text-[10px] font-bold text-[#ababab] uppercase tracking-wider">{{ $yieldMonths }} meses</th>
                <th class="px-4 py-3 text-right text-[10px] font-bold text-[#ababab] uppercase tracking-wider">APR nominal</th>
                <th class="px-4 py-3 text-right text-[10px] font-bold text-[#ababab] uppercase tracking-wider">APR efectivo</th>
                <th class="px-4 py-3 text-right text-[10px] font-bold text-[#ababab] uppercase tracking-wider hidden sm:table-cell">Última captura</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-[#ababab]/08">
            @foreach($yieldRows as $row)
            @php
                $nominal = (float) ($row['apr_nominal'] ?? 0);
                $cap     = $row['apr_cap'] !== null ? (float) $row['apr_cap'] : null;
                // Con tope, el APR sobre el saldo total no puede llegar al nominal
                $expected  = $row['apr_expected'] !== null ? (float) $row['apr_expected'] : $nominal;
                $effective = $row['apr_effective'] !== null ? (float) $row['apr_effective'] : null;
                $belowNominal = $expected > 0 && $effective !== null && $effective < $expected - 0.5;
            @endphp
            <tr class="hover:bg-[#fafafa] dark:hover:bg-white/5 transition-colors">
                <td class="px-4 py-3">
                    <div class="flex items-center gap-2">
                        <div class="w-2.5 h-2.5 rounded-full flex-shrink-0" style="background-color: {{ $row['account']->color ?? '#76a72b' }}"></div>
                        <span class="font-semibold text-[#373737] dark:text-white">{{ $row['account']->name }}</span>
                        @if($row['pending'])
                        <span class="text-[10px] bg-amber-100 dark:bg-amber-500/15 text-amber-600 dark:text-amber-400 px-1.5 py-0.5 rounded-full font-semibold">Pendiente</span>
                        @endif
                    </div>
                </td>
                <td class="px-4 py-3 text-right tabular-nums font-semibold text-[#76a72b]">
                    {{ $row['interest_prev'] > 0 ? '+$'.number_format($row['interest_prev'], 2) : '—' }}
                </td>
                <td class="px-4 py-3 text-right tabular-nums font-bold text-[#76a72b]">
                    {{ $row['interest_sum'] > 0 ? '+$'.number_format($row['interest_sum'], 2) : '—' }}
                </td>
                <td class="px-4 py-3 text-right tabular-nums text-[#878787]">
                    {{ $nominal > 0 ? number_format($nominal, 2).'%' : '—' }}
                    @if($nominal > 0 && $cap)
                    <span class="block text-[10px] text-[#ababab]">
                        hasta ${{ number_format($cap, 2) }}
                        @if($expected > 0 && $expected < $nominal - 0.005)
                            · esperado {{ number_format($expected, 2) }}%
                        @endif
                    </span>
                    @endif
                </td>
                <td class="px-4 py-3 text-right tabular-nums font-bold {{ $belowNominal ? 'text-amber-500' : 'text-[#373737] dark:text-white' }}">
                    {{ $effective !== null && $effective > 0 ? number_format($effective, 2).'%' : '—' }}
                </td>
                <td class="px-4 py-3 text-right text-xs text-[#878787] hidden sm:table-cell">
                    {{ $row['last_capture'] ? $row['last_capture']->translatedFormat('j M Y') : '—' }}
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
    </div>
    <p class="px-4 py-3 text-[10px] text-[#ababab] border-t border-[#ababab]/10">
        APR efectivo = interés capturado ÷ saldo promedio mensual, anualizado. Si queda por debajo del nominal
        (en ámbar) suele deberse a retención de ISR o a saldo por arriba del tope de la tasa promocional.
    </p>
</div>

@endif

{{-- ════════════════════════════════════════════════════════════════
     REPORTE POR FUENTE
     ════════════════════════════════════════════════════════════════ --}}
@else

@php $monthCarbon = \Illuminate\Support\Carbon::createFromFormat('Y-m', $month); @endphp

<div class="bg-white dark:bg-[#2a2a2a] rounded-xl border border-[#ababab]/20 p-4 text-center mb-5">
    <p class="text-[10px] text-[#ababab] uppercase tracking-wider mb-1">Ingresos totales {{ $monthCarbon->translatedFormat('F Y') }}</p>
    <p class="text-2xl font-bold text-[#76a72b] tabular-nums">${{ number_format($totalIncome, 2) }}</p>
</div>

{{-- Evolución últimos 6 meses --}}
<div class="bg-white dark:bg-[#2a2a2a] rounded-2xl border border-[#ababab]/15 shadow-sm p-4 mb-5">
    <h3 class="text-sm font-bold text-[#373737] dark:text-white mb-4">Ingresos últimos 6 meses</h3>
    <div class="relative" style="height:200px">
        <canvas id="sourcesChart"></canvas>
    </div>
</div>

@if($bySource->isEmpty())
<x-card class="text-center py-12">
    <p class="text-[#878787] text-sm">Sin ingresos registrados en {{ $monthCarbon->translatedFormat('F Y') }}</p>
</x-card>
@else

<div class="bg-white dark:bg-[#2a2a2a] rounded-2xl border border-[#ababab]/15 shadow-sm overflow-hidden">
    <table class="w-full text-sm">
        <thead>
            <tr class="border-b border-[#ababab]/10">
                <th class="px-4 py-3 text-left text-[10px] font-bold text-[#ababab] uppercase tracking-wider">Fuente</th>
                <th class="px-4 py-3 text-right text-[10px] font-bold text-[#ababab] uppercase tracking-wider">Total</th>
                <th class="px-4 py-3 text-right text-[10px] font-bold text-[#ababab] uppercase tracking-wider">%</th>
                <th class="px-4 py-3 text-right text-[10px] font-bold text-[#ababab] uppercase tracking-wider hidden sm:table-cell">Movs.</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-[#ababab]/08">
            @foreach($bySource as $src)
            @php $pct = $totalIncome > 0 ? ($src['total'] / $totalIncome * 100) : 0; @endphp
            <tr class="hover:bg-[#fafafa] dark:hover:bg-white/5 transition-colors">
                <td class="px-4 py-3">
                    <div class="flex items-center gap-3">
                        <div class="w-7 h-7 rounded-lg bg-[#76a72b]/15 flex items-center justify-center flex-shrink-0">
                            <span class="text-xs font-bold text-[#76a72b]">{{ mb_strtoupper(mb_substr($src['name'], 0, 1)) }}</span>
                        </div>
                        <div>
                            <p class="font-semibold text-[#373737] dark:text-white">{{ $src['name'] }}</p>
                            <div class="h-1 bg-[#efeded] dark:bg-white/10 rounded-full mt-1 w-24 overflow-hidden">
                                <div class="h-full rounded-full bg-[#76a72b]" style="width:{{ number_format($pct, 1) }}%"></div>
                            </div>
                        </div>
                    </div>
                </td>
                <td class="px-4 py-3 text-right tabular-nums font-bold text-[#76a72b]">+${{ number_format($src['total'], 2) }}</td>
                <td class="px-4 py-3 text-right tabular-nums text-[#878787]">{{ number_format($pct, 1) }}%</td>
                <td class="px-4 py-3 text-right text-[#ababab] hidden sm:table-cell">{{ $src['count'] }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>

@endif
@endif

{{-- ── Chart.js ─────────────────────────────────────────────────── --}}
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
Chart.defaults.font.family = "'Roboto', system-ui, sans-serif";
Chart.defaults.color = '#878787';

@if($type === 'annual')
new Chart(document.getElementById('annualChart'), {
    type: 'bar',
    data: {
        labels: @json($monthLabels),
        datasets: [
            {
                label: 'Ingresos',
                data: @json($incomes),
                backgroundColor: '#76a72b88',
                borderColor: '#76a72b',
                borderWidth: 1.5,
                borderRadius: 6,
            },
            {
                label: 'Egresos',
                data: @json($expenses),
                backgroundColor: '#ef444488',
                borderColor: '#ef4444',
                borderWidth: 1.5,
                borderRadius: 6,
            },
        ]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { position: 'top' } },
        scales: {
            y: {
                beginAtZero: true,
                grid: { color: 'rgba(0,0,0,0.05)' },
                ticks: { callback: v => '$' + v.toLocaleString('es-MX') }
            },
            x: { grid: { display: false } }
        }
    }
});

@elseif($type === 'categories')
@if($byCategory->isNotEmpty())
new Chart(document.getElementById('catChart'), {
    type: 'doughnut',
    data: {
        labels: @json($catLabels),
        datasets: [{
            data: @json($catTotals),
            backgroundColor: @json($catColors),
            borderWidth: 2,
            borderColor: '#ffffff',
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        cutout: '68%',
        plugins: {
            legend: { display: false },
            tooltip: {
                callbacks: {
                    label: ctx => ' $' + ctx.raw.toLocaleString('es-MX', {minimumFractionDigits:2})
                }
            }
        }
    }
});
@endif

@elseif($type === 'yields')
@if($yieldRows->isNotEmpty())
new Chart(document.getElementById('yieldsChart'), {
    type: 'bar',
    data: {
        labels: @json($yieldLabels),
        datasets: @json($yieldDatasets),
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { position: 'top' },
            tooltip: {
                callbacks: {
                    label: ctx => ' ' + ctx.dataset.label + ': $' + ctx.raw.toLocaleString('es-MX', {minimumFractionDigits:2})
                }
            }
        },
        scales: {
            x: { stacked: true, grid: { display: false } },
            y: {
                stacked: true,
                beginAtZero: true,
                grid: { color: 'rgba(0,0,0,0.05)' },
                ticks: { callback: v => '$' + v.toLocaleString('es-MX') }
            }
        }
    }
});
@endif

@else
new Chart(document.getElementById('sourcesChart'), {
    type: 'bar',
    data: {
        labels: @json($srcLabels),
        datasets: [{
            label: 'Ingresos',
            data: @json($srcMonths),
            backgroundColor: '#76a72b88',
            borderColor: '#76a72b',
            borderWidth: 1.5,
            borderRadius: 6,
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: {
            y: {
                beginAtZero: true,
                grid: { color: 'rgba(0,0,0,0.05)' },
                ticks: { callback: v => '$' + v.toLocaleString('es-MX') }
            },
            x: { grid: { display: false } }
        }
    }
});
@endif
</script>

</x-app-layout>
