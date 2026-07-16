<x-app-layout title="Categorías">
    <div class="flex items-center justify-between mb-4">
        <h1 class="text-xl font-bold text-gray-900 dark:text-white">Categorías</h1>
        <a href="{{ route('categories.create') }}" class="inline-flex items-center gap-2 px-4 py-2 bg-[#76a72b] hover:bg-[#659220] text-white text-sm font-medium rounded-lg shadow-sm transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            Nueva
        </a>
    </div>

    @foreach(['income' => ['label' => 'Ingresos', 'list' => $income, 'color' => 'green'], 'expense' => ['label' => 'Egresos', 'list' => $expense, 'color' => 'red']] as $kind => $data)
    <div class="mb-6">
        <h2 class="text-sm font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-2">{{ $data['label'] }}</h2>
        @if($data['list']->isEmpty())
            <p class="text-sm text-gray-400 py-2">Sin categorías.</p>
        @else
            <div class="space-y-1">
                @foreach($data['list'] as $cat)
                <div class="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800">
                    <div class="flex items-center justify-between p-3">
                        <div class="flex items-center gap-2.5">
                            <div class="w-8 h-8 rounded-lg flex items-center justify-center text-white flex-shrink-0" style="background-color: {{ $cat->color }}">
                                <x-category-icon :name="$cat->icon" class="w-4 h-4" />
                            </div>
                            <span class="font-medium text-gray-900 dark:text-white text-sm">{{ $cat->name }}</span>
                            @if($cat->children->count()) <span class="text-xs text-gray-400">({{ $cat->children->count() }})</span> @endif
                        </div>
                        <div class="flex items-center gap-1">
                            <a href="{{ route('categories.edit', $cat) }}" class="p-1.5 text-[#ababab] hover:text-[#76a72b] rounded hover:bg-[#76a72b]/10">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                            </a>
                            <form method="POST" action="{{ route('categories.destroy', $cat) }}" onsubmit="return confirm('¿Eliminar {{ addslashes($cat->name) }}?')">
                                @csrf @method('DELETE')
                                <button type="submit" class="p-1.5 text-gray-400 hover:text-red-600 rounded hover:bg-gray-100 dark:hover:bg-gray-800">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                </button>
                            </form>
                        </div>
                    </div>
                    @if($cat->children->count())
                    <div class="border-t border-gray-100 dark:border-gray-800 pl-8 pr-3 py-1 space-y-1">
                        @foreach($cat->children as $child)
                        <div class="flex items-center justify-between py-1.5">
                            <div class="flex items-center gap-2">
                                <x-category-icon :name="$child->icon" class="w-3.5 h-3.5" style="color: {{ $child->color }}" />
                                <span class="text-sm text-gray-600 dark:text-gray-300">{{ $child->name }}</span>
                            </div>
                            <div class="flex items-center gap-1">
                                <a href="{{ route('categories.edit', $child) }}" class="p-1 text-[#ababab] hover:text-[#76a72b] rounded">
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                </a>
                                <form method="POST" action="{{ route('categories.destroy', $child) }}" onsubmit="return confirm('¿Eliminar {{ addslashes($child->name) }}?')">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="p-1 text-gray-400 hover:text-red-600 rounded">
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                    </button>
                                </form>
                            </div>
                        </div>
                        @endforeach
                    </div>
                    @endif
                </div>
                @endforeach
            </div>
        @endif
    </div>
    @endforeach
</x-app-layout>
