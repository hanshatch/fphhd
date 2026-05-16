<x-app-layout title="Movimientos">
    <div class="flex items-center justify-between mb-4">
        <h1 class="text-xl font-bold text-gray-900 dark:text-white">Movimientos</h1>
        <a href="{{ route('transactions.create') }}" class="hidden lg:inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-lg transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            Nuevo
        </a>
    </div>

    {{-- Filtros --}}
    <form method="GET" class="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 p-4 mb-4 space-y-3">
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
            <select name="account_id" class="rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 px-3 py-2 text-sm text-gray-900 dark:text-white col-span-2 sm:col-span-1">
                <option value="">Todas las cuentas</option>
                @foreach($accounts as $a)
                    <option value="{{ $a->id }}" {{ request('account_id') == $a->id ? 'selected' : '' }}>{{ $a->name }}</option>
                @endforeach
            </select>
            <select name="type" class="rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 px-3 py-2 text-sm text-gray-900 dark:text-white">
                <option value="">Todos los tipos</option>
                @foreach(['income' => 'Ingresos', 'expense' => 'Egresos', 'transfer' => 'Transferencias', 'interest' => 'Intereses', 'fee' => 'Comisiones'] as $val => $label)
                    <option value="{{ $val }}" {{ request('type') === $val ? 'selected' : '' }}>{{ $label }}</option>
                @endforeach
            </select>
            <input type="date" name="from" value="{{ request('from') }}"
                class="rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 px-3 py-2 text-sm text-gray-900 dark:text-white">
            <input type="date" name="to" value="{{ request('to') }}"
                class="rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 px-3 py-2 text-sm text-gray-900 dark:text-white">
        </div>
        <div class="flex gap-2">
            <input type="text" name="search" value="{{ request('search') }}" placeholder="Buscar en descripción..."
                class="flex-1 rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 px-3 py-2 text-sm text-gray-900 dark:text-white">
            <button type="submit" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm rounded-lg">Filtrar</button>
            @if(request()->hasAny(['account_id', 'type', 'from', 'to', 'search']))
                <a href="{{ route('transactions.index') }}" class="px-4 py-2 border border-gray-300 dark:border-gray-700 text-gray-600 dark:text-gray-400 text-sm rounded-lg hover:bg-gray-50 dark:hover:bg-gray-800">Limpiar</a>
            @endif
        </div>
    </form>

    {{-- Lista --}}
    @if($transactions->isEmpty())
        <div class="text-center py-16">
            <svg class="mx-auto w-12 h-12 text-gray-300 dark:text-gray-600 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/></svg>
            <p class="text-gray-500 dark:text-gray-400 font-medium">Sin movimientos</p>
            <a href="{{ route('transactions.create') }}" class="mt-3 inline-block text-indigo-600 text-sm hover:underline">Registra tu primer movimiento →</a>
        </div>
    @else
        @php
        $typeColors = ['income' => 'text-green-600 dark:text-green-400', 'interest' => 'text-green-600 dark:text-green-400', 'expense' => 'text-red-600 dark:text-red-400', 'fee' => 'text-red-600 dark:text-red-400', 'transfer' => 'text-gray-500 dark:text-gray-400'];
        $typeSign   = ['income' => '+', 'interest' => '+', 'expense' => '-', 'fee' => '-', 'transfer' => ''];
        $typeLabels = ['income' => 'Ingreso', 'expense' => 'Egreso', 'transfer' => 'Transferencia', 'interest' => 'Interés', 'fee' => 'Comisión'];
        @endphp

        <div class="space-y-2">
            @foreach($transactions as $tx)
            <div class="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 p-4 flex items-center gap-3">
                <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-2 flex-wrap">
                        <span class="font-medium text-gray-900 dark:text-white text-sm truncate">
                            {{ $tx->description ?: $typeLabels[$tx->type] }}
                        </span>
                        <span class="text-xs bg-gray-100 dark:bg-gray-800 text-gray-500 px-2 py-0.5 rounded-full flex-shrink-0">
                            {{ $tx->account->name }}
                        </span>
                    </div>
                    <div class="flex items-center gap-2 mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                        <span>{{ $tx->date->translatedFormat('d M Y') }}</span>
                        @if($tx->category) <span>· {{ $tx->category->name }}</span> @endif
                        @if($tx->source) <span>· {{ $tx->source->name }}</span> @endif
                        @if($tx->counterpartyAccount) <span>→ {{ $tx->counterpartyAccount->name }}</span> @endif
                    </div>
                </div>
                <div class="text-right flex-shrink-0">
                    <div class="font-semibold text-sm {{ $typeColors[$tx->type] }}">
                        {{ $typeSign[$tx->type] }}${{ number_format((float)$tx->amount, 2) }}
                    </div>
                </div>
                <div class="flex items-center gap-1 flex-shrink-0">
                    <a href="{{ route('transactions.edit', $tx) }}" class="p-2 text-gray-400 hover:text-indigo-600 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                    </a>
                    <form method="POST" action="{{ route('transactions.destroy', $tx) }}" onsubmit="return confirm('¿Eliminar este movimiento?')">
                        @csrf @method('DELETE')
                        <button type="submit" class="p-2 text-gray-400 hover:text-red-600 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                        </button>
                    </form>
                </div>
            </div>
            @endforeach
        </div>

        <div class="mt-4">
            {{ $transactions->links() }}
        </div>
    @endif
</x-app-layout>
