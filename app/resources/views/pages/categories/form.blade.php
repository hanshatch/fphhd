<x-app-layout :title="$category->exists ? 'Editar categoría' : 'Nueva categoría'">
    <div class="max-w-lg mx-auto">
        <x-page-header :title="$category->exists ? 'Editar categoría' : 'Nueva categoría'" :back="route('categories.index')" />

        @php
            $parentMeta = $parents->mapWithKeys(fn ($p) => [
                (string) $p->id => ['name' => $p->name, 'color' => $p->color, 'icon' => $p->icon, 'kind' => $p->kind],
            ]);
        @endphp

        <x-card class="p-6">
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
                <label class="block text-sm font-semibold text-[#373737] dark:text-white mb-1.5">Nombre <span class="text-positive">*</span></label>
                <input type="text" name="name" value="{{ old('name', $category->name) }}" required @unless($category->exists) autofocus @endunless
                    class="w-full rounded-xl border border-[#ababab]/40 bg-[#efeded]/50 dark:bg-white/5 px-4 py-3 text-[#373737] dark:text-white placeholder-[#ababab] focus:outline-none focus:ring-2 focus:ring-[#76a72b] transition">
                @error('name')<p class="mt-1.5 text-xs text-negative">{{ $message }}</p>@enderror
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-semibold text-[#373737] dark:text-white mb-1.5">Tipo <span class="text-positive">*</span></label>
                    <select name="kind" required x-model="kind" x-bind:disabled="!!inherited"
                        class="w-full rounded-xl border border-[#ababab]/40 bg-[#efeded]/50 dark:bg-white/5 px-4 py-3 text-[#373737] dark:text-white placeholder-[#ababab] focus:outline-none focus:ring-2 focus:ring-[#76a72b] transition disabled:opacity-60">
                        <option value="expense">Egreso</option>
                        <option value="income">Ingreso</option>
                    </select>
                    {{-- Deshabilitado no se envía: el tipo viaja aquí y el servidor lo re-fuerza --}}
                    <template x-if="inherited">
                        <input type="hidden" name="kind" x-bind:value="kind">
                    </template>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-[#373737] dark:text-white mb-1.5">Categoría padre</label>
                    <select name="parent_id" x-model="parent" x-on:change="applyParent()" class="w-full rounded-xl border border-[#ababab]/40 bg-[#efeded]/50 dark:bg-white/5 px-4 py-3 text-[#373737] dark:text-white placeholder-[#ababab] focus:outline-none focus:ring-2 focus:ring-[#76a72b] transition">
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
                <div class="flex items-center gap-3 rounded-xl border border-[#76a72b]/30 bg-[#76a72b]/5 px-4 py-3">
                    <span class="w-9 h-9 rounded-xl flex-shrink-0" x-bind:style="`background-color:${color}`"></span>
                    <p class="text-xs text-[#878787]">
                        Color, icono y tipo se heredan de
                        <span class="font-semibold text-[#373737] dark:text-white" x-text="inherited?.name"></span>,
                        para que la subcategoría se vea igual que su grupo.
                    </p>
                </div>
            </template>

            <div x-show="!inherited" x-cloak>
                <label class="block text-sm font-semibold text-[#373737] dark:text-white mb-1.5">Color</label>
                <input type="color" name="color" x-model="color"
                    class="h-11 w-16 rounded-xl border border-[#ababab]/40 cursor-pointer">
            </div>
            {{-- x-show solo oculta: el color heredado sí se envía --}}

            <input type="hidden" name="icon" x-model="icon">

            <div x-show="!inherited" x-cloak>
                <label class="block text-sm font-semibold text-[#373737] dark:text-white mb-1.5">Icono</label>
                @php
                    $iconOptions = ['tag', 'utensils', 'car', 'home', 'heart-pulse', 'graduation-cap', 'tv', 'shirt',
                        'laptop', 'landmark', 'briefcase', 'school', 'presentation', 'receipt', 'plane', 'gift',
                        'scissors', 'paw-print', 'users', 'ellipsis'];
                @endphp
                <div class="grid grid-cols-5 sm:grid-cols-10 gap-2">
                    @foreach($iconOptions as $opt)
                    <button type="button" data-no-spinner="true" x-on:click="icon = '{{ $opt }}'" aria-label="Icono {{ $opt }}" x-bind:aria-pressed="icon === '{{ $opt }}'"
                        class="h-11 rounded-xl border flex items-center justify-center transition-colors"
                        x-bind:class="icon === '{{ $opt }}'
                            ? 'border-[#76a72b] bg-[#76a72b]/10 text-positive'
                            : 'border-[#ababab]/40 text-[#878787] hover:border-[#76a72b] hover:text-positive'">
                        <x-category-icon :name="$opt" class="w-5 h-5" />
                    </button>
                    @endforeach
                </div>
            </div>

            <div class="flex gap-3 pt-2">
                <x-btn variant="secondary" href="{{ route('categories.index') }}" class="flex-1">Cancelar</x-btn>
                <x-btn type="submit" class="flex-1">{{ $category->exists ? 'Guardar' : 'Crear' }}</x-btn>
            </div>
        </form>
        </x-card>
    </div>
</x-app-layout>
