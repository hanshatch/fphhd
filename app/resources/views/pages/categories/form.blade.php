<x-app-layout :title="$category->exists ? 'Editar categoría' : 'Nueva categoría'">
    <div class="max-w-lg mx-auto">
        <div class="flex items-center gap-3 mb-6">
            <a href="{{ route('categories.index') }}" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            </a>
            <h1 class="text-xl font-bold text-gray-900 dark:text-white">{{ $category->exists ? 'Editar categoría' : 'Nueva categoría' }}</h1>
        </div>

        @php
            $parentMeta = $parents->mapWithKeys(fn ($p) => [
                (string) $p->id => ['name' => $p->name, 'color' => $p->color, 'icon' => $p->icon, 'kind' => $p->kind],
            ]);
        @endphp

        <form method="POST" action="{{ $category->exists ? route('categories.update', $category) : route('categories.store') }}" class="space-y-5"
            x-data="{
                parents: {{ Illuminate\Support\Js::from($parentMeta) }},
                parent: '{{ old('parent_id', $category->parent_id) }}',
                color: '{{ old('color', $category->color ?? '#6366f1') }}',
                icon: '{{ old('icon', $category->icon ?? 'tag') }}',
                kind: '{{ old('kind', $category->kind ?? 'expense') }}',
                get inherited() { return this.parents[this.parent] ?? null; },
                applyParent() {
                    const p = this.inherited;
                    if (!p) return;
                    this.color = p.color;
                    this.icon  = p.icon;
                    this.kind  = p.kind;
                }
            }">
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
                    <select name="kind" required x-model="kind" x-bind:disabled="!!inherited"
                        class="w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 px-3 py-2.5 text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-[#76a72b] disabled:opacity-60">
                        <option value="expense">Egreso</option>
                        <option value="income">Ingreso</option>
                    </select>
                    {{-- Deshabilitado no se envía: el tipo viaja aquí y el servidor lo re-fuerza --}}
                    <template x-if="inherited">
                        <input type="hidden" name="kind" x-bind:value="kind">
                    </template>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Categoría padre</label>
                    <select name="parent_id" x-model="parent" x-on:change="applyParent()" class="w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 px-3 py-2.5 text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-[#76a72b]">
                        <option value="">— Ninguna (raíz) —</option>
                        @foreach($parents as $p)
                            <option value="{{ $p->id }}">
                                {{ $p->name }} ({{ $p->kind === 'income' ? 'ingreso' : 'egreso' }})
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>

            {{-- Heredado del padre: se muestra, no se elige --}}
            <template x-if="inherited">
                <div class="flex items-center gap-3 rounded-lg border border-[#76a72b]/30 bg-[#76a72b]/5 px-4 py-3">
                    <span class="w-9 h-9 rounded-xl flex-shrink-0" x-bind:style="`background-color:${color}`"></span>
                    <p class="text-xs text-[#878787]">
                        Color, icono y tipo se heredan de
                        <span class="font-semibold text-[#373737] dark:text-white" x-text="inherited?.name"></span>,
                        para que la subcategoría se vea igual que su grupo.
                    </p>
                </div>
            </template>

            <div x-show="!inherited" x-cloak>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Color</label>
                <input type="color" name="color" x-model="color"
                    class="h-10 w-16 rounded-lg border border-gray-300 dark:border-gray-700 cursor-pointer">
            </div>
            {{-- x-show solo oculta: el color heredado sí se envía --}}

            <input type="hidden" name="icon" x-model="icon">

            <div x-show="!inherited" x-cloak>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Icono</label>
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
