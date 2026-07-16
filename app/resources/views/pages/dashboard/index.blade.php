<x-app-layout title="Panel">

    {{-- Saludo --}}
    <div class="mb-5">
        <h1 class="text-2xl font-bold text-[#373737] dark:text-white">
            Hola, <span class="text-[#76a72b]">Hans</span> 👋
        </h1>
        <p class="text-[#878787] text-sm mt-0.5">{{ now()->translatedFormat('l, d \d\e F \d\e Y') }}</p>
    </div>

    {{-- ── Fila 1: Patrimonio + Flujo del mes ─────────────────────── --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mb-4">

        {{-- Patrimonio neto --}}
        <div class="lg:col-span-1">
            <div class="p-5 bg-[#373737] rounded-2xl shadow-sm h-full flex flex-col justify-between">
                <div>
                    <p class="text-white/40 text-[10px] font-bold uppercase tracking-widest mb-2">Patrimonio neto</p>
                    <p class="text-3xl font-bold text-white leading-none">
                        ${{ number_format((float)$netWorth, 2) }}
                    </p>
                    <p class="text-white/30 text-xs mt-1">MXN</p>
                </div>
                <div class="flex gap-5 mt-4">
                    <div>
                        <p class="text-white/30 text-[10px] uppercase tracking-wider">Activos</p>
                        <p class="text-[#76a72b] font-bold text-sm">${{ number_format($assets, 2) }}</p>
                    </div>
                    <div>
                        <p class="text-white/30 text-[10px] uppercase tracking-wider">Tarjetas</p>
                        <p class="text-red-400 font-bold text-sm">${{ number_format($debts, 2) }}</p>
                    </div>
                </div>
            </div>
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
                        <div class="text-xl font-bold text-[#76a72b]">${{ number_format((float)$flow['income'], 2) }}</div>
                    </div>
                    <div class="text-center">
                        <div class="text-xs text-[#ababab] mb-1 uppercase tracking-wider">Egresos</div>
                        <div class="text-xl font-bold text-red-500">${{ number_format((float)$flow['expenses'], 2) }}</div>
                    </div>
                    <div class="text-center">
                        <div class="text-xs text-[#ababab] mb-1 uppercase tracking-wider">Neto</div>
                        <div class="text-xl font-bold {{ (float)$flow['net'] >= 0 ? 'text-[#76a72b]' : 'text-red-500' }}">
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

    {{-- ── FASE 7: Alertas TDC (corte/pago próximos) ──────────────── --}}
    @if($tdcAlerts->isNotEmpty())
    <div class="mb-4">
        <p class="text-xs font-bold text-[#878787] uppercase tracking-widest mb-2">Tarjetas de crédito</p>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
            @foreach($tdcAlerts as $tdc)
            @php
                $urgentStatement = $tdc['days_statement'] <= 3;
                $urgentPayment   = $tdc['days_payment'] <= 3;
                $warnStatement   = !$urgentStatement && $tdc['days_statement'] <= 7;
                $warnPayment     = !$urgentPayment   && $tdc['days_payment']   <= 7;
                $accColor        = $tdc['account']->color ?? '#ef4444';
            @endphp
            <div class="bg-white dark:bg-[#2a2a2a] rounded-2xl border border-[#ababab]/15 shadow-sm p-4">

                {{-- Header cuenta --}}
                <div class="flex items-center gap-3 mb-3">
                    <div class="w-9 h-9 rounded-xl flex items-center justify-center flex-shrink-0 overflow-hidden"
                         style="background-color: {{ $accColor }}20">
                        @if($tdc['account']->logo_path)
                        <img src="{{ $tdc['account']->logoUrl() }}" alt="" class="w-6 h-6 object-contain">
                        @else
                        <span class="font-bold text-sm" style="color: {{ $accColor }}">
                            {{ mb_strtoupper(mb_substr($tdc['account']->name, 0, 1)) }}
                        </span>
                        @endif
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="font-bold text-sm text-[#373737] dark:text-white truncate">{{ $tdc['account']->name }}</p>
                        <p class="text-xs text-[#ababab] tabular-nums">
                            ${{ number_format(abs((float)$tdc['balance']), 2) }}
                            @if($tdc['credit_limit'] > 0)
                            / ${{ number_format((float)$tdc['credit_limit'], 2) }}
                            @endif
                        </p>
                    </div>
                    @if($tdc['utilization'] > 0)
                    <div class="text-right flex-shrink-0">
                        <span class="text-xs font-bold {{ $tdc['utilization'] >= 80 ? 'text-red-500' : ($tdc['utilization'] >= 50 ? 'text-amber-500' : 'text-[#76a72b]') }}">
                            {{ $tdc['utilization'] }}%
                        </span>
                        <p class="text-[10px] text-[#ababab]">uso</p>
                    </div>
                    @endif
                </div>

                {{-- Barra de utilización --}}
                @if($tdc['credit_limit'] > 0)
                <div class="h-1.5 bg-[#efeded] dark:bg-white/10 rounded-full overflow-hidden mb-3">
                    <div class="h-full rounded-full transition-all"
                         style="width: {{ min($tdc['utilization'], 100) }}%;
                                background-color: {{ $tdc['utilization'] >= 80 ? '#ef4444' : ($tdc['utilization'] >= 50 ? '#f97316' : '#76a72b') }}">
                    </div>
                </div>
                @endif

                {{-- Fechas corte/pago --}}
                <div class="grid grid-cols-2 gap-2">
                    <div class="rounded-xl p-2 text-center
                        {{ $urgentStatement ? 'bg-red-50 dark:bg-red-500/10' : ($warnStatement ? 'bg-amber-50 dark:bg-amber-500/10' : 'bg-[#efeded] dark:bg-white/5') }}">
                        <p class="text-[10px] font-semibold uppercase tracking-wider
                            {{ $urgentStatement ? 'text-red-500' : ($warnStatement ? 'text-amber-500' : 'text-[#ababab]') }}">
                            Corte
                        </p>
                        <p class="text-sm font-bold
                            {{ $urgentStatement ? 'text-red-600' : ($warnStatement ? 'text-amber-600' : 'text-[#373737] dark:text-white') }}">
                            {{ $tdc['days_statement'] === 0 ? 'Hoy' : 'En '.$tdc['days_statement'].'d' }}
                        </p>
                        <p class="text-[10px] text-[#ababab]">{{ $tdc['next_statement']->translatedFormat('d M') }}</p>
                    </div>
                    <div class="rounded-xl p-2 text-center
                        {{ $urgentPayment ? 'bg-red-50 dark:bg-red-500/10' : ($warnPayment ? 'bg-amber-50 dark:bg-amber-500/10' : 'bg-[#efeded] dark:bg-white/5') }}">
                        <p class="text-[10px] font-semibold uppercase tracking-wider
                            {{ $urgentPayment ? 'text-red-500' : ($warnPayment ? 'text-amber-500' : 'text-[#ababab]') }}">
                            Pago
                        </p>
                        <p class="text-sm font-bold
                            {{ $urgentPayment ? 'text-red-600' : ($warnPayment ? 'text-amber-600' : 'text-[#373737] dark:text-white') }}">
                            {{ $tdc['days_payment'] === 0 ? 'Hoy' : 'En '.$tdc['days_payment'].'d' }}
                        </p>
                        <p class="text-[10px] text-[#ababab]">{{ $tdc['next_payment']->translatedFormat('d M') }}</p>
                    </div>
                </div>
            </div>
            @endforeach
        </div>
    </div>
    @endif

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
                            <span class="text-sm font-bold text-[#373737] dark:text-white">${{ number_format($cat['total'], 2) }}</span>
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

    {{-- ── FASE 9: Indicadores financieros ────────────────────────── --}}
    @php $ind = $indicators; @endphp
    <div class="mb-4">
        <p class="text-xs font-bold text-[#878787] uppercase tracking-widest mb-2">Salud financiera</p>
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">

            {{-- Tasa de ahorro --}}
            <div class="bg-white dark:bg-[#2a2a2a] rounded-2xl border border-[#ababab]/15 shadow-sm p-4">
                <p class="text-[10px] font-semibold text-[#ababab] uppercase tracking-wider mb-2">Tasa de ahorro</p>
                @if($ind['savings_rate'] !== null)
                @php $sr = $ind['savings_rate']; @endphp
                <p class="text-2xl font-bold tabular-nums {{ $sr >= 20 ? 'text-[#76a72b]' : ($sr >= 0 ? 'text-amber-500' : 'text-red-500') }}">
                    {{ $sr >= 0 ? '' : '-' }}{{ number_format(abs($sr), 1) }}<span class="text-sm">%</span>
                </p>
                <p class="text-[10px] text-[#ababab] mt-1">
                    {{ $sr >= 20 ? '¡Buen ritmo!' : ($sr >= 0 ? 'Puedes mejorar' : 'Gastando más de lo que ingresa') }}
                </p>
                @else
                <p class="text-sm text-[#ababab]">Sin ingresos aún</p>
                @endif
            </div>

            {{-- Proyección de gasto --}}
            <div class="bg-white dark:bg-[#2a2a2a] rounded-2xl border border-[#ababab]/15 shadow-sm p-4">
                <p class="text-[10px] font-semibold text-[#ababab] uppercase tracking-wider mb-2">Proyección del mes</p>
                <p class="text-2xl font-bold tabular-nums text-[#373737] dark:text-white">
                    ${{ number_format($ind['projected_expense'], 0) }}
                </p>
                <p class="text-[10px] text-[#ababab] mt-1">
                    Día {{ $ind['days_elapsed'] }}/{{ $ind['days_in_month'] }} ·
                    ${{ number_format($ind['daily_pace'], 0) }}/día
                </p>
            </div>

            {{-- Quincena disponible --}}
            <div class="bg-white dark:bg-[#2a2a2a] rounded-2xl border border-[#ababab]/15 shadow-sm p-4">
                <p class="text-[10px] font-semibold text-[#ababab] uppercase tracking-wider mb-2">Próximos 15 días</p>
                <p class="text-2xl font-bold tabular-nums {{ $ind['quincena_available'] >= 0 ? 'text-[#76a72b]' : 'text-red-500' }}">
                    ${{ number_format(abs($ind['quincena_available']), 0) }}
                </p>
                <p class="text-[10px] text-[#ababab] mt-1">
                    @if($ind['quincena_income'] > 0 || $ind['quincena_charges'] > 0)
                        +${{ number_format($ind['quincena_income'], 0) }} ingresos ·
                        -${{ number_format($ind['quincena_charges'], 0) }} cargos
                    @else
                        Sin movimientos programados
                    @endif
                </p>
            </div>

            {{-- Presupuestos --}}
            <div class="bg-white dark:bg-[#2a2a2a] rounded-2xl border border-[#ababab]/15 shadow-sm p-4">
                <p class="text-[10px] font-semibold text-[#ababab] uppercase tracking-wider mb-2">Presupuestos</p>
                @if($ind['budgets_at_risk'] > 0)
                <p class="text-2xl font-bold text-amber-500">{{ $ind['budgets_at_risk'] }}</p>
                <p class="text-[10px] text-amber-500 mt-1 font-semibold">
                    {{ $ind['budgets_at_risk'] === 1 ? 'categoría en riesgo' : 'categorías en riesgo' }}
                </p>
                <a href="{{ route('budgets.index') }}" class="text-[10px] text-[#76a72b] hover:underline font-semibold mt-1 block">
                    Ver presupuestos →
                </a>
                @else
                <p class="text-2xl font-bold text-[#76a72b]">✓</p>
                <p class="text-[10px] text-[#76a72b] mt-1 font-semibold">Todos en orden</p>
                @endif
            </div>

        </div>
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
                                <p class="text-sm font-semibold text-[#373737] dark:text-white truncate">{{ $acc->name }}</p>
                                <p class="text-[10px] text-[#ababab] capitalize">{{ $typeLabels[$acc->type] ?? $acc->type }} · {{ $acc->institution }}</p>
                            </div>
                            <div class="text-right">
                                <p class="font-bold text-sm {{ $acc->isCredit() ? 'text-red-500' : 'text-[#373737] dark:text-white' }}">
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
                    <div class="mb-4 p-3 bg-[#76a72b]/10 rounded-xl text-center">
                        <p class="text-xs text-[#76a72b] font-semibold">Total del mes</p>
                        <p class="text-xl font-bold text-[#76a72b]">{{ format_currency($interestTotal) }}</p>
                    </div>
                    <div class="space-y-3">
                        @foreach($monthlyInterest as $r)
                        <div class="flex items-center gap-2">
                            <div class="w-2.5 h-2.5 rounded-full flex-shrink-0" style="background-color: {{ $r['color'] }}"></div>
                            <span class="text-xs text-[#878787] flex-1 truncate">{{ $r['name'] }}</span>
                            <span class="text-xs font-bold text-[#76a72b]">${{ number_format($r['total'], 2) }}</span>
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
                        <p class="text-sm font-semibold text-[#373737] dark:text-white truncate">
                            {{ $tx->description ?: $c['label'] }}
                        </p>
                        <p class="text-xs text-[#ababab]">
                            {{ $tx->date->translatedFormat('d M Y') }}
                            · {{ $tx->account->name }}
                            @if($tx->category) · {{ $tx->category->name }} @endif
                        </p>
                    </div>
                    <span class="font-bold text-sm {{ $c['text'] }} flex-shrink-0">
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
