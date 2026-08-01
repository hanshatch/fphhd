<x-app-layout title="Cuentas">

@php
$typeConfig = [
    'debit'      => ['label' => 'Cuentas bancarias',      'color' => '#76a72b', 'icon' => 'bank'],
    'savings'    => ['label' => 'Cajas de ahorro',         'color' => '#3b82f6', 'icon' => 'savings'],
    'investment' => ['label' => 'Inversiones',             'color' => '#8b5cf6', 'icon' => 'investment'],
    'cash'       => ['label' => 'Efectivo',                'color' => '#f97316', 'icon' => 'cash'],
    'credit'     => ['label' => 'Tarjetas de crédito',     'color' => '#ef4444', 'icon' => 'credit'],
];
$instLabels = [
    'banamex' => 'Banamex', 'klar' => 'Klar', 'mercadopago' => 'MercadoPago', 'nu' => 'Nu', 'openbank' => 'OpenBank',
    'revolut' => 'Revolut', 'amex' => 'American Express', 'efectivo' => 'Efectivo', 'other' => 'Otra',
];
@endphp

{{-- ── Header página ────────────────────────────────────────────── --}}
<x-page-header title="Cuentas"
    action-route="{{ route('accounts.create') }}"
    action-label="Nueva cuenta" />

{{-- ── Patrimonio neto ───────────────────────────────────────────── --}}
<div class="rounded-2xl p-5 mb-6" style="background:#373737">
    <p class="text-xs font-semibold text-white/50 uppercase tracking-wider mb-1">Patrimonio neto</p>
    <p class="text-3xl font-bold text-white tabular-nums">
        ${{ number_format((float)$netWorth, 2) }}
        <span class="text-base font-normal text-white/40 ml-1">MXN</span>
    </p>
</div>

@if($groups->isEmpty())
<x-card class="text-center py-16">
    <svg class="mx-auto w-12 h-12 text-[#ababab] mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/>
    </svg>
    <p class="text-[#878787] font-medium">Aún no tienes cuentas</p>
    <p class="text-[#ababab] text-sm mt-1">Agrega tus cuentas bancarias, cajas de ahorro y tarjetas</p>
    <x-btn href="{{ route('accounts.create') }}" class="mt-4">Crear primera cuenta</x-btn>
</x-card>
@else

<div class="space-y-4">
@foreach($groups as $type => $items)
@php
    $cfg   = $typeConfig[$type] ?? ['label' => $type, 'color' => '#878787', 'icon' => 'bank'];
    $total = $groupTotals[$type] ?? 0;
    $isCredit = $type === 'credit';
@endphp

{{-- ── Tarjeta de grupo ─────────────────────────────────────── --}}
<div class="rounded-2xl overflow-hidden shadow-sm border border-white/10">

    {{-- Header del grupo --}}
    <div class="px-4 py-3.5 flex items-center gap-3" style="background-color: {{ $cfg['color'] }}">

        {{-- Ícono del tipo --}}
        <div class="w-9 h-9 rounded-xl bg-white/20 flex items-center justify-center flex-shrink-0">
            @if($cfg['icon'] === 'bank')
            <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/>
            </svg>
            @elseif($cfg['icon'] === 'savings')
            <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/>
            </svg>
            @elseif($cfg['icon'] === 'investment')
            <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/>
            </svg>
            @elseif($cfg['icon'] === 'cash')
            <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2z"/>
            </svg>
            @else
            <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/>
            </svg>
            @endif
        </div>

        <div class="flex-1 min-w-0">
            <p class="text-white font-bold text-sm">{{ $cfg['label'] }}</p>
            <p class="text-white/70 text-xs tabular-nums">
                ${{ number_format(abs($total), 2) }} MXN
                @if($isCredit && $total > 0)
                <span class="text-white/50">deuda</span>
                @endif
            </p>
        </div>

        <a href="{{ route('accounts.create') }}"
           class="w-8 h-8 flex items-center justify-center rounded-full bg-white/20 hover:bg-white/30 transition-colors flex-shrink-0"
           title="Nueva cuenta">
            <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/>
            </svg>
        </a>
    </div>

    {{-- Cuentas del grupo --}}
    <div class="bg-white dark:bg-[#2a2a2a] divide-y divide-[#ababab]/10">
        @foreach($items as $item)
        @php $account = $item['account']; $balance = $item['balance']; @endphp

        <div class="flex items-center gap-3 px-4 py-3.5 cursor-pointer hover:bg-[#f9f9f9] dark:hover:bg-white/5 transition-colors group"
             onclick="window.location='{{ route('accounts.show', $account) }}'">

            {{-- Logo / inicial --}}
            <div class="w-10 h-10 rounded-xl flex-shrink-0 flex items-center justify-center overflow-hidden"
                 style="background-color: {{ $account->color ?? $cfg['color'] }}20">
                @if($account->logo_path)
                    <img src="{{ $account->logoUrl() }}" alt="{{ $account->name }}" class="w-7 h-7 object-contain">
                @else
                    <span class="font-bold text-sm" style="color: {{ $account->color ?? $cfg['color'] }}">
                        {{ mb_strtoupper(mb_substr($account->name, 0, 1)) }}
                    </span>
                @endif
            </div>

            {{-- Info --}}
            <div class="flex-1 min-w-0">
                @php
                    // Cajas de ahorro / inversión: tasa y tope a la vista
                    $showYield = in_array($account->type, ['savings', 'investment'], true) && $account->invest_apr;
                    $cap       = $account->invest_cap !== null ? (float) $account->invest_cap : null;
                    $overCap   = $cap && (float) $balance > $cap;
                @endphp
                <div class="flex items-center gap-2">
                    <span class="font-semibold text-[#373737] dark:text-white text-sm truncate">{{ $account->name }}</span>
                    @if($showYield)
                    <span class="text-[10px] bg-[#76a72b]/10 text-[#76a72b] px-1.5 py-0.5 rounded-full font-bold flex-shrink-0 tabular-nums">
                        {{ rtrim(rtrim(number_format((float) $account->invest_apr, 2), '0'), '.') }}%
                    </span>
                    @endif
                    @if(! $account->is_active)
                    <span class="text-[10px] bg-[#efeded] dark:bg-white/10 text-[#ababab] px-1.5 py-0.5 rounded-full font-medium flex-shrink-0">Inactiva</span>
                    @endif
                </div>
                <p class="text-[11px] text-[#ababab] mt-0.5">
                    {{ $instLabels[$account->institution] ?? $account->institution }}
                    @if($showYield && $cap)
                        · tope ${{ number_format($cap, 2) }}
                    @endif
                </p>
                @if($overCap)
                <p class="text-[11px] text-amber-500 font-semibold mt-0.5 tabular-nums">
                    ${{ number_format((float) $balance - $cap, 2) }} arriba del tope
                </p>
                @endif
            </div>

            {{-- Saldo --}}
            <div class="text-right flex-shrink-0">
                <p class="font-bold text-sm tabular-nums {{ $isCredit ? 'text-red-500' : 'text-[#373737] dark:text-white' }}">
                    ${{ number_format(abs((float)$balance), 2) }}
                </p>
                <p class="text-[10px] text-[#ababab] uppercase tracking-wider">MXN</p>
            </div>

            {{-- Acción editar (hover) --}}
            <a href="{{ route('accounts.edit', $account) }}"
               onclick="event.stopPropagation()"
               class="w-8 h-8 flex items-center justify-center text-[#ababab] hover:text-[#76a72b] hover:bg-[#76a72b]/10 rounded-lg transition-colors flex-shrink-0">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                </svg>
            </a>
        </div>
        @endforeach
    </div>

</div>{{-- /grupo --}}
@endforeach
</div>

@endif
</x-app-layout>
