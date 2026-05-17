<x-app-layout title="Panel">

    {{-- Saludo --}}
    <div class="mb-5">
        <h1 class="text-2xl font-bold text-[#373737] dark:text-white font-['Nunito']">
            Hola, <span class="text-[#76a72b]">Hans</span> 👋
        </h1>
        <p class="text-[#878787] text-sm mt-0.5">{{ now()->translatedFormat('l, d \d\e F \d\e Y') }}</p>
    </div>

    {{-- ── Fila 1: Patrimonio + Flujo del mes ─────────────────────── --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mb-4">

        {{-- Patrimonio neto --}}
        <div class="lg:col-span-1">
            <x-card class="p-5 bg-[#373737] dark:bg-[#222] border-0 h-full flex flex-col justify-between">
                <div>
                    <p class="text-white/40 text-[10px] font-bold uppercase tracking-widest mb-2">Patrimonio neto</p>
                    <p class="text-3xl font-bold text-white font-['Nunito'] leading-none">
                        ${{ number_format((float)$netWorth, 2) }}
                    </p>
                    <p class="text-white/30 text-xs mt-1">MXN</p>
                </div>
                <div class="flex gap-5 mt-4">
                    @php
                    $assets = $accounts->filter(fn($i) => !$i['account']->isCredit())->sum(fn($i) => (float)$i['balance']);
                    $debts  = $accounts->filter(fn($i) =>  $i['account']->isCredit())->sum(fn($i) => (float)$i['balance']);
                    @endphp
                    <div>
                        <p class="text-white/30 text-[10px] uppercase tracking-wider">Activos</p>
                        <p class="text-[#76a72b] font-bold text-sm font-['Nunito']">${{ number_format($assets, 2) }}</p>
                    </div>
                    <div>
                        <p class="text-white/30 text-[10px] uppercase tracking-wider">Tarjetas</p>
                        <p class="text-red-400 font-bold text-sm font-['Nunito']">${{ number_format($debts, 2) }}</p>
                    </div>
                </div>
            </x-card>
        </div>

        {{-- Flujo del mes --}}
        <div class="lg:col-span-2">
            <x-card class="p-5 h-full">
                <div class="flex items-center justify-between mb-4">
                    <p class="text-xs font-bold text-[#878787] uppercase tracking-widest">Flujo de {{ now()->translatedFormat('F Y') }}</p>
                    <a href="{{ route('transactions.index', ['from' => $flow['from'], 'to' => $flow['to']]) }}"
                       class="text-xs text-[#76a72b] hover:underline font-semibold">Ver detalle →</a>
                </div>
                <div class="grid grid-cols-3 gap-4">
                    <div class="text-center">
                        <div class="text-xs text-[#ababab] mb-1 uppercase tracking-wider">Ingresos</div>
                        <div class="text-xl font-bold text-[#76a72b] font-['Nunito']">${{ number_format((float)$flow['income'], 2) }}</div>
                    </div>
                    <div class="text-center">
                        <div class="text-xs text-[#ababab] mb-1 uppercase tracking-wider">Egresos</div>
                        <div class="text-xl font-bold text-red-500 font-['Nunito']">${{ number_format((float)$flow['expenses'], 2) }}</div>
                    </div>
                    <div class="text-center">
                        <div class="text-xs text-[#ababab] mb-1 uppercase tracking-wider">Neto</div>
                        <div class="text-xl font-bold font-['Nunito'] {{ (float)$flow['net'] >= 0 ? 'text-[#76a72b]' : 'text-red-500' }}">
                            {{ (float)$flow['net'] >= 0 ? '+' : '' }}${{ number_format((float)$flow['net'], 2) }}
                        </div>
                    </div>
                </div>

                {{-- Barra de progreso ingreso vs gasto --}}
                @php
                $total = max((float)$flow['income'], (float)$flow['expenses'], 1);
                $incPct = min(100, round(((float)$flow['income'] / $total) * 100));
                $expPct = min(100, round(((float)$flow['expenses'] / $total) * 100));
                @endphp
                <div class="mt-4 space-y-2">
                    <div class="flex items-center gap-2">
                        <span class="text-xs text-[#ababab] w-16 text-right">Ingreso</span>
                        <div class="flex-1 h-2 bg-[#efeded] dark:bg-white/10 rounded-full overflow-hidden">
                            <div class="h-full bg-[#76a72b] rounded-full transition-all" style="width: {{ $incPct }}%"></div>
                        </div>
                        <span class="text-xs text-[#878787] w-8">{{ $incPct }}%</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="text-xs text-[#ababab] w-16 text-right">Egreso</span>
                        <div class="flex-1 h-2 bg-[#efeded] dark:bg-white/10 rounded-full overflow-hidden">
                            <div class="h-full bg-red-400 rounded-full transition-all" style="width: {{ $expPct }}%"></div>
                        </div>
                        <span class="text-xs text-[#878787] w-8">{{ $expPct }}%</span>
                    </div>
                </div>
            </x-card>
        </div>
    </div>

    {{-- ── Fila 2: Gráfica 6 meses + Top categorías ────────────────── --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-4">

        {{-- Gráfica barras 6 meses --}}
        <x-card class="p-5">
            <p class="text-xs font-bold text-[#878787] uppercase tracking-widest mb-4">Últimos 6 meses</p>
            <div class="relative h-44">
                <canvas id="flowChart"></canvas>
            </div>
        </x-card>

        {{-- Top categorías de gasto --}}
        <x-card class="p-5">
            <div class="flex items-center justify-between mb-4">
                <p class="text-xs font-bold text-[#878787] uppercase tracking-widest">Top gastos del mes</p>
                <a href="{{ route('transactions.index', ['type' => 'expense', 'from' => $flow['from'], 'to' => $flow['to']]) }}"
                   class="text-xs text-[#76a72b] hover:underline font-semibold">Ver todos →</a>
            </div>
            @if($topCategories->isEmpty())
                <div class="text-center py-6">
                    <p class="text-[#ababab] text-sm">Sin egresos este mes</p>
                </div>
            @else
                <div class="space-y-3">
                    @foreach($topCategories as $cat)
                    <div>
                        <div class="flex items-center justify-between mb-1">
                            <div class="flex items-center gap-2">
                                <div class="w-2.5 h-2.5 rounded-full flex-shrink-0" style="background-color: {{ $cat['color'] }}"></div>
                                <span class="text-sm text-[#373737] dark:text-white font-medium truncate max-w-[140px]">{{ $cat['name'] }}</span>
                            </div>
                            <span class="text-sm font-bold text-[#373737] dark:text-white font-['Nunito']">${{ number_format($cat['total'], 2) }}</span>
                        </div>
                        <div class="h-1.5 bg-[#efeded] dark:bg-white/10 rounded-full overflow-hidden">
                            <div class="h-full rounded-full transition-all duration-500"
                                 style="width: {{ $cat['percent'] }}%; background-color: {{ $cat['color'] }}"></div>
                        </div>
                    </div>
                    @endforeach
                </div>
            @endif
        </x-card>
    </div>

    {{-- ── Fila 3: Saldos + Rendimientos ───────────────────────────── --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mb-4">

        {{-- Saldos por cuenta --}}
        <div class="lg:col-span-2">
            <x-card class="p-5">
                <div class="flex items-center justify-between mb-4">
                    <p class="text-xs font-bold text-[#878787] uppercase tracking-widest">Saldos por cuenta</p>
                    <a href="{{ route('accounts.index') }}" class="text-xs text-[#76a72b] hover:underline font-semibold">Gestionar →</a>
                </div>
                @if($accounts->isEmpty())
                    <div class="text-center py-6">
                        <p class="text-[#ababab] text-sm">No hay cuentas activas</p>
                        <x-btn href="{{ route('accounts.create') }}" class="mt-3 text-xs">Crear cuenta</x-btn>
                    </div>
                @else
                    @php
                    $typeLabels = ['debit' => 'Débito', 'credit' => 'TDC', 'savings' => 'Ahorro', 'investment' => 'Inversión', 'cash' => 'Efectivo'];
                    @endphp
                    <div class="divide-y divide-[#efeded] dark:divide-white/10">
                        @foreach($accounts as $item)
                        @php $acc = $item['account']; $bal = $item['balance']; @endphp
                        <div class="flex items-center gap-3 py-3 first:pt-0 last:pb-0">
                            <div class="w-8 h-8 rounded-lg flex items-center justify-center flex-shrink-0"
                                 style="background-color: {{ $acc->color }}22">
                                <div class="w-2.5 h-2.5 rounded-full" style="background-color: {{ $acc->color }}"></div>
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-semibold text-[#373737] dark:text-white truncate font-['Nunito']">{{ $acc->name }}</p>
                                <p class="text-[10px] text-[#ababab] capitalize">{{ $typeLabels[$acc->type] ?? $acc->type }} · {{ $acc->institution }}</p>
                            </div>
                            <div class="text-right">
                                <p class="font-bold text-sm {{ $acc->isCredit() ? 'text-red-500' : 'text-[#373737] dark:text-white' }} font-['Nunito']">
                                    ${{ number_format((float)$bal, 2) }}
                                </p>
                                @if($acc->isInvestment() && $acc->invest_apr)
                                    <p class="text-[10px] text-[#76a72b]">{{ $acc->invest_apr }}% APR</p>
                                @endif
                            </div>
                        </div>
                        @endforeach
                    </div>
                @endif
            </x-card>
        </div>

        {{-- Rendimientos del mes --}}
        <div class="lg:col-span-1">
            <x-card class="p-5 h-full">
                <p class="text-xs font-bold text-[#878787] uppercase tracking-widest mb-4">Rendimientos del mes</p>
                @if($monthlyInterest->isEmpty())
                    <div class="text-center py-6">
                        <svg class="mx-auto w-8 h-8 text-[#ababab] mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/></svg>
                        <p class="text-[#ababab] text-xs">Sin intereses registrados este mes</p>
                        <p class="text-[10px] text-[#ababab] mt-1">Registra movimientos tipo "Interés"</p>
                    </div>
                @else
                    @php $totalInterest = array_sum(array_column($monthlyInterest->toArray(), 'total')); @endphp
                    <div class="mb-4 p-3 bg-[#76a72b]/10 rounded-xl text-center">
                        <p class="text-xs text-[#76a72b] font-semibold">Total del mes</p>
                        <p class="text-xl font-bold text-[#76a72b] font-['Nunito']">${{ number_format($totalInterest, 2) }}</p>
                    </div>
                    <div class="space-y-3">
                        @foreach($monthlyInterest as $r)
                        <div class="flex items-center gap-2">
                            <div class="w-2.5 h-2.5 rounded-full flex-shrink-0" style="background-color: {{ $r['color'] }}"></div>
                            <span class="text-xs text-[#878787] flex-1 truncate">{{ $r['name'] }}</span>
                            <span class="text-xs font-bold text-[#76a72b] font-['Nunito']">${{ number_format($r['total'], 2) }}</span>
                        </div>
                        @endforeach
                    </div>
                @endif
            </x-card>
        </div>
    </div>

    {{-- ── Fila 4: Últimos movimientos ──────────────────────────────── --}}
    <x-card>
        <div class="flex items-center justify-between p-5 border-b border-[#efeded] dark:border-white/10">
            <p class="text-xs font-bold text-[#878787] uppercase tracking-widest">Últimos movimientos</p>
            <a href="{{ route('transactions.create') }}"
               class="inline-flex items-center gap-1.5 text-xs text-[#76a72b] hover:underline font-semibold">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/></svg>
                Registrar
            </a>
        </div>

        @if($recent->isEmpty())
            <div class="text-center py-12">
                <svg class="mx-auto w-10 h-10 text-[#ababab] mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/></svg>
                <p class="text-[#878787] text-sm font-medium">Sin movimientos aún</p>
                <x-btn href="{{ route('transactions.create') }}" class="mt-4">Registrar el primero</x-btn>
            </div>
        @else
            @php
            $txCfg = [
                'income'   => ['bg' => 'bg-[#76a72b]/10', 'text' => 'text-[#76a72b]',  'sign' => '+', 'label' => 'Ingreso'],
                'interest' => ['bg' => 'bg-[#76a72b]/10', 'text' => 'text-[#76a72b]',  'sign' => '+', 'label' => 'Interés'],
                'expense'  => ['bg' => 'bg-red-50',        'text' => 'text-red-500',      'sign' => '-', 'label' => 'Egreso'],
                'fee'      => ['bg' => 'bg-red-50',        'text' => 'text-red-500',      'sign' => '-', 'label' => 'Comisión'],
                'transfer' => ['bg' => 'bg-[#efeded]',     'text' => 'text-[#878787]',   'sign' => '',  'label' => 'Transferencia'],
            ];
            @endphp
            <div class="divide-y divide-[#efeded] dark:divide-white/10">
                @foreach($recent as $tx)
                @php $c = $txCfg[$tx->type] ?? $txCfg['transfer']; @endphp
                <a href="{{ route('transactions.edit', $tx) }}"
                   class="flex items-center gap-3 px-5 py-3.5 hover:bg-[#efeded]/50 dark:hover:bg-white/5 transition-colors group">
                    <div class="w-9 h-9 rounded-xl {{ $c['bg'] }} flex items-center justify-center flex-shrink-0 text-sm font-bold {{ $c['text'] }}">
                        {{ $c['sign'] ?: '⇄' }}
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-semibold text-[#373737] dark:text-white truncate font-['Nunito']">
                            {{ $tx->description ?: $c['label'] }}
                        </p>
                        <p class="text-xs text-[#ababab]">
                            {{ $tx->date->translatedFormat('d M Y') }}
                            · {{ $tx->account->name }}
                            @if($tx->category) · {{ $tx->category->name }} @endif
                        </p>
                    </div>
                    <span class="font-bold text-sm {{ $c['text'] }} font-['Nunito'] flex-shrink-0">
                        {{ $c['sign'] }}${{ number_format((float)$tx->amount, 2) }}
                    </span>
                </a>
                @endforeach
            </div>
            <div class="p-4 border-t border-[#efeded] dark:border-white/10 text-center">
                <a href="{{ route('transactions.index') }}" class="text-sm text-[#76a72b] font-semibold hover:underline">
                    Ver todos los movimientos →
                </a>
            </div>
        @endif
    </x-card>

    {{-- Chart.js --}}
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <script>
    const ctx = document.getElementById('flowChart');
    if (ctx) {
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: @json($chart['labels']),
                datasets: [
                    {
                        label: 'Ingresos',
                        data: @json($chart['incomes']),
                        backgroundColor: '#76a72b',
                        borderRadius: 6,
                        borderSkipped: false,
                    },
                    {
                        label: 'Egresos',
                        data: @json($chart['expenses']),
                        backgroundColor: '#ef4444',
                        borderRadius: 6,
                        borderSkipped: false,
                    },
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            boxWidth: 10,
                            boxHeight: 10,
                            borderRadius: 3,
                            useBorderRadius: true,
                            font: { size: 11, family: 'Roboto' },
                            color: '#878787',
                            padding: 12,
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: ctx => ' $' + ctx.parsed.y.toLocaleString('es-MX', {minimumFractionDigits: 2})
                        }
                    }
                },
                scales: {
                    x: {
                        grid: { display: false },
                        border: { display: false },
                        ticks: { font: { size: 11, family: 'Roboto' }, color: '#ababab' }
                    },
                    y: {
                        grid: { color: '#efeded' },
                        border: { display: false, dash: [4, 4] },
                        ticks: {
                            font: { size: 11, family: 'Roboto' },
                            color: '#ababab',
                            callback: v => '$' + (v >= 1000 ? (v/1000).toFixed(0) + 'k' : v)
                        }
                    }
                }
            }
        });
    }
    </script>

</x-app-layout>
