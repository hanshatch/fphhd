<x-app-layout title="Movimientos">

@php
$typeConfig = [
    'income'   => ['sign' => '+', 'color' => '#76a72b', 'label' => 'Ingreso'],
    'interest' => ['sign' => '+', 'color' => '#76a72b', 'label' => 'Interés'],
    'expense'  => ['sign' => '-', 'color' => '#ef4444', 'label' => 'Egreso'],
    'fee'      => ['sign' => '-', 'color' => '#ef4444', 'label' => 'Comisión'],
    'transfer' => ['sign' => '',  'color' => '#878787', 'label' => 'Transferencia'],
];

$now = now();
@endphp

{{-- ── Header ──────────────────────────────────────────────────── --}}
<x-page-header title="Movimientos"
    action-route="{{ route('transactions.create') }}"
    action-label="Nuevo" />

{{-- ── Búsqueda + filtros ───────────────────────────────────────── --}}
<form method="GET" class="mb-5">
    {{-- Barra de búsqueda --}}
    <div class="relative mb-3">
        <svg class="absolute left-3.5 top-1/2 -translate-y-1/2 w-4 h-4 text-[#ababab]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
        </svg>
        <input type="text" name="search" value="{{ request('search') }}"
               placeholder="Buscar movimiento…"
               class="w-full rounded-xl border border-[#ababab]/40 bg-white dark:bg-white/5 pl-10 pr-4 py-2.5 text-sm text-[#373737] dark:text-white placeholder-[#ababab] focus:outline-none focus:ring-2 focus:ring-[#76a72b] transition">
    </div>

    {{-- Filtros secundarios (colapsables en mobile) --}}
    <div x-data="{ open: {{ request()->hasAny(['account_id','type','from','to']) ? 'true' : 'false' }} }">
        <button type="button" x-on:click="open = !open"
                class="flex items-center gap-1.5 text-xs font-semibold text-[#878787] hover:text-[#76a72b] transition-colors mb-2">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2a1 1 0 01-.293.707L13 13.414V19a1 1 0 01-.553.894l-4 2A1 1 0 017 21v-7.586L3.293 6.707A1 1 0 013 6V4z"/>
            </svg>
            <span x-text="open ? 'Ocultar filtros' : 'Filtros'"></span>
        </button>

        <div x-show="open" x-cloak class="grid grid-cols-2 gap-2 sm:grid-cols-4 mb-2">
            <select name="account_id"
                class="rounded-xl border border-[#ababab]/40 bg-white dark:bg-white/5 px-3 py-2 text-sm text-[#373737] dark:text-white focus:outline-none focus:ring-2 focus:ring-[#76a72b]">
                <option value="">Todas las cuentas</option>
                @foreach($accounts as $a)
                <option value="{{ $a->id }}" {{ request('account_id') == $a->id ? 'selected' : '' }}>{{ $a->name }}</option>
                @endforeach
            </select>

            <select name="type"
                class="rounded-xl border border-[#ababab]/40 bg-white dark:bg-white/5 px-3 py-2 text-sm text-[#373737] dark:text-white focus:outline-none focus:ring-2 focus:ring-[#76a72b]">
                <option value="">Todos los tipos</option>
                @foreach(['income' => 'Ingresos', 'expense' => 'Egresos', 'transfer' => 'Transferencias', 'interest' => 'Intereses', 'fee' => 'Comisiones'] as $v => $l)
                <option value="{{ $v }}" {{ request('type') === $v ? 'selected' : '' }}>{{ $l }}</option>
                @endforeach
            </select>

            <input type="date" name="from" value="{{ request('from') }}"
                class="rounded-xl border border-[#ababab]/40 bg-white dark:bg-white/5 px-3 py-2 text-sm text-[#373737] dark:text-white focus:outline-none focus:ring-2 focus:ring-[#76a72b]">

            <input type="date" name="to" value="{{ request('to') }}"
                class="rounded-xl border border-[#ababab]/40 bg-white dark:bg-white/5 px-3 py-2 text-sm text-[#373737] dark:text-white focus:outline-none focus:ring-2 focus:ring-[#76a72b]">
        </div>

        <div x-show="open" x-cloak class="flex gap-2">
            <x-btn type="submit" class="text-xs py-1.5 px-4">Filtrar</x-btn>
            @if($hasFilter)
            <x-btn variant="secondary" href="{{ route('transactions.index') }}" class="text-xs py-1.5 px-4">Limpiar</x-btn>
            @endif
        </div>
    </div>
</form>

{{-- ── Lista agrupada por mes ───────────────────────────────────── --}}
@if($grouped->isEmpty())
<x-card class="text-center py-16">
    <svg class="mx-auto w-12 h-12 text-[#ababab] mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/>
    </svg>
    <p class="text-[#878787] font-medium">Sin movimientos</p>
    <p class="text-[#ababab] text-sm mt-1">Registra tu primer ingreso, egreso o transferencia</p>
    <x-btn href="{{ route('transactions.create') }}" class="mt-4">Registrar movimiento</x-btn>
</x-card>
@else

@foreach($grouped as $monthKey => $txs)
@php
    $monthCarbon = \Illuminate\Support\Carbon::createFromFormat('Y-m', $monthKey);
    $isCurrentMonth = $monthKey === $now->format('Y-m');
    $isLastMonth    = $monthKey === $now->copy()->subMonth()->format('Y-m');
    $monthLabel = $isCurrentMonth
        ? 'Este mes'
        : ($isLastMonth ? 'Mes anterior' : $monthCarbon->translatedFormat('F Y'));

    // Totales del mes
    $inSum  = $txs->whereIn('type', ['income','interest'])->sum(fn($t) => (float)$t->amount);
    $outSum = $txs->whereIn('type', ['expense','fee'])->sum(fn($t) => (float)$t->amount);
@endphp

{{-- Header de mes --}}
<div class="flex items-center justify-between mb-2 mt-5 first:mt-0">
    <h2 class="text-xs font-bold text-[#878787] uppercase tracking-wider">{{ $monthLabel }}</h2>
    <div class="flex items-center gap-3 text-xs font-semibold">
        @if($inSum > 0)
        <span class="text-[#76a72b]">+${{ number_format($inSum, 2) }}</span>
        @endif
        @if($outSum > 0)
        <span class="text-red-500">-${{ number_format($outSum, 2) }}</span>
        @endif
    </div>
</div>

{{-- Tarjeta del grupo --}}
<div class="bg-white dark:bg-[#2a2a2a] rounded-2xl overflow-hidden border border-[#ababab]/15 shadow-sm mb-1">
    @foreach($txs as $i => $tx)
    @php
        $cfg = $typeConfig[$tx->type] ?? $typeConfig['expense'];
        // Ícono de categoría
        if ($tx->category) {
            $iconBg    = $tx->category->color;
            $iconLabel = mb_strtoupper(mb_substr($tx->category->name, 0, 1));
        } else {
            $iconBg    = $cfg['color'];
            $iconLabel = $tx->type === 'transfer' ? '⇄' : $cfg['sign'];
        }
    @endphp
    <div class="flex items-center gap-3 px-4 py-3 {{ !$loop->last ? 'border-b border-[#ababab]/10' : '' }} hover:bg-[#f9f9f9] dark:hover:bg-white/5 transition-colors group">

        {{-- Ícono de categoría --}}
        <div class="w-10 h-10 rounded-[10px] flex items-center justify-center flex-shrink-0 text-white font-bold text-sm select-none"
             style="background-color: {{ $iconBg }}">
            @if($tx->category)
                <x-category-icon :name="$tx->category->icon" class="w-5 h-5" />
            @else
                {{ $iconLabel }}
            @endif
        </div>

        {{-- Descripción + meta --}}
        <div class="flex-1 min-w-0">
            <p class="text-sm font-semibold text-[#373737] dark:text-white truncate">
                {{ $tx->description ?: $cfg['label'] }}
            </p>
            <div class="flex items-center gap-1.5 mt-0.5 flex-wrap">
                <span class="text-[11px] text-[#ababab]">{{ $tx->date->translatedFormat('d M') }}</span>
                <span class="text-[#ababab]/60 text-[10px]">·</span>
                <span class="text-[11px] text-[#ababab]">{{ $tx->account->name }}</span>
                @if($tx->category)
                <span class="text-[#ababab]/60 text-[10px]">·</span>
                <span class="text-[11px] text-[#ababab]">{{ $tx->category->name }}</span>
                @endif
                @if($tx->counterpartyAccount)
                <span class="text-[#ababab]/60 text-[10px]">·</span>
                <span class="text-[11px] text-[#ababab]">→ {{ $tx->counterpartyAccount->name }}</span>
                @endif
            </div>
        </div>

        {{-- Monto --}}
        <div class="text-right flex-shrink-0">
            <p class="text-sm font-bold tabular-nums"
               style="color: {{ $cfg['color'] }}">
                {{ $cfg['sign'] }}${{ number_format((float)$tx->amount, 2) }}
            </p>
        </div>

        {{-- Acciones (visibles en hover desktop) --}}
        <div class="flex items-center gap-0.5 flex-shrink-0">
            {{-- Editar --}}
            <a href="{{ route('transactions.edit', $tx) }}"
               class="w-7 h-7 flex items-center justify-center text-[#ababab] hover:text-[#76a72b] hover:bg-[#76a72b]/10 rounded-lg transition-colors"
               title="Editar">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                </svg>
            </a>
            {{-- Duplicar --}}
            <form method="POST" action="{{ route('transactions.duplicate', $tx) }}">
                @csrf
                <button type="submit"
                    class="w-7 h-7 flex items-center justify-center text-[#ababab] hover:text-blue-500 hover:bg-blue-50 dark:hover:bg-blue-500/10 rounded-lg transition-colors"
                    title="Duplicar">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                    </svg>
                </button>
            </form>
            {{-- Eliminar --}}
            <form method="POST" action="{{ route('transactions.destroy', $tx) }}"
                  onsubmit="return confirm('¿Eliminar este movimiento?')">
                @csrf @method('DELETE')
                <button type="submit"
                    class="w-7 h-7 flex items-center justify-center text-[#ababab] hover:text-red-500 hover:bg-red-50 dark:hover:bg-red-500/10 rounded-lg transition-colors"
                    title="Eliminar">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                    </svg>
                </button>
            </form>
        </div>
    </div>
    @endforeach
</div>
@endforeach

@if(! $hasFilter)
<p class="text-center text-xs text-[#ababab] mt-6">
    Mostrando últimos 90 días ·
    <a href="{{ route('transactions.index') }}?from={{ now()->subYear()->format('Y-m-d') }}"
       class="text-[#76a72b] hover:underline">Ver más</a>
</p>
@endif

@endif

</x-app-layout>
