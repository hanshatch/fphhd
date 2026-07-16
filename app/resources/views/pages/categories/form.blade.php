<x-app-layout :title="$category->exists ? 'Editar categoría' : 'Nueva categoría'">
    <div class="max-w-lg mx-auto">
        <div class="flex items-center gap-3 mb-6">
            <a href="{{ route('categories.index') }}" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            </a>
            <h1 class="text-xl font-bold text-gray-900 dark:text-white">{{ $category->exists ? 'Editar categoría' : 'Nueva categoría' }}</h1>
        </div>

        <form method="POST" action="{{ $category->exists ? route('categories.update', $category) : route('categories.store') }}" class="space-y-5">
            @csrf
            @if($category->exists) @method('PATCH') @endif

            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Nombre *</label>
                <input type="text" name="name" value="{{ old('name', $category->name) }}" required
                    class="w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 px-3 py-2.5 text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-[#76a72b]">
                @error('name')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Tipo *</label>
                    <select name="kind" required class="w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 px-3 py-2.5 text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-[#76a72b]">
                        <option value="expense" {{ old('kind', $category->kind) === 'expense' ? 'selected' : '' }}>Egreso</option>
                        <option value="income"  {{ old('kind', $category->kind) === 'income'  ? 'selected' : '' }}>Ingreso</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Categoría padre</label>
                    <select name="parent_id" class="w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 px-3 py-2.5 text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-[#76a72b]">
                        <option value="">— Ninguna (raíz) —</option>
                        @foreach($parents as $p)
                            <option value="{{ $p->id }}" {{ old('parent_id', $category->parent_id) == $p->id ? 'selected' : '' }}>
                                {{ $p->name }} ({{ $p->kind === 'income' ? 'ingreso' : 'egreso' }})
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Color</label>
                <input type="color" name="color" value="{{ old('color', $category->color ?? '#6366f1') }}"
                    class="h-10 w-16 rounded-lg border border-gray-300 dark:border-gray-700 cursor-pointer">
            </div>

            <div x-data="{ icon: '{{ old('icon', $category->icon ?? 'tag') }}' }">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Icono</label>
                <input type="hidden" name="icon" x-model="icon">
                @php
                    $iconOptions = ['tag', 'utensils', 'car', 'home', 'heart-pulse', 'graduation-cap', 'tv', 'shirt',
                        'laptop', 'landmark', 'briefcase', 'school', 'presentation', 'receipt', 'plane', 'gift',
                        'scissors', 'paw-print', 'users', 'ellipsis'];
                @endphp
                <div class="grid grid-cols-5 sm:grid-cols-10 gap-2">
                    @foreach($iconOptions as $opt)
                    <button type="button" data-no-spinner="true" x-on:click="icon = '{{ $opt }}'"
                        class="h-11 rounded-lg border flex items-center justify-center transition-colors"
                        x-bind:class="icon === '{{ $opt }}'
                            ? 'border-[#76a72b] bg-[#76a72b]/10 text-[#76a72b]'
                            : 'border-gray-300 dark:border-gray-700 text-[#878787] hover:border-[#76a72b] hover:text-[#76a72b]'">
                        <x-category-icon :name="$opt" class="w-5 h-5" />
                    </button>
                    @endforeach
                </div>
            </div>

            <div class="flex gap-3 pt-2">
                <a href="{{ route('categories.index') }}" class="flex-1 text-center px-4 py-2.5 border border-gray-300 dark:border-gray-700 text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-800">Cancelar</a>
                <button type="submit" class="flex-1 px-4 py-2.5 bg-[#76a72b] hover:bg-[#659220] text-white font-medium rounded-lg shadow-sm transition-colors">{{ $category->exists ? 'Guardar' : 'Crear' }}</button>
            </div>
        </form>
    </div>
</x-app-layout>
