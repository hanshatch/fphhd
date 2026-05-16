<x-app-layout title="Cuentas">
    <div class="flex items-center justify-between mb-4">
        <h1 class="text-xl font-bold text-gray-900 dark:text-white">Cuentas</h1>
        <a href="{{ route('accounts.create') }}" class="inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-lg transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            Nueva cuenta
        </a>
    </div>

    @if($accounts->isEmpty())
        <div class="text-center py-16">
            <svg class="mx-auto w-12 h-12 text-gray-300 dark:text-gray-600 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg>
            <p class="text-gray-500 dark:text-gray-400 font-medium">No tienes cuentas aún</p>
            <a href="{{ route('accounts.create') }}" class="mt-3 inline-block text-indigo-600 dark:text-indigo-400 text-sm hover:underline">Crea tu primera cuenta →</a>
        </div>
    @else
        <div class="space-y-3">
            @foreach($accounts as $item)
                @php $account = $item['account']; $balance = $item['balance']; @endphp
                <div class="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 p-4 flex items-center gap-4">
                    <div class="w-10 h-10 rounded-full flex-shrink-0" style="background-color: {{ $account->color }}20; border: 2px solid {{ $account->color }}">
                        <div class="w-full h-full flex items-center justify-center">
                            <div class="w-3 h-3 rounded-full" style="background-color: {{ $account->color }}"></div>
                        </div>
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2">
                            <span class="font-medium text-gray-900 dark:text-white truncate">{{ $account->name }}</span>
                            @if(!$account->is_active)
                                <span class="text-xs bg-gray-100 dark:bg-gray-800 text-gray-500 px-2 py-0.5 rounded-full">Inactiva</span>
                            @endif
                        </div>
                        <span class="text-xs text-gray-500 dark:text-gray-400 capitalize">{{ $account->institution }} · {{ $account->type }}</span>
                    </div>
                    <div class="text-right">
                        <div class="font-semibold {{ $account->isCredit() ? 'text-red-600 dark:text-red-400' : 'text-gray-900 dark:text-white' }}">
                            ${{ number_format((float)$balance, 2) }}
                        </div>
                        <div class="text-xs text-gray-400">MXN</div>
                    </div>
                    <div class="flex items-center gap-2 flex-shrink-0">
                        <a href="{{ route('accounts.edit', $account) }}" class="p-2 text-gray-400 hover:text-indigo-600 dark:hover:text-indigo-400 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                        </a>
                        <form method="POST" action="{{ route('accounts.destroy', $account) }}" onsubmit="return confirm('¿Eliminar la cuenta {{ addslashes($account->name) }}?')">
                            @csrf @method('DELETE')
                            <button type="submit" class="p-2 text-gray-400 hover:text-red-600 dark:hover:text-red-400 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                            </button>
                        </form>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</x-app-layout>
