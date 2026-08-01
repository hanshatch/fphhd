{{--
    Fragmento (sin layout) del formulario de movimiento, para inyectarse en el
    modal de la vista de cuenta. Alpine inicializa el HTML inyectado gracias a
    su MutationObserver, por eso aquí solo se usan expresiones inline: nada de
    funciones globales ni <script>, que no se ejecutarían con innerHTML.
--}}
@php
    $typeColor  = ['expense' => '#ef4444', 'income' => '#76a72b', 'transfer' => '#878787', 'interest' => '#3b82f6', 'fee' => '#ef4444'];
    $typeLabels = ['expense' => 'Egreso', 'income' => 'Ingreso', 'transfer' => 'Transfer.', 'interest' => 'Interés'];
    $initType   = $transaction->type ?? 'expense';
    $exists     = $transaction->exists;
    $action     = $exists ? route('transactions.update', $transaction) : route('transactions.store');
@endphp

<div x-data="{
        type: '{{ $initType }}',
        colors: {{ json_encode($typeColor) }},
        get accent() { return this.colors[this.type] || this.colors.expense; }
     }">

    <form method="POST" action="{{ $action }}" class="space-y-4">
        @csrf
        @if($exists) @method('PATCH') @endif
        <input type="hidden" name="redirect_to" value="{{ $redirectTo }}">

        {{-- Tipo --}}
        <div class="grid grid-cols-4 gap-2">
            @foreach($typeLabels as $val => $label)
            <label class="cursor-pointer">
                <input type="radio" name="type" value="{{ $val }}" x-model="type" class="sr-only"
                    {{ $initType === $val ? 'checked' : '' }}>
                <div class="text-center py-2.5 rounded-xl text-xs font-bold transition-all duration-150 border-2 select-none"
                     :class="type === '{{ $val }}' ? 'border-transparent text-white' : 'border-[#ababab]/30 text-[#878787]'"
                     :style="type === '{{ $val }}' ? `background:${accent}` : ''">
                    {{ $label }}
                </div>
            </label>
            @endforeach
        </div>

        {{-- Monto --}}
        <div>
            <label class="block text-sm font-semibold text-[#373737] dark:text-white mb-1.5">
                Monto <span class="text-[#76a72b]">*</span>
            </label>
            <div class="relative">
                <span class="absolute left-4 top-1/2 -translate-y-1/2 text-[#878787] font-semibold text-lg">$</span>
                <input type="text" name="amount" data-money inputmode="decimal" required
                    value="{{ $transaction->amount }}" placeholder="0.00" @if(! $exists) autofocus @endif
                    class="w-full rounded-xl border border-[#ababab]/40 bg-[#efeded]/50 dark:bg-white/5 pl-9 pr-16 py-3 text-2xl font-bold text-[#373737] dark:text-white tabular-nums focus:outline-none focus:ring-2 focus:ring-[#76a72b] transition">
                <span class="absolute right-4 top-1/2 -translate-y-1/2 text-[#ababab] text-xs font-semibold uppercase tracking-wider">MXN</span>
            </div>
        </div>

        {{-- Cuenta --}}
        <div>
            <label class="block text-sm font-semibold text-[#373737] dark:text-white mb-1.5">
                Cuenta <span class="text-[#76a72b]">*</span>
            </label>
            <select name="account_id" required
                class="w-full rounded-xl border border-[#ababab]/40 bg-[#efeded]/50 dark:bg-white/5 px-4 py-3 text-[#373737] dark:text-white focus:outline-none focus:ring-2 focus:ring-[#76a72b] transition">
                @unless($transaction->account_id)
                <option value="">Selecciona una cuenta</option>
                @endunless
                @foreach($accounts->groupBy(fn ($a) => $a->institutionLabel()) as $institution => $group)
                <optgroup label="{{ $institution }}">
                    @foreach($group as $account)
                    <option value="{{ $account->id }}" {{ $transaction->account_id == $account->id ? 'selected' : '' }}>
                        {{ $account->name }}
                    </option>
                    @endforeach
                </optgroup>
                @endforeach
            </select>
        </div>

        {{-- Cuenta destino --}}
        <div x-show="type === 'transfer'" x-cloak>
            <label class="block text-sm font-semibold text-[#373737] dark:text-white mb-1.5">
                Cuenta destino <span class="text-[#76a72b]">*</span>
            </label>
            <select name="counterparty_account_id" :required="type === 'transfer'"
                class="w-full rounded-xl border border-[#ababab]/40 bg-[#efeded]/50 dark:bg-white/5 px-4 py-3 text-[#373737] dark:text-white focus:outline-none focus:ring-2 focus:ring-[#76a72b] transition">
                <option value="">Selecciona destino</option>
                @foreach($accounts->groupBy(fn ($a) => $a->institutionLabel()) as $institution => $group)
                <optgroup label="{{ $institution }}">
                    @foreach($group as $account)
                    <option value="{{ $account->id }}" {{ $transaction->counterparty_account_id == $account->id ? 'selected' : '' }}>
                        {{ $account->name }}
                    </option>
                    @endforeach
                </optgroup>
                @endforeach
            </select>
        </div>

        {{-- Categoría --}}
        <div x-show="type !== 'transfer' && type !== 'interest'" x-cloak>
            <label class="block text-sm font-semibold text-[#373737] dark:text-white mb-1.5">Categoría</label>
            <x-category-picker :categories="$categories" :selected="$transaction->category_id"
                disabled-expr="type === 'transfer' || type === 'interest'" />
        </div>

        {{-- Fuente --}}
        <div x-show="type === 'income'" x-cloak>
            <label class="block text-sm font-semibold text-[#373737] dark:text-white mb-1.5">Fuente</label>
            <select name="source_id"
                class="w-full rounded-xl border border-[#ababab]/40 bg-[#efeded]/50 dark:bg-white/5 px-4 py-3 text-[#373737] dark:text-white focus:outline-none focus:ring-2 focus:ring-[#76a72b] transition">
                <option value="">Sin fuente</option>
                @foreach($sources as $source)
                <option value="{{ $source->id }}" {{ $transaction->source_id == $source->id ? 'selected' : '' }}>
                    {{ $source->name }}
                </option>
                @endforeach
            </select>
        </div>

        {{-- Descripción --}}
        <div>
            <label class="block text-sm font-semibold text-[#373737] dark:text-white mb-1.5">Descripción</label>
            <input type="text" name="description" maxlength="500"
                value="{{ $transaction->description }}"
                placeholder="Opcional"
                class="w-full rounded-xl border border-[#ababab]/40 bg-[#efeded]/50 dark:bg-white/5 px-4 py-3 text-[#373737] dark:text-white placeholder-[#ababab] focus:outline-none focus:ring-2 focus:ring-[#76a72b] transition">
        </div>

        {{-- Fecha --}}
        <div>
            <label class="block text-sm font-semibold text-[#373737] dark:text-white mb-1.5">
                Fecha <span class="text-[#76a72b]">*</span>
            </label>
            <input type="date" name="date" required
                value="{{ $transaction->date?->format('Y-m-d') }}"
                class="w-full rounded-xl border border-[#ababab]/40 bg-[#efeded]/50 dark:bg-white/5 px-4 py-3 text-[#373737] dark:text-white focus:outline-none focus:ring-2 focus:ring-[#76a72b] transition">
        </div>

        <div class="flex gap-3 pt-1">
            <button type="button" data-no-spinner="true" x-on:click="$dispatch('close-tx-modal')"
                class="flex-1 py-3 rounded-xl border border-[#ababab]/40 text-sm font-semibold text-[#878787] hover:bg-[#efeded] dark:hover:bg-white/5 transition-colors">
                Cancelar
            </button>
            <button type="submit"
                class="flex-1 py-3 rounded-xl text-sm font-semibold text-white transition-colors"
                :style="`background:${accent}`">
                {{ $exists ? 'Guardar cambios' : 'Registrar' }}
            </button>
        </div>

        @if(! $exists)
        <button type="submit" name="save_and_new" value="1"
            class="w-full text-center text-sm font-semibold py-2.5 rounded-xl transition-colors"
            :style="`background:${accent}15;color:${accent}`">
            + Registrar y agregar otro
        </button>
        @endif
    </form>
</div>
