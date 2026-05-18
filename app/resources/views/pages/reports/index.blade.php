<x-app-layout title="Reportes">

<x-page-header title="Reportes" />

{{-- ── Tabs de tipo de reporte ──────────────────────────────────── --}}
<div class="flex gap-1 mb-5 bg-white dark:bg-[#2a2a2a] rounded-xl p-1 border border-[#ababab]/15 shadow-sm">
    @foreach(['annual' => ['Anual', 'M3 3v18h18'], 'categories' => ['Categorías', 'M7 7h.01M7 3H5a2 2 0 00-2 2v2a2 2 0 00.586 1.414l9 9A2 2 0 0014 19l5-5a2 2 0 000-2.828l-9-9A2 2 0 007 3z'], 'sources' => ['Fuentes', 'M13 7h8m0 0v8m0-8l-8 8-4-4-6 6']] as $key => [$label, $iconPath])
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

{{-- ── Filtros comunes ──────────────────────────────────────────── --}}
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
                        <div class="w-7 h-7 rounded-lg flex items-center justify-center text-white text-xs font-bold flex-shrink-0"
                             style="background-color:{{ $cat['color'] }}">
                            {{ mb_strtoupper(mb_substr($cat['name'], 0, 1)) }}
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
