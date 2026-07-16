<x-app-layout title="Fuentes de ingreso">
    <div class="flex items-center justify-between mb-4">
        <h1 class="text-xl font-bold text-gray-900 dark:text-white">Fuentes de ingreso</h1>
        <a href="{{ route('sources.create') }}" class="inline-flex items-center gap-2 px-4 py-2 bg-[#76a72b] hover:bg-[#659220] text-white text-sm font-medium rounded-lg shadow-sm transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            Nueva fuente
        </a>
    </div>

    {{-- Links rápidos --}}
    <div class="flex gap-3 mb-4">
        <a href="{{ route('categories.index') }}" class="text-sm text-[#4a7018] dark:text-[#76a72b] hover:underline">Ver categorías →</a>
    </div>

    @if($sources->isEmpty())
        <div class="text-center py-16">
            <p class="text-gray-500 dark:text-gray-400">No hay fuentes de ingreso registradas.</p>
            <a href="{{ route('sources.create') }}" class="mt-3 inline-block text-[#4a7018] dark:text-[#76a72b] text-sm hover:underline">Crear primera fuente →</a>
        </div>
    @else
        @php $kindLabels = ['agency' => 'Agencia', 'university' => 'Universidad', 'training' => 'Capacitación', 'other' => 'Otro']; @endphp
        <div class="space-y-2">
            @foreach($sources as $source)
            <div class="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 p-4 flex items-center justify-between">
                <div>
                    <div class="flex items-center gap-2">
                        <span class="font-medium text-gray-900 dark:text-white">{{ $source->name }}</span>
                        @if($source->is_archived)
                            <span class="text-xs bg-gray-100 dark:bg-gray-800 text-gray-500 px-2 py-0.5 rounded-full">Archivada</span>
                        @endif
                    </div>
                    <span class="text-xs text-gray-500 dark:text-gray-400">{{ $kindLabels[$source->kind] ?? $source->kind }}</span>
                </div>
                <div class="flex items-center gap-2">
                    <a href="{{ route('sources.edit', $source) }}" class="p-2 text-[#ababab] hover:text-[#76a72b] rounded-lg hover:bg-[#76a72b]/10 transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                    </a>
                    <form method="POST" action="{{ route('sources.destroy', $source) }}" onsubmit="return confirm('¿Eliminar {{ addslashes($source->name) }}?')">
                        @csrf @method('DELETE')
                        <button type="submit" class="p-2 text-gray-400 hover:text-red-600 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                        </button>
                    </form>
                </div>
            </div>
            @endforeach
        </div>
    @endif
</x-app-layout>
