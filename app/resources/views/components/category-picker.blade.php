{{--
    Teclado: ↓/↑ recorren las opciones visibles y Enter en la búsqueda elige
    la primera coincidencia. Esc cierra solo el selector, no el modal de atrás.

    kindExpr: expresión Alpine (del scope padre) que devuelve 'expense' o
    'income' para mostrar solo las categorías de ese tipo, ej. kind-expr="type".
--}}
@props(['categories', 'selected' => null, 'name' => 'category_id', 'placeholder' => 'Sin categoría', 'variant' => 'default', 'disabledExpr' => null, 'kindExpr' => null])

@php
    $flat = $categories->flatMap(fn ($c) => collect([$c])->merge($c->children ?? collect()));
    $current = old($name, $selected);
    $selectedCat = $current ? $flat->firstWhere('id', (int) $current) : null;
    $parents = $categories->whereNull('parent_id');
    $kindLabels = ['expense' => 'Egresos', 'income' => 'Ingresos'];
@endphp

<div @if($variant === 'row') class="contents" @endif x-data="{
        open: false,
        value: '{{ $current }}',
        label: @js($selectedCat?->name),
        color: @js($selectedCat?->color),
        q: '',
        norm(s) { return (s || '').toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, ''); },
        matches(hay) { return this.q === '' || this.norm(hay).includes(this.norm(this.q)); },
        pick(id, name, color) { this.value = id; this.label = name; this.color = color; this.open = false; this.q = ''; this.$nextTick(() => this.$refs.trigger.focus()); },
        show() { this.q = ''; this.open = true; this.$nextTick(() => this.$refs.search.focus()); },
        options() { return [...this.$refs.list.querySelectorAll('button')].filter(b => b.offsetParent !== null); },
        move(step) {
            const opts = this.options(), i = opts.indexOf(document.activeElement);
            const next = opts[i + step];
            next ? next.focus() : (step < 0 && this.$refs.search.focus());
        },
        pickFirst() {
            if (this.q === '') return;
            this.options().find(b => !b.hasAttribute('data-none'))?.click();
        }
    }"
    x-on:keydown.escape.window="open = false">

    <input type="hidden" name="{{ $name }}" x-model="value" {{ $attributes->only('form') }}
        @if($disabledExpr) x-bind:disabled="{{ $disabledExpr }}" @endif>

    {{-- Trigger --}}
    @if($variant === 'row')
    <button type="button" data-no-spinner="true" x-ref="trigger" x-on:click="show()"
        {{ $attributes->except('form')->merge(['class' => 'tx-row-value flex items-center justify-end gap-1.5']) }}>
        <template x-if="label">
            <span class="w-2 h-2 rounded-full flex-shrink-0" x-bind:style="`background-color: ${color || '#76a72b'}`"></span>
        </template>
        <span class="truncate" x-text="label || '{{ $placeholder }}'"></span>
        <svg class="w-3.5 h-3.5 text-[#878787] flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
    </button>
    @else
    <button type="button" data-no-spinner="true" x-ref="trigger" x-on:click="show()"
        {{ $attributes->except('form')->merge(['class' => 'w-full rounded-xl border border-[#ababab]/40 bg-[#efeded]/50 dark:bg-white/5 px-4 py-3 text-left flex items-center gap-2.5 focus:outline-none focus:ring-2 focus:ring-[#76a72b] transition']) }}>
        <template x-if="label">
            <span class="w-2.5 h-2.5 rounded-full flex-shrink-0" x-bind:style="`background-color: ${color || '#76a72b'}`"></span>
        </template>
        <span class="flex-1 truncate text-[#373737] dark:text-white" x-bind:class="label ? '' : 'text-[#878787]'"
              x-text="label || '{{ $placeholder }}'"></span>
        <svg class="w-4 h-4 text-[#878787] flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
    </button>
    @endif

    {{-- Modal --}}
    <div x-show="open" x-cloak x-transition:leave="transition duration-150" x-on:keydown.escape.stop="open = false"
         role="dialog" aria-modal="true" aria-label="Elegir categoría"
         class="fixed inset-0 z-50 flex items-end sm:items-center justify-center">
        <div class="absolute inset-0 bg-black/50" x-on:click="open = false" x-show="open" x-transition:enter="transition-opacity ease-snappy duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" x-transition:leave="transition-opacity ease-snappy duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"></div>

        <div class="relative w-full sm:max-w-md bg-white dark:bg-[#2a2a2a] rounded-t-2xl sm:rounded-2xl shadow-2xl max-h-[85vh] sm:max-h-[70vh] flex flex-col"
             x-show="open" x-transition:enter="transition ease-snappy duration-200" x-transition:enter-start="translate-y-full sm:translate-y-0 sm:scale-[0.96] sm:opacity-0" x-transition:enter-end="translate-y-0 sm:scale-100 sm:opacity-100" x-transition:leave="transition ease-snappy duration-150" x-transition:leave-start="translate-y-0 sm:scale-100 sm:opacity-100" x-transition:leave-end="translate-y-full sm:translate-y-0 sm:scale-[0.98] sm:opacity-0">

            {{-- Search --}}
            <div class="p-4 border-b border-[#ababab]/15">
                <div class="relative">
                    <svg class="absolute left-3.5 top-1/2 -translate-y-1/2 w-4 h-4 text-[#878787]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    <input type="text" x-ref="search" x-model="q" placeholder="Buscar categoría…"
                        x-on:keydown.enter.prevent="pickFirst()" x-on:keydown.down.prevent="move(1)"
                        class="w-full rounded-xl border border-[#ababab]/40 bg-[#efeded]/50 dark:bg-white/5 pl-10 pr-4 py-2.5 text-sm text-[#373737] dark:text-white placeholder-[#ababab] focus:outline-none focus:ring-2 focus:ring-[#76a72b] transition">
                </div>
            </div>

            {{-- Lista --}}
            <div class="flex-1 overflow-y-auto p-2 pb-6" x-ref="list"
                 x-on:keydown.down.prevent="move(1)" x-on:keydown.up.prevent="move(-1)">
                <button type="button" data-no-spinner="true" data-none x-on:click="pick('', null, null)" x-show="matches('sin categoria')"
                    class="w-full flex items-center gap-3 px-3 py-2.5 rounded-lg hover:bg-[#efeded] dark:hover:bg-white/5 focus:bg-[#efeded] dark:focus:bg-white/5 focus:outline-none text-left transition-colors">
                    <span class="w-7 h-7 rounded-lg bg-[#efeded] dark:bg-white/10 flex items-center justify-center text-[#878787] flex-shrink-0 text-xs">—</span>
                    <span class="text-sm text-[#878787]">{{ $placeholder }}</span>
                </button>

                @foreach($kindLabels as $kind => $kindLabel)
                    @php
                        $kindParents = $parents->where('kind', $kind);
                        $kindGuard   = $kindExpr ? "({$kindExpr}) === '{$kind}' && " : '';
                    @endphp
                    @if($kindParents->isNotEmpty())
                    <p class="px-3 pt-4 pb-1 text-[11px] font-bold text-[#878787] uppercase tracking-widest"
                       x-show="{{ $kindGuard }}[@foreach($kindParents as $cat)'{{ addslashes($cat->name) }}',@foreach($cat->children as $child)'{{ addslashes($child->name) }}',@endforeach @endforeach].some(n => matches(n))">
                        {{ $kindLabel }}
                    </p>
                    @foreach($kindParents as $cat)
                    <div x-show="{{ $kindGuard }}[@foreach(collect([$cat])->merge($cat->children) as $c)'{{ addslashes($c->name) }}',@endforeach].some(n => matches(n))">
                        <button type="button" data-no-spinner="true"
                            x-on:click="pick('{{ $cat->id }}', @js($cat->name), '{{ $cat->color }}')"
                            x-show="matches(@js($cat->name))"
                            class="w-full flex items-center gap-3 px-3 py-2.5 rounded-lg hover:bg-[#efeded] dark:hover:bg-white/5 focus:bg-[#efeded] dark:focus:bg-white/5 focus:outline-none text-left transition-colors"
                            x-bind:class="value == '{{ $cat->id }}' ? 'bg-[#76a72b]/10' : ''">
                            <span class="w-7 h-7 rounded-lg flex items-center justify-center text-white flex-shrink-0" style="background-color: {{ $cat->color }}">
                                <x-category-icon :name="$cat->icon" class="w-3.5 h-3.5" />
                            </span>
                            <span class="text-sm font-semibold text-[#373737] dark:text-white">{{ $cat->name }}</span>
                        </button>
                        @foreach($cat->children as $child)
                        <button type="button" data-no-spinner="true"
                            x-on:click="pick('{{ $child->id }}', @js($child->name), '{{ $child->color }}')"
                            x-show="matches(@js($child->name)) || matches(@js($cat->name))"
                            class="w-full flex items-center gap-3 pl-8 pr-3 py-2 rounded-lg hover:bg-[#efeded] dark:hover:bg-white/5 focus:bg-[#efeded] dark:focus:bg-white/5 focus:outline-none text-left transition-colors"
                            x-bind:class="value == '{{ $child->id }}' ? 'bg-[#76a72b]/10' : ''">
                            <span class="w-1.5 h-1.5 rounded-full flex-shrink-0" style="background-color: {{ $child->color }}"></span>
                            <span class="text-sm text-[#373737] dark:text-white">{{ $child->name }}</span>
                        </button>
                        @endforeach
                    </div>
                    @endforeach
                    @endif
                @endforeach
            </div>
        </div>
    </div>
</div>
