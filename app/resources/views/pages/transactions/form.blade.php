<x-app-layout :title="$transaction->exists ? 'Editar movimiento' : 'Nuevo movimiento'">

@php
$typeColor = [
    'expense'  => '#ef4444',
    'income'   => '#76a72b',
    'transfer' => '#878787',
    'interest' => '#3b82f6',
    'fee'      => '#f97316',
];
$initType = old('type', $transaction->type ?? 'expense');
$initAmount = old('amount', $transaction->amount ?? '');
@endphp

<div
    x-data="txForm(
        '{{ $initType }}',
        '{{ $initAmount }}',
        {{ json_encode($typeColor) }}
    )"
    class="fixed inset-0 lg:relative lg:inset-auto flex flex-col bg-[#111] lg:max-w-sm lg:mx-auto lg:rounded-3xl lg:my-6 lg:shadow-2xl lg:overflow-hidden"
    style="min-height: 100dvh; max-height: 100dvh;"
    :style="`--accent: ${accentColor}`"
>

    {{-- ── Header: cancelar · tipo · confirmar ─────────── --}}
    <div class="flex-none flex items-center gap-1 px-4 pt-safe-top pt-4 pb-2">
        <a href="{{ route('transactions.index') }}"
           class="w-9 h-9 flex items-center justify-center rounded-full text-white/40 hover:text-white hover:bg-white/10 transition-colors flex-shrink-0">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
        </a>

        <div class="flex-1 flex items-center justify-center gap-0.5">
            @foreach(['expense' => 'Egreso', 'income' => 'Ingreso', 'transfer' => 'Transfer.', 'interest' => 'Interés'] as $val => $label)
            <label class="cursor-pointer">
                <input type="radio" name="type" value="{{ $val }}" x-model="type" class="sr-only">
                <span class="block px-3 py-1.5 rounded-full text-xs font-semibold transition-all duration-150"
                      :class="type === '{{ $val }}'
                          ? 'text-white'
                          : 'text-white/30 hover:text-white/60'"
                      :style="type === '{{ $val }}' ? `background:${accentColor}` : ''">
                    {{ $label }}
                </span>
            </label>
            @endforeach
        </div>

        <button type="submit" form="tx-form"
            class="w-9 h-9 flex items-center justify-center rounded-full text-white transition-colors flex-shrink-0"
            :style="`background:${accentColor}`">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
        </button>
    </div>

    {{-- ── Monto ────────────────────────────────────────── --}}
    <div class="flex-none flex flex-col items-center justify-center py-6 px-6">
        <div class="flex items-baseline gap-2">
            <span class="text-white/30 text-2xl font-light">$</span>
            <span x-text="displayAmount || '0'"
                  class="text-5xl font-bold text-white tracking-tight font-['Roboto'] tabular-nums leading-none"
                  :style="`color:${amount && amount !== '0' ? accentColor : 'rgba(255,255,255,0.9)'}`">
            </span>
            <span class="text-white/25 text-sm font-medium self-end mb-1">MXN</span>
        </div>
        @error('amount')
        <p class="mt-1 text-xs text-red-400">{{ $message }}</p>
        @enderror
    </div>

    {{-- ── Form hidden ──────────────────────────────────── --}}
    <form id="tx-form" method="POST"
          action="{{ $transaction->exists ? route('transactions.update', $transaction) : route('transactions.store') }}">
        @csrf
        @if($transaction->exists) @method('PATCH') @endif
        <input type="hidden" name="amount" x-bind:value="amount">
        <input type="hidden" name="type"   x-bind:value="type">
    </form>

    {{-- ── Filas de campos ─────────────────────────────── --}}
    <div class="flex-none mx-3 rounded-2xl overflow-hidden divide-y divide-white/[0.06] bg-white/[0.06]">

        {{-- Cuenta --}}
        <label class="flex items-center gap-3 px-4 py-3.5 cursor-pointer">
            <div class="w-8 h-8 rounded-xl flex items-center justify-center flex-shrink-0" :style="`background:${accentColor}22`">
                <svg class="w-4 h-4" :style="`color:${accentColor}`" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg>
            </div>
            <span class="text-white/40 text-sm w-20 flex-shrink-0">Cuenta</span>
            <div class="flex-1 min-w-0">
                <select name="account_id" form="tx-form" required
                    class="w-full bg-transparent text-white text-sm text-right focus:outline-none appearance-none cursor-pointer truncate">
                    <option value="" class="bg-[#222]">Selecciona</option>
                    @foreach($accounts as $account)
                    <option value="{{ $account->id }}" class="bg-[#222]"
                        {{ old('account_id', $transaction->account_id) == $account->id ? 'selected' : '' }}>
                        {{ $account->name }}
                    </option>
                    @endforeach
                </select>
            </div>
            <svg class="w-4 h-4 text-white/20 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
        </label>

        {{-- Cuenta destino (solo transferencias) --}}
        <label class="flex items-center gap-3 px-4 py-3.5 cursor-pointer" x-show="type === 'transfer'" x-cloak>
            <div class="w-8 h-8 rounded-xl flex items-center justify-center flex-shrink-0 bg-white/10">
                <svg class="w-4 h-4 text-white/50" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/></svg>
            </div>
            <span class="text-white/40 text-sm w-20 flex-shrink-0">Destino</span>
            <div class="flex-1 min-w-0">
                <select name="counterparty_account_id" form="tx-form"
                    class="w-full bg-transparent text-white text-sm text-right focus:outline-none appearance-none cursor-pointer truncate">
                    <option value="" class="bg-[#222]">Selecciona</option>
                    @foreach($accounts as $account)
                    <option value="{{ $account->id }}" class="bg-[#222]"
                        {{ old('counterparty_account_id', $transaction->counterparty_account_id) == $account->id ? 'selected' : '' }}>
                        {{ $account->name }}
                    </option>
                    @endforeach
                </select>
            </div>
            <svg class="w-4 h-4 text-white/20 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
        </label>

        {{-- Categoría (no en transfer/interest) --}}
        <label class="flex items-center gap-3 px-4 py-3.5 cursor-pointer"
               x-show="type !== 'transfer' && type !== 'interest'" x-cloak>
            <div class="w-8 h-8 rounded-xl flex items-center justify-center flex-shrink-0" :style="`background:${accentColor}22`">
                <svg class="w-4 h-4" :style="`color:${accentColor}`" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3H5a2 2 0 00-2 2v2a2 2 0 00.586 1.414l9 9A2 2 0 0014 19l5-5a2 2 0 000-2.828l-9-9A2 2 0 007 3z"/></svg>
            </div>
            <span class="text-white/40 text-sm w-20 flex-shrink-0">Categoría</span>
            <div class="flex-1 min-w-0">
                <select name="category_id" form="tx-form"
                    x-bind:disabled="type === 'transfer' || type === 'interest'"
                    class="w-full bg-transparent text-white text-sm text-right focus:outline-none appearance-none cursor-pointer truncate">
                    <option value="" class="bg-[#222]">Sin categoría</option>
                    @foreach($categories->whereNull('parent_id') as $cat)
                    <optgroup label="{{ $cat->name }}" class="bg-[#222]">
                        <option value="{{ $cat->id }}" class="bg-[#222]"
                            {{ old('category_id', $transaction->category_id) == $cat->id ? 'selected' : '' }}>
                            {{ $cat->name }}
                        </option>
                        @foreach($cat->children as $child)
                        <option value="{{ $child->id }}" class="bg-[#222]"
                            {{ old('category_id', $transaction->category_id) == $child->id ? 'selected' : '' }}>
                            ↳ {{ $child->name }}
                        </option>
                        @endforeach
                    </optgroup>
                    @endforeach
                </select>
            </div>
            <svg class="w-4 h-4 text-white/20 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
        </label>

        {{-- Fuente (solo ingresos) --}}
        <label class="flex items-center gap-3 px-4 py-3.5 cursor-pointer" x-show="type === 'income'" x-cloak>
            <div class="w-8 h-8 rounded-xl flex items-center justify-center flex-shrink-0 bg-[#76a72b]/20">
                <svg class="w-4 h-4 text-[#76a72b]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
            </div>
            <span class="text-white/40 text-sm w-20 flex-shrink-0">Fuente</span>
            <div class="flex-1 min-w-0">
                <select name="source_id" form="tx-form"
                    class="w-full bg-transparent text-white text-sm text-right focus:outline-none appearance-none cursor-pointer truncate">
                    <option value="" class="bg-[#222]">Sin fuente</option>
                    @foreach($sources as $source)
                    <option value="{{ $source->id }}" class="bg-[#222]"
                        {{ old('source_id', $transaction->source_id) == $source->id ? 'selected' : '' }}>
                        {{ $source->name }}
                    </option>
                    @endforeach
                </select>
            </div>
            <svg class="w-4 h-4 text-white/20 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
        </label>

        {{-- Descripción --}}
        <div class="flex items-center gap-3 px-4 py-3.5">
            <div class="w-8 h-8 rounded-xl flex items-center justify-center flex-shrink-0 bg-white/[0.07]">
                <svg class="w-4 h-4 text-white/40" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
            </div>
            <span class="text-white/40 text-sm w-20 flex-shrink-0">Nota</span>
            <input type="text" name="description" form="tx-form" maxlength="500"
                value="{{ old('description', $transaction->description) }}"
                placeholder="Opcional"
                class="flex-1 bg-transparent text-white text-sm text-right focus:outline-none placeholder-white/20">
        </div>

        {{-- Fecha --}}
        <div class="flex items-center gap-3 px-4 py-3.5">
            <div class="w-8 h-8 rounded-xl flex items-center justify-center flex-shrink-0 bg-white/[0.07]">
                <svg class="w-4 h-4 text-white/40" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
            </div>
            <span class="text-white/40 text-sm w-20 flex-shrink-0">Fecha</span>
            <input type="date" name="date" form="tx-form" required
                value="{{ old('date', $transaction->date?->format('Y-m-d') ?? now()->format('Y-m-d')) }}"
                class="flex-1 bg-transparent text-white text-sm text-right focus:outline-none [color-scheme:dark] cursor-pointer">
        </div>

    </div>

    {{-- ── Numpad ───────────────────────────────────────── --}}
    <div class="flex-1 flex flex-col justify-end px-3 pb-safe-bottom pb-4 mt-3">
        <div class="grid grid-cols-3 gap-1.5">
            @foreach(['1','2','3','4','5','6','7','8','9','.','0','⌫'] as $key)
            <button type="button"
                @if($key === '⌫')
                    x-on:click="backspace()"
                @elseif($key === '.')
                    x-on:click="appendDot()"
                @else
                    x-on:click="append('{{ $key }}')"
                @endif
                class="h-14 rounded-2xl text-white font-semibold text-xl transition-all active:scale-95 select-none
                    {{ $key === '⌫' ? 'bg-white/[0.06] text-red-400' : 'bg-white/[0.06] hover:bg-white/10' }}"
            >
                @if($key === '⌫')
                <svg class="w-5 h-5 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2M3 12l6.414 6.414a2 2 0 001.414.586H19a2 2 0 002-2V7a2 2 0 00-2-2h-8.172a2 2 0 00-1.414.586L3 12z"/></svg>
                @else
                    {{ $key }}
                @endif
            </button>
            @endforeach
        </div>

        {{-- Guardar y crear otro --}}
        @if(!$transaction->exists)
        <button type="submit" form="tx-form" name="save_and_new" value="1"
            class="mt-2 w-full py-3 rounded-2xl text-sm font-semibold transition-colors"
            :style="`background:${accentColor}22; color:${accentColor}`">
            + Guardar y registrar otro
        </button>
        @endif
    </div>

</div>

<script>
function txForm(initialType, initialAmount, typeColors) {
    return {
        type: initialType,
        amount: initialAmount ? String(initialAmount) : '',

        get accentColor() {
            return typeColors[this.type] || typeColors['expense'];
        },

        get displayAmount() {
            if (!this.amount) return '0';
            const n = parseFloat(this.amount);
            if (isNaN(n)) return '0';
            // Formatear con separadores de miles
            const parts = this.amount.split('.');
            const intPart = parseInt(parts[0] || '0').toLocaleString('es-MX');
            return parts.length > 1 ? `${intPart}.${parts[1]}` : intPart;
        },

        append(digit) {
            if (this.amount === '' || this.amount === '0') {
                this.amount = digit;
            } else if (this.amount.length < 11) {
                // Máx 2 decimales
                const dotIdx = this.amount.indexOf('.');
                if (dotIdx !== -1 && this.amount.length - dotIdx > 2) return;
                this.amount += digit;
            }
        },

        appendDot() {
            if (!this.amount.includes('.')) {
                this.amount = (this.amount || '0') + '.';
            }
        },

        backspace() {
            if (this.amount.length > 1) {
                this.amount = this.amount.slice(0, -1);
            } else {
                this.amount = '';
            }
        },
    }
}
</script>

</x-app-layout>
