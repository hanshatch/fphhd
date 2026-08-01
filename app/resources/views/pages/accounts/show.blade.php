<x-app-layout :title="$account->name">

@php
$typeConfig = [
    'income'   => ['sign' => '+', 'color' => '#76a72b', 'label' => 'Ingreso'],
    'interest' => ['sign' => '+', 'color' => '#76a72b', 'label' => 'Interés'],
    'expense'  => ['sign' => '-', 'color' => '#ef4444', 'label' => 'Egreso'],
    'fee'      => ['sign' => '-', 'color' => '#ef4444', 'label' => 'Comisión'],
    'transfer' => ['sign' => '',  'color' => '#878787', 'label' => 'Transferencia'],
];

$isCredit   = $account->type === 'credit';
$balanceColor = $isCredit
    ? ((float)$balance > 0 ? '#ef4444' : '#76a72b')
    : ((float)$balance >= 0 ? '#76a72b' : '#ef4444');

$now = now();
@endphp

{{-- ── Header de cuenta ─────────────────────────────────────────── --}}
<div class="mb-5">
    <x-page-header :title="$account->name" :back="route('accounts.index')" />

    {{-- Card de saldo --}}
    <div class="rounded-2xl p-5 text-white mb-1"
         style="background-color: {{ $account->color ?? '#76a72b' }}">
        <div class="flex items-center gap-3 mb-3">
            @if($account->logo_path)
            <img src="{{ $account->logoUrl() }}" alt="{{ $account->name }}"
                 class="w-10 h-10 rounded-xl object-contain bg-white/20 p-1">
            @else
            <div class="w-10 h-10 rounded-xl bg-white/20 flex items-center justify-center font-bold text-lg">
                {{ mb_strtoupper(mb_substr($account->name, 0, 1)) }}
            </div>
            @endif
            <div>
                <p class="font-bold text-lg leading-tight">{{ $account->name }}</p>
                <p class="text-white/70 text-xs capitalize">{{ $account->type }}</p>
            </div>
            <a href="{{ route('accounts.edit', $account) }}"
               class="ml-auto w-8 h-8 flex items-center justify-center rounded-full bg-white/20 hover:bg-white/30 transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                </svg>
            </a>
        </div>
        <p class="text-white/70 text-xs mb-1">{{ $isCredit ? 'Saldo deudor' : 'Saldo disponible' }}</p>
        <p class="text-3xl font-bold tabular-nums">
            ${{ number_format(abs((float)$balance), 2) }}
            <span class="text-base font-normal text-white/60">MXN</span>
        </p>
    </div>

    {{-- Acciones rápidas --}}
    <div class="grid grid-cols-2 gap-2">
        <a href="{{ route('transactions.create') }}?account_id={{ $account->id }}"
           class="flex items-center justify-center gap-2 py-2.5 rounded-xl border-2 border-dashed border-[#ababab]/40 text-[#878787] hover:border-[#76a72b] hover:text-[#76a72b] transition-colors text-sm font-semibold">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/>
            </svg>
            Nuevo movimiento
        </a>
        <a href="{{ route('accounts.adjust.show', $account) }}"
           class="flex items-center justify-center gap-2 py-2.5 rounded-xl border-2 border-dashed border-[#ababab]/40 text-[#878787] hover:border-amber-500 hover:text-amber-500 transition-colors text-sm font-semibold">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4"/>
            </svg>
            Ajustar saldo
        </a>
    </div>
</div>

{{-- ── Modal de edición de movimiento ───────────────────────────── --}}
<div x-data="{
        open: false,
        loading: false,
        html: '',
        async edit(id) {
            this.open = true;
            this.loading = true;
            this.html = '';
            const url = '/transactions/' + id + '/edit-modal?redirect_to={{ urlencode(route('accounts.show', $account, false)) }}';
            const res = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            this.html = res.ok
                ? await res.text()
                : '<p class=\'text-sm text-red-500\'>No se pudo cargar el movimiento.</p>';
            this.loading = false;
        },
        close() { this.open = false; this.html = ''; }
     }"
     x-on:edit-tx.window="edit($event.detail)"
     x-on:close-tx-modal="close()"
     x-on:keydown.escape.window="close()"
     x-init="@if(request()->filled('edit')) edit({{ (int) request('edit') }}) @endif">

    <div x-show="open" x-cloak class="fixed inset-0 z-[60] flex items-end sm:items-center justify-center">
        <div class="absolute inset-0 bg-black/50" x-on:click="close()"></div>

        <div class="relative w-full sm:max-w-md bg-white dark:bg-[#2a2a2a] rounded-t-2xl sm:rounded-2xl shadow-2xl max-h-[92vh] sm:max-h-[85vh] flex flex-col"
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="translate-y-full sm:translate-y-4 sm:opacity-0"
             x-transition:enter-end="translate-y-0 opacity-100">

            <div class="flex items-center justify-between px-5 py-4 border-b border-[#ababab]/15">
                <h2 class="text-base font-bold text-[#373737] dark:text-white">Editar movimiento</h2>
                <button type="button" data-no-spinner="true" x-on:click="close()"
                    class="w-9 h-9 flex items-center justify-center rounded-full text-[#ababab] hover:text-[#373737] hover:bg-[#efeded] dark:hover:bg-white/10 transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>

            <div class="p-5 overflow-y-auto">
                <div x-show="loading" class="py-10 flex justify-center">
                    <svg class="w-6 h-6 animate-spin text-[#76a72b]" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                    </svg>
                </div>
                <div x-html="html"></div>
            </div>
        </div>
    </div>
</div>

{{-- ── Movimientos agrupados ────────────────────────────────────── --}}
@if($grouped->isEmpty())
<x-card class="text-center py-12">
    <p class="text-[#878787] font-medium text-sm">Sin movimientos en esta cuenta</p>
    <x-btn href="{{ route('transactions.create') }}?account_id={{ $account->id }}" class="mt-3 text-sm">
        Registrar primero
    </x-btn>
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

    $inSum  = $txs->whereIn('type', ['income','interest'])->sum(fn($t) => (float)$t->amount);
    $outSum = $txs->whereIn('type', ['expense','fee'])->sum(fn($t) => (float)$t->amount);
@endphp

<div class="flex items-center justify-between mb-2 mt-5 first:mt-0">
    <h2 class="text-xs font-bold text-[#878787] uppercase tracking-wider">{{ $monthLabel }}</h2>
    <div class="flex items-center gap-3 text-xs font-semibold">
        @if($inSum > 0)<span class="text-[#76a72b]">+${{ number_format($inSum, 2) }}</span>@endif
        @if($outSum > 0)<span class="text-red-500">-${{ number_format($outSum, 2) }}</span>@endif
    </div>
</div>

<div class="bg-white dark:bg-[#2a2a2a] rounded-2xl overflow-hidden border border-[#ababab]/15 shadow-sm mb-1">
    @foreach($txs as $tx)
    @php
        $isIncoming = $tx->type === 'transfer' && $tx->counterparty_account_id === $account->id;
        $cfg = $isIncoming
            ? ['sign' => '+', 'color' => '#878787', 'label' => 'Transferencia recibida']
            : ($typeConfig[$tx->type] ?? $typeConfig['expense']);
        if ($tx->category) {
            $iconBg    = $tx->category->color;
            $iconLabel = mb_strtoupper(mb_substr($tx->category->name, 0, 1));
        } else {
            $iconBg    = $cfg['color'];
            $iconLabel = $tx->type === 'transfer' ? '⇄' : $cfg['sign'];
        }
    @endphp
    <div class="flex items-center gap-3 px-4 py-3 {{ !$loop->last ? 'border-b border-[#ababab]/10' : '' }} hover:bg-[#f9f9f9] dark:hover:bg-white/5 transition-colors group">

        <div class="w-10 h-10 rounded-[10px] flex items-center justify-center flex-shrink-0 text-white font-bold text-sm select-none"
             style="background-color: {{ $iconBg }}">
            {{ $iconLabel }}
        </div>

        <div class="flex-1 min-w-0">
            <p class="text-sm font-semibold text-[#373737] dark:text-white truncate">
                {{ $tx->description ?: $cfg['label'] }}
            </p>
            <div class="flex items-center gap-1.5 mt-0.5 flex-wrap">
                <span class="text-[11px] text-[#ababab]">{{ $tx->date->translatedFormat('d M') }}</span>
                @if($tx->category)
                <span class="text-[#ababab]/60 text-[10px]">·</span>
                <span class="text-[11px] text-[#ababab]">{{ $tx->category->name }}</span>
                @endif
                @if($isIncoming)
                <span class="text-[#ababab]/60 text-[10px]">·</span>
                <span class="text-[11px] text-[#ababab]">← {{ $tx->account->name }}</span>
                @elseif($tx->counterpartyAccount)
                <span class="text-[#ababab]/60 text-[10px]">·</span>
                <span class="text-[11px] text-[#ababab]">→ {{ $tx->counterpartyAccount->name }}</span>
                @endif
            </div>
        </div>

        {{-- Monto + saldo corrido --}}
        <div class="text-right flex-shrink-0 min-w-[80px]">
            <p class="text-sm font-bold tabular-nums" style="color: {{ $cfg['color'] }}">
                {{ $cfg['sign'] }}${{ number_format((float)$tx->amount, 2) }}
            </p>
            @if(isset($runningBalances[$tx->id]))
            @php $rb = (float) $runningBalances[$tx->id]; @endphp
            <p class="text-[10px] tabular-nums mt-0.5 {{ $rb < 0 ? 'text-red-400' : 'text-[#ababab]' }}">
                ${{ number_format($rb, 2) }}
            </p>
            @endif
        </div>

        {{-- Siempre visibles: en táctil (incluido iPad, que es pantalla ancha)
             no hay hover, así que ocultarlas las volvía inalcanzables --}}
        <div class="flex items-center gap-0.5 flex-shrink-0" x-data>
            <button type="button" data-no-spinner="true"
               x-on:click="$dispatch('edit-tx', {{ $tx->id }})"
               class="w-7 h-7 flex items-center justify-center text-[#ababab] hover:text-[#76a72b] hover:bg-[#76a72b]/10 rounded-lg transition-colors" title="Editar">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                </svg>
            </button>
            <form method="POST" action="{{ route('transactions.duplicate', $tx) }}">
                @csrf
                <input type="hidden" name="redirect_to" value="{{ route('accounts.show', $account, false) }}">
                <button type="submit"
                    class="w-7 h-7 flex items-center justify-center text-[#ababab] hover:text-blue-500 hover:bg-blue-50 dark:hover:bg-blue-500/10 rounded-lg transition-colors" title="Duplicar">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                    </svg>
                </button>
            </form>
            <form method="POST" action="{{ route('transactions.destroy', $tx) }}"
                  onsubmit="return confirm('¿Eliminar este movimiento?')">
                @csrf @method('DELETE')
                <input type="hidden" name="redirect_to" value="{{ route('accounts.show', $account, false) }}">
                <button type="submit"
                    class="w-7 h-7 flex items-center justify-center text-[#ababab] hover:text-red-500 hover:bg-red-50 dark:hover:bg-red-500/10 rounded-lg transition-colors" title="Eliminar">
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

@endif
</x-app-layout>
