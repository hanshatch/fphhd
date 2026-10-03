<x-app-layout :title="$goal->exists ? 'Editar meta' : 'Nueva meta'">

@php
    $label = 'block text-sm font-semibold text-[#373737] dark:text-white mb-1.5';
    $input = 'w-full rounded-xl border border-[#ababab]/40 bg-[#efeded]/50 dark:bg-white/5 px-4 py-3 text-[#373737] dark:text-white placeholder-[#ababab] focus:outline-none focus:ring-2 focus:ring-[#76a72b] transition';

    $oldItems = old('items', $goal->exists
        ? $goal->items->map(fn ($i) => ['description' => $i->description, 'amount' => (string) $i->amount])->all()
        : []);
    $itemsJs  = collect($oldItems)->map(fn ($i) => ['description' => $i['description'] ?? '', 'amount' => $i['amount'] ?? ''])->values();
    $saved    = $goal->exists ? (string) ($goal->contributions()->sum('amount') ?: 0) : '0';
    $colors   = ['#76a72b', '#3b82f6', '#8b5cf6', '#f97316', '#ec4899', '#0ea5e9', '#eab308', '#373737'];
@endphp

<div class="max-w-2xl mx-auto">
    <x-page-header :title="$goal->exists ? 'Editar meta' : 'Nueva meta'"
        :back="$goal->exists ? route('goals.show', $goal) : route('goals.index')" />

    <form method="POST" action="{{ $goal->exists ? route('goals.update', $goal) : route('goals.store') }}"
          x-data="goalForm({
              items: @js($itemsJs),
              total: @js((string) old('target_amount', $goal->exists && $goal->items->isEmpty() ? $goal->target_amount : '')),
              date: @js(old('target_date', $goal->target_date?->format('Y-m-d') ?? '')),
              saved: @js($saved),
              kind: @js(old('kind', $goal->kind)),
              color: @js(old('color', $goal->color ?? '#76a72b')),
          })"
          class="space-y-4">
        @csrf
        @if($goal->exists) @method('PATCH') @endif

        <x-card class="p-6 space-y-5">
            {{-- Tipo --}}
            <fieldset>
                <legend class="{{ $label }}">¿Qué estás planeando?</legend>
                <div class="grid grid-cols-3 sm:grid-cols-5 gap-2">
                    @foreach(\App\Models\Goal::KINDS as $value => $text)
                    <label class="cursor-pointer">
                        <input type="radio" name="kind" value="{{ $value }}" x-model="kind" class="sr-only peer">
                        <span class="flex flex-col items-center gap-1.5 py-3 rounded-xl border-2 text-xs font-semibold transition-colors peer-focus-visible:ring-2 peer-focus-visible:ring-[#76a72b]"
                              x-bind:class="kind === '{{ $value }}' ? 'border-[#76a72b] bg-[#76a72b]/10 text-positive' : 'border-[#ababab]/30 text-[#878787] hover:border-[#ababab]'">
                            <x-goal-kind-icon :kind="$value" />
                            {{ $text }}
                        </span>
                    </label>
                    @endforeach
                </div>
            </fieldset>

            {{-- Nombre --}}
            <div>
                <label for="name" class="{{ $label }}">Nombre <span class="text-positive">*</span></label>
                <input id="name" type="text" name="name" value="{{ old('name', $goal->name) }}" required maxlength="150"
                       @unless($goal->exists) autofocus @endunless
                       placeholder="Ej. Viaje a Japón, Remodelar cocina, Laptop nueva" class="{{ $input }}">
                @error('name')<p class="mt-1.5 text-xs text-negative">{{ $message }}</p>@enderror
            </div>

            {{-- Fecha --}}
            <div>
                <label for="target_date" class="{{ $label }}">¿Para cuándo necesitas el dinero? <span class="text-positive">*</span></label>
                <input id="target_date" type="date" name="target_date" x-model="date" required class="{{ $input }}">
                <p class="mt-1.5 text-xs text-[#878787]">El plan busca que tengas todo listo al inicio de ese mes.</p>
                @error('target_date')<p class="mt-1.5 text-xs text-negative">{{ $message }}</p>@enderror
            </div>
        </x-card>

        {{-- ── Costos ─────────────────────────────────────────────── --}}
        <x-card class="p-6">
            <div class="flex items-center justify-between gap-3 mb-1">
                <h2 class="text-sm font-semibold text-[#373737] dark:text-white">¿Cuánto va a costar?</h2>
                <span class="text-xs text-[#878787]" x-show="items.length" x-cloak>
                    {{-- Con desglose, el total se calcula solo --}}
                    Total calculado
                </span>
            </div>
            <p class="text-xs text-[#878787] mb-4">Escribe un total o desglósalo en partidas (vuelo, hotel, comidas…).</p>

            {{-- Total directo (solo sin desglose) --}}
            <div x-show="!items.length">
                <label for="target_amount" class="sr-only">Costo total</label>
                <div class="relative">
                    <span class="absolute left-4 top-1/2 -translate-y-1/2 text-[#878787] font-semibold text-lg">$</span>
                    <input id="target_amount" type="text" name="target_amount" x-model="total" data-money inputmode="decimal"
                           placeholder="0.00" x-bind:disabled="items.length > 0"
                           class="{{ $input }} pl-9 pr-16 text-2xl font-bold tabular-nums">
                    <span class="absolute right-4 top-1/2 -translate-y-1/2 text-[#878787] text-xs font-semibold uppercase tracking-wider">MXN</span>
                </div>
                @error('target_amount')<p class="mt-1.5 text-xs text-negative">{{ $message }}</p>@enderror
            </div>

            {{-- Desglose --}}
            <div class="space-y-2" x-show="items.length" x-cloak>
                <template x-for="(item, i) in items" x-bind:key="i">
                    <div class="flex items-center gap-2">
                        <input type="text" x-bind:name="`items[${i}][description]`" x-model="item.description" maxlength="150"
                               placeholder="Concepto" aria-label="Concepto de la partida"
                               class="flex-1 min-w-0 rounded-xl border border-[#ababab]/40 bg-[#efeded]/50 dark:bg-white/5 px-3 py-2.5 text-sm text-[#373737] dark:text-white placeholder-[#ababab] focus:outline-none focus:ring-2 focus:ring-[#76a72b]">
                        <div class="relative w-36 flex-shrink-0">
                            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-sm text-[#878787]">$</span>
                            <input type="text" x-bind:name="`items[${i}][amount]`" x-model="item.amount" inputmode="decimal" data-money
                                   placeholder="0.00" aria-label="Monto de la partida"
                                   class="w-full rounded-xl border border-[#ababab]/40 bg-[#efeded]/50 dark:bg-white/5 pl-7 pr-3 py-2.5 text-sm text-right tabular-nums text-[#373737] dark:text-white focus:outline-none focus:ring-2 focus:ring-[#76a72b]">
                        </div>
                        <button type="button" data-no-spinner="true" x-on:click="items.splice(i, 1)" aria-label="Quitar partida" title="Quitar"
                                class="w-10 h-10 flex-shrink-0 flex items-center justify-center rounded-xl text-[#878787] hover:text-negative hover:bg-red-50 dark:hover:bg-red-500/10 transition-colors">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                    </div>
                </template>
                <div class="flex items-center justify-between pt-3 mt-1 border-t border-[#ababab]/15">
                    <span class="text-sm font-semibold text-[#373737] dark:text-white">Total</span>
                    <span class="text-lg font-bold tabular-nums text-[#373737] dark:text-white" x-text="money(itemsTotal)"></span>
                </div>
            </div>

            <button type="button" data-no-spinner="true" x-on:click="addItem()"
                    class="mt-3 inline-flex items-center gap-1.5 min-h-[40px] px-3 rounded-xl text-sm font-semibold text-positive hover:bg-[#76a72b]/10 transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/></svg>
                <span x-text="items.length ? 'Agregar partida' : 'Desglosar en partidas'"></span>
            </button>

            {{-- Vista previa del plan --}}
            <div class="mt-5 rounded-xl p-4 bg-[#76a72b]/10 flex items-center gap-3" x-show="quota" x-cloak aria-live="polite">
                <svg class="w-5 h-5 text-positive flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                <p class="text-sm text-[#373737] dark:text-white">
                    Aparta <strong class="tabular-nums" x-text="money(quota)"></strong> al mes
                    durante <strong x-text="months === 1 ? '1 mes' : `${months} meses`"></strong>.
                </p>
            </div>
        </x-card>

        {{-- ── Detalles ───────────────────────────────────────────── --}}
        <x-card class="p-6 space-y-5">
            <div>
                <label for="account_id" class="{{ $label }}">¿Dónde vas a guardar el dinero?</label>
                <select id="account_id" name="account_id" class="{{ $input }}">
                    <option value="">Sin cuenta específica</option>
                    @foreach($accounts as $account)
                    <option value="{{ $account->id }}" {{ (string) old('account_id', $goal->account_id) === (string) $account->id ? 'selected' : '' }}>
                        {{ $account->displayLabel() }}
                    </option>
                    @endforeach
                </select>
                <p class="mt-1.5 text-xs text-[#878787]">Solo informativo: FP te avisa si lo apartado supera el saldo de esa cuenta.</p>
            </div>

            <fieldset>
                <legend class="{{ $label }}">Color</legend>
                <div class="flex flex-wrap gap-2">
                    @foreach($colors as $c)
                    <label class="cursor-pointer">
                        <input type="radio" name="color" value="{{ $c }}" x-model="color" class="sr-only peer" aria-label="Color {{ $c }}">
                        <span class="block w-9 h-9 rounded-full ring-offset-2 ring-offset-white dark:ring-offset-[#2a2a2a] transition-shadow peer-focus-visible:ring-2 peer-focus-visible:ring-[#373737]"
                              style="background-color: {{ $c }}"
                              x-bind:class="color === '{{ $c }}' ? 'ring-2 ring-[#373737] dark:ring-white' : ''"></span>
                    </label>
                    @endforeach
                </div>
            </fieldset>

            <div>
                <label for="notes" class="{{ $label }}">Notas</label>
                <textarea id="notes" name="notes" rows="3" maxlength="1000" placeholder="Opcional"
                          class="{{ $input }}">{{ old('notes', $goal->notes) }}</textarea>
            </div>
        </x-card>

        <div class="flex gap-3">
            <x-btn variant="secondary" href="{{ $goal->exists ? route('goals.show', $goal) : route('goals.index') }}" class="flex-1">Cancelar</x-btn>
            <x-btn type="submit" class="flex-1">{{ $goal->exists ? 'Guardar cambios' : 'Crear meta' }}</x-btn>
        </div>
    </form>
</div>

@push('scripts')
<script>
function goalForm(init) {
    // Montos en centavos enteros para no sumar con flotantes
    const cents = v => {
        const n = String(v ?? '').replace(/[$,\s]/g, '');
        if (n === '' || isNaN(n)) return 0;
        return Math.round(Number(n) * 100);
    };

    return {
        items: init.items,
        total: init.total,
        date: init.date,
        kind: init.kind,
        color: init.color,
        savedCents: cents(init.saved),

        addItem() {
            // Al empezar el desglose, lo escrito como total se vuelve la primera partida
            if (!this.items.length && cents(this.total) > 0) {
                this.items.push({ description: 'Total estimado', amount: this.total });
            }
            this.items.push({ description: '', amount: '' });
            this.$nextTick(() => {
                const inputs = this.$root.querySelectorAll('input[name$="[description]"]');
                inputs[inputs.length - 1]?.focus();
            });
        },
        get itemsTotal() { return this.items.reduce((s, i) => s + cents(i.amount), 0); },
        get totalCents() { return this.items.length ? this.itemsTotal : cents(this.total); },

        // Meses entre este mes y el mes de la meta (mínimo 1), igual que el servidor
        get months() {
            if (!this.date) return 0;
            const [y, m] = this.date.split('-').map(Number);
            const now = new Date();
            return Math.max(1, (y - now.getFullYear()) * 12 + (m - 1 - now.getMonth()));
        },
        get quota() {
            const remaining = this.totalCents - this.savedCents;
            if (!this.months || remaining <= 0) return 0;
            return Math.ceil(remaining / this.months);
        },
        money(c) {
            return '$' + (c / 100).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        },
    };
}
</script>
@endpush

</x-app-layout>
