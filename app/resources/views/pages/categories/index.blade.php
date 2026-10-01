<x-app-layout title="Categorías">
    <x-page-header title="Categorías" :back="route('settings')"
        action-route="{{ route('categories.create') }}" action-label="Nueva" />

    {{-- En escritorio, ingresos y egresos lado a lado --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 lg:items-start">
    @foreach(['expense' => ['label' => 'Egresos', 'list' => $expense], 'income' => ['label' => 'Ingresos', 'list' => $income]] as $kind => $data)
    <section>
        <h2 class="text-xs font-bold text-[#878787] uppercase tracking-wider mb-2">
            {{ $data['label'] }} <span class="font-medium text-[#878787] tabular-nums">· {{ $data['list']->count() }}</span>
        </h2>
        @if($data['list']->isEmpty())
            <x-card class="text-center py-10">
                <p class="text-sm text-[#878787]">Sin categorías de {{ mb_strtolower($data['label']) }}.</p>
                <x-btn href="{{ route('categories.create') }}" variant="secondary" class="mt-3 text-sm">Crear categoría</x-btn>
            </x-card>
        @else
            <x-card class="overflow-hidden divide-y divide-[#ababab]/10">
                @foreach($data['list'] as $cat)
                <div>
                    <div class="flex items-center gap-3 px-4 py-3 group hover:bg-[#f9f9f9] dark:hover:bg-white/5 transition-colors">
                        <div class="w-9 h-9 rounded-xl flex items-center justify-center text-white flex-shrink-0" style="background-color: {{ $cat->color }}">
                            <x-category-icon :name="$cat->icon" class="w-4 h-4" />
                        </div>
                        <a href="{{ route('categories.edit', $cat) }}" class="flex-1 min-w-0 text-sm font-semibold text-[#373737] dark:text-white truncate hover:text-positive transition-colors">
                            {{ $cat->name }}
                            @if($cat->children->count())
                            <span class="text-xs font-normal text-[#878787] tabular-nums">· {{ $cat->children->count() }}</span>
                            @endif
                        </a>
                        <div class="row-actions flex items-center gap-0.5 flex-shrink-0">
                            <a href="{{ route('categories.edit', $cat) }}" title="Editar"
                               class="w-8 h-8 flex items-center justify-center text-[#878787] hover:text-positive hover:bg-[#76a72b]/10 rounded-lg transition-colors">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                            </a>
                            <form method="POST" action="{{ route('categories.destroy', $cat) }}" data-confirm="¿Eliminar «{{ $cat->name }}»?" data-confirm-label="Eliminar">
                                @csrf @method('DELETE')
                                <button type="submit" title="Eliminar"
                                    class="w-8 h-8 flex items-center justify-center text-[#878787] hover:text-negative hover:bg-red-50 dark:hover:bg-red-500/10 rounded-lg transition-colors">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                </button>
                            </form>
                        </div>
                    </div>
                    @foreach($cat->children as $child)
                    <div class="flex items-center gap-3 pl-[60px] pr-4 py-0.5 group hover:bg-[#f9f9f9] dark:hover:bg-white/5 transition-colors">
                        <span class="w-1.5 h-1.5 rounded-full flex-shrink-0" style="background-color: {{ $child->color }}"></span>
                        <a href="{{ route('categories.edit', $child) }}" class="flex-1 min-w-0 text-sm text-[#878787] dark:text-white/70 truncate hover:text-positive transition-colors">{{ $child->name }}</a>
                        <div class="row-actions flex items-center gap-0.5 flex-shrink-0">
                            <a href="{{ route('categories.edit', $child) }}" title="Editar"
                               class="w-8 h-8 flex items-center justify-center text-[#878787] hover:text-positive hover:bg-[#76a72b]/10 rounded-lg transition-colors">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                            </a>
                            <form method="POST" action="{{ route('categories.destroy', $child) }}" data-confirm="¿Eliminar «{{ $child->name }}»?" data-confirm-label="Eliminar">
                                @csrf @method('DELETE')
                                <button type="submit" title="Eliminar"
                                    class="w-8 h-8 flex items-center justify-center text-[#878787] hover:text-negative hover:bg-red-50 dark:hover:bg-red-500/10 rounded-lg transition-colors">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                </button>
                            </form>
                        </div>
                    </div>
                    @endforeach
                </div>
                @endforeach
            </x-card>
        @endif
    </section>
    @endforeach
    </div>
</x-app-layout>
