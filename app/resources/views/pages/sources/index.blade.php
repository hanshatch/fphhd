<x-app-layout title="Fuentes de ingreso">
    <x-page-header title="Fuentes de ingreso" :back="route('settings')"
        action-route="{{ route('sources.create') }}" action-label="Nueva fuente" />

    @if($sources->isEmpty())
        <x-card class="text-center py-16">
            <svg class="mx-auto w-10 h-10 text-[#ababab] mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
            <p class="text-[#878787] font-medium text-sm">No hay fuentes de ingreso registradas</p>
            <x-btn href="{{ route('sources.create') }}" class="mt-4 text-sm">Crear primera fuente</x-btn>
        </x-card>
    @else
        @php $kindLabels = ['agency' => 'Agencia', 'university' => 'Universidad', 'training' => 'Capacitación', 'other' => 'Otro']; @endphp
        <x-card class="max-w-3xl overflow-hidden divide-y divide-[#ababab]/10">
            @foreach($sources as $source)
            <div class="flex items-center gap-3 px-4 py-3 group hover:bg-[#f9f9f9] dark:hover:bg-white/5 transition-colors {{ $source->is_archived ? 'opacity-60' : '' }}">
                <div class="w-9 h-9 rounded-xl bg-[#76a72b]/15 flex items-center justify-center flex-shrink-0">
                    <span class="text-sm font-bold text-[#76a72b]">{{ mb_strtoupper(mb_substr($source->name, 0, 1)) }}</span>
                </div>
                <a href="{{ route('sources.edit', $source) }}" class="flex-1 min-w-0">
                    <span class="flex items-center gap-2">
                        <span class="text-sm font-semibold text-[#373737] dark:text-white truncate">{{ $source->name }}</span>
                        @if($source->is_archived)
                            <span class="text-[10px] bg-[#efeded] dark:bg-white/10 text-[#878787] px-1.5 py-0.5 rounded-full font-medium">Archivada</span>
                        @endif
                    </span>
                    <span class="block text-xs text-[#ababab]">{{ $kindLabels[$source->kind] ?? $source->kind }}</span>
                </a>
                <div class="row-actions flex items-center gap-0.5 flex-shrink-0">
                    <a href="{{ route('sources.edit', $source) }}" title="Editar"
                       class="w-8 h-8 flex items-center justify-center text-[#ababab] hover:text-[#76a72b] hover:bg-[#76a72b]/10 rounded-lg transition-colors">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                    </a>
                    <form method="POST" action="{{ route('sources.destroy', $source) }}" data-confirm="¿Eliminar «{{ $source->name }}»?" data-confirm-label="Eliminar">
                        @csrf @method('DELETE')
                        <button type="submit" title="Eliminar"
                            class="w-8 h-8 flex items-center justify-center text-[#ababab] hover:text-red-500 hover:bg-red-50 dark:hover:bg-red-500/10 rounded-lg transition-colors">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                        </button>
                    </form>
                </div>
            </div>
            @endforeach
        </x-card>
    @endif
</x-app-layout>
