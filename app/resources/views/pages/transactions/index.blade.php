<x-app-layout title="Movimientos">
    <x-page-header title="Movimientos" action-route="{{ route('transactions.create') }}" action-label="Nuevo" />

    {{-- Filtros --}}
    <x-card class="p-4 mb-4">
        <form method="GET" class="space-y-3">
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                <select name="account_id"
                    class="rounded-xl border border-[#ababab]/40 bg-[#efeded]/50 dark:bg-white/5 px-3 py-2.5 text-sm text-[#373737] dark:text-white col-span-2 sm:col-span-1 focus:outline-none focus:ring-2 focus:ring-[#76a72b]">
                    <option value="">Todas las cuentas</option>
                    @foreach($accounts as $a)
                        <option value="{{ $a->id }}" {{ request('account_id') == $a->id ? 'selected' : '' }}>{{ $a->name }}</option>
                    @endforeach
                </select>
                <select name="type"
                    class="rounded-xl border border-[#ababab]/40 bg-[#efeded]/50 dark:bg-white/5 px-3 py-2.5 text-sm text-[#373737] dark:text-white focus:outline-none focus:ring-2 focus:ring-[#76a72b]">
                    <option value="">Todos los tipos</option>
                    @foreach(['income' => 'Ingresos', 'expense' => 'Egresos', 'transfer' => 'Transferencias', 'interest' => 'Intereses', 'fee' => 'Comisiones'] as $val => $label)
                        <option value="{{ $val }}" {{ request('type') === $val ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
                <input type="date" name="from" value="{{ request('from') }}"
                    class="rounded-xl border border-[#ababab]/40 bg-[#efeded]/50 dark:bg-white/5 px-3 py-2.5 text-sm text-[#373737] dark:text-white focus:outline-none focus:ring-2 focus:ring-[#76a72b]">
                <input type="date" name="to" value="{{ request('to') }}"
                    class="rounded-xl border border-[#ababab]/40 bg-[#efeded]/50 dark:bg-white/5 px-3 py-2.5 text-sm text-[#373737] dark:text-white focus:outline-none focus:ring-2 focus:ring-[#76a72b]">
            </div>
            <div class="flex gap-2">
                <input type="text" name="search" value="{{ request('search') }}" placeholder="Buscar en descripción..."
                    class="flex-1 rounded-xl border border-[#ababab]/40 bg-[#efeded]/50 dark:bg-white/5 px-3 py-2.5 text-sm text-[#373737] dark:text-white placeholder-[#ababab] focus:outline-none focus:ring-2 focus:ring-[#76a72b]">
                <x-btn type="submit">Filtrar</x-btn>
                @if(request()->hasAny(['account_id', 'type', 'from', 'to', 'search']))
                    <x-btn variant="secondary" href="{{ route('transactions.index') }}">Limpiar</x-btn>
                @endif
            </div>
        </form>
    </x-card>

    @if($transactions->isEmpty())
    <x-card class="text-center py-16">
        <svg class="mx-auto w-12 h-12 text-[#ababab] mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/></svg>
        <p class="text-[#878787] font-medium">Sin movimientos</p>
        <p class="text-[#ababab] text-sm mt-1">Registra tu primer ingreso, egreso o transferencia</p>
        <x-btn href="{{ route('transactions.create') }}" class="mt-4">Registrar movimiento</x-btn>
    </x-card>
    @else

    @php
    $typeConfig = [
        'income'   => ['color' => 'text-[#76a72b]',   'bg' => 'bg-[#76a72b]/10',  'sign' => '+', 'label' => 'Ingreso'],
        'interest' => ['color' => 'text-[#76a72b]',   'bg' => 'bg-[#76a72b]/10',  'sign' => '+', 'label' => 'Interés'],
        'expense'  => ['color' => 'text-red-500',      'bg' => 'bg-red-50',         'sign' => '-', 'label' => 'Egreso'],
        'fee'      => ['color' => 'text-red-500',      'bg' => 'bg-red-50',         'sign' => '-', 'label' => 'Comisión'],
        'transfer' => ['color' => 'text-[#878787]',   'bg' => 'bg-[#efeded]',      'sign' => '',  'label' => 'Transferencia'],
    ];
    @endphp

    <div class="space-y-2">
        @foreach($transactions as $tx)
        @php $cfg = $typeConfig[$tx->type] ?? $typeConfig['expense']; @endphp
        <x-card class="p-4 flex items-center gap-3 hover:shadow-md transition-shadow">
            {{-- Indicador de tipo --}}
            <div class="w-10 h-10 rounded-xl {{ $cfg['bg'] }} flex items-center justify-center flex-shrink-0 text-sm font-bold {{ $cfg['color'] }}">
                {{ $cfg['sign'] ?: '⇄' }}
            </div>

            {{-- Descripción --}}
            <div class="flex-1 min-w-0">
                <div class="flex items-center gap-2 flex-wrap">
                    <span class="font-semibold text-[#373737] dark:text-white text-sm truncate font-['Nunito']">
                        {{ $tx->description ?: $cfg['label'] }}
                    </span>
                    <span class="text-[10px] bg-[#efeded] dark:bg-white/10 text-[#878787] px-2 py-0.5 rounded-full flex-shrink-0 font-medium">
                        {{ $tx->account->name }}
                    </span>
                </div>
                <div class="flex items-center gap-1.5 mt-0.5 text-xs text-[#ababab]">
                    <span>{{ $tx->date->translatedFormat('d M Y') }}</span>
                    @if($tx->category) <span>·</span><span>{{ $tx->category->name }}</span> @endif
                    @if($tx->source)   <span>·</span><span>{{ $tx->source->name }}</span>   @endif
                    @if($tx->counterpartyAccount) <span>→ {{ $tx->counterpartyAccount->name }}</span> @endif
                </div>
            </div>

            {{-- Monto --}}
            <div class="font-bold text-sm {{ $cfg['color'] }} text-right flex-shrink-0 font-['Nunito']">
                {{ $cfg['sign'] }}${{ number_format((float)$tx->amount, 2) }}
            </div>

            {{-- Acciones --}}
            <div class="flex items-center gap-1 flex-shrink-0">
                <a href="{{ route('transactions.edit', $tx) }}"
                   class="w-8 h-8 flex items-center justify-center text-[#ababab] hover:text-[#76a72b] hover:bg-[#76a72b]/10 rounded-lg transition-colors">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                </a>
                <form method="POST" action="{{ route('transactions.destroy', $tx) }}"
                      onsubmit="return confirm('¿Eliminar este movimiento?')">
                    @csrf @method('DELETE')
                    <button type="submit" class="w-8 h-8 flex items-center justify-center text-[#ababab] hover:text-red-500 hover:bg-red-50 dark:hover:bg-red-500/10 rounded-lg transition-colors">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                    </button>
                </form>
            </div>
        </x-card>
        @endforeach
    </div>

    <div class="mt-4 [&_.pagination]:flex [&_.pagination]:gap-1">
        {{ $transactions->links() }}
    </div>
    @endif
</x-app-layout>
