<x-app-layout :title="$transaction->exists ? 'Editar movimiento' : 'Nuevo movimiento'">

@php
$typeColor = [
    'expense'  => '#ef4444',
    'income'   => '#76a72b',
    'transfer' => '#878787',
    'interest' => '#3b82f6',
];
$initType   = old('type',   $transaction->type   ?? 'expense');
$initAmount = old('amount', $transaction->amount ?? '');
$typeLabels = ['expense' => 'Egreso', 'income' => 'Ingreso', 'transfer' => 'Transfer.', 'interest' => 'Interés'];
$action = $transaction->exists
    ? route('transactions.update', $transaction)
    : route('transactions.store');
@endphp

<style>
#tx-mobile  { display: flex; }
#tx-desktop { display: none; }
@media (min-width: 1024px) {
    #tx-mobile  { display: none !important; }
    #tx-desktop { display: block; }
}

/* Filas del formulario mobile */
.tx-row {
    display: flex;
    align-items: center;
    min-height: 52px;
    padding: 0 16px;
    background: #fff;
    border-bottom: 1px solid #f0eeee;
    gap: 10px;
}
.tx-row-icon {
    width: 30px;
    height: 30px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}
.tx-row-label {
    font-size: 14px;
    font-weight: 500;
    color: #373737;
    flex-shrink: 0;
    width: 80px;
}
.tx-row-value {
    flex: 1;
    font-size: 14px;
    color: #878787;
    text-align: right;
    border: none;
    outline: none;
    background: transparent;
    min-width: 0;
    padding: 0;
    /* selects nativos iOS — mejor UX que appearance:none */
}
input.tx-row-value::placeholder { color: #ababab; }
</style>

<div x-data="txForm('{{ $initType }}', '{{ $initAmount }}', {{ json_encode($typeColor) }})">

{{-- ==============================================================
     MOBILE
     ============================================================== --}}
<div id="tx-mobile"
     style="position:fixed;top:0;left:0;right:0;bottom:0;z-index:200;background:#f2f2f7;flex-direction:column;overflow:hidden">

    {{-- Header --}}
    <div style="flex:0 0 auto;background:#fff;border-bottom:1px solid rgba(0,0,0,0.1);padding:48px 12px 10px;display:flex;align-items:center;gap:4px">

        {{-- Cancelar --}}
        <a href="{{ route('transactions.index') }}"
           style="width:36px;height:36px;display:flex;align-items:center;justify-content:center;border-radius:50%;background:#f2f2f7;color:#878787;text-decoration:none;flex-shrink:0">
            <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7"/>
            </svg>
        </a>

        {{-- Tabs de tipo --}}
        <div style="flex:1;display:flex;align-items:center;justify-content:center;gap:4px;flex-wrap:nowrap">
            @foreach($typeLabels as $val => $label)
            <label style="cursor:pointer;flex-shrink:0">
                <input type="radio" x-model="type" value="{{ $val }}" style="position:absolute;opacity:0;width:0;height:0">
                <span style="display:block;padding:5px 10px;border-radius:20px;font-size:11px;font-weight:700;white-space:nowrap;transition:all .15s"
                      :style="type === '{{ $val }}'
                          ? `background:${accentColor};color:#fff`
                          : 'background:#f2f2f7;color:#8e8e93'">
                    {{ $label }}
                </span>
            </label>
            @endforeach
        </div>

        {{-- Guardar --}}
        <button type="submit" form="mobile-form"
                style="width:36px;height:36px;display:flex;align-items:center;justify-content:center;border-radius:50%;color:#fff;border:none;cursor:pointer;flex-shrink:0"
                :style="`background:${accentColor}`">
            <svg width="17" height="17" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
            </svg>
        </button>
    </div>

    {{-- Monto --}}
    <div style="flex:0 0 auto;background:#fff;border-bottom:1px solid rgba(0,0,0,0.08);display:flex;align-items:baseline;justify-content:center;gap:6px;padding:20px 24px">
        <span style="font-size:20px;font-weight:300;color:#ababab">$</span>
        <span x-text="displayAmount || '0'"
              style="font-size:50px;font-weight:700;line-height:1;font-variant-numeric:tabular-nums;transition:color .15s"
              :style="`color:${amount && amount !== '0' ? accentColor : '#3a3a3c'}`">
        </span>
        <span style="font-size:12px;font-weight:600;color:#ababab;align-self:flex-end;margin-bottom:6px;letter-spacing:.08em">MXN</span>
    </div>

    {{-- Form hidden --}}
    <form id="mobile-form" method="POST" action="{{ $action }}">
        @csrf
        @if($transaction->exists) @method('PATCH') @endif
        <input type="hidden" name="amount" x-bind:value="amount">
        <input type="hidden" name="type"   x-bind:value="type">
    </form>

    {{-- Campos --}}
    <div style="flex:1;overflow-y:auto;padding:16px 0">

        {{-- Sección principal --}}
        <div style="margin:0 16px;border-radius:12px;overflow:hidden;box-shadow:0 1px 3px rgba(0,0,0,0.08)">

            {{-- Cuenta --}}
            <div class="tx-row">
                <div class="tx-row-icon" :style="`background:${accentColor}18`">
                    <svg width="15" height="15" fill="none" stroke="currentColor" viewBox="0 0 24 24" :style="`color:${accentColor}`">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/>
                    </svg>
                </div>
                <span class="tx-row-label">Cuenta</span>
                <select name="account_id" form="mobile-form" required class="tx-row-value">
                    <option value="">Selecciona</option>
                    @foreach($accounts as $account)
                    <option value="{{ $account->id }}"
                        {{ old('account_id', $transaction->account_id) == $account->id ? 'selected' : '' }}>
                        {{ $account->displayLabel() }}
                    </option>
                    @endforeach
                </select>
            </div>

            {{-- Cuenta destino --}}
            <div class="tx-row" x-show="type === 'transfer'" x-cloak>
                <div class="tx-row-icon" style="background:#87878718">
                    <svg width="15" height="15" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="color:#878787">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/>
                    </svg>
                </div>
                <span class="tx-row-label">Destino</span>
                <select name="counterparty_account_id" form="mobile-form" class="tx-row-value"
                        :required="type === 'transfer'">
                    <option value="">Selecciona</option>
                    @foreach($accounts as $account)
                    <option value="{{ $account->id }}"
                        {{ old('counterparty_account_id', $transaction->counterparty_account_id) == $account->id ? 'selected' : '' }}>
                        {{ $account->displayLabel() }}
                    </option>
                    @endforeach
                </select>
            </div>

            {{-- Categoría --}}
            <div class="tx-row" x-show="type !== 'transfer' && type !== 'interest'" x-cloak>
                <div class="tx-row-icon" :style="`background:${accentColor}18`">
                    <svg width="15" height="15" fill="none" stroke="currentColor" viewBox="0 0 24 24" :style="`color:${accentColor}`">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3H5a2 2 0 00-2 2v2a2 2 0 00.586 1.414l9 9A2 2 0 0014 19l5-5a2 2 0 000-2.828l-9-9A2 2 0 007 3z"/>
                    </svg>
                </div>
                <span class="tx-row-label">Categoría</span>
                <x-category-picker :categories="$categories" :selected="$transaction->category_id"
                    form="mobile-form" variant="row"
                    disabled-expr="type === 'transfer' || type === 'interest'" />
            </div>

            {{-- Fuente --}}
            <div class="tx-row" x-show="type === 'income'" x-cloak>
                <div class="tx-row-icon" style="background:#76a72b18">
                    <svg width="15" height="15" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="color:#76a72b">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                    </svg>
                </div>
                <span class="tx-row-label">Fuente</span>
                <select name="source_id" form="mobile-form" class="tx-row-value">
                    <option value="">Sin fuente</option>
                    @foreach($sources as $source)
                    <option value="{{ $source->id }}"
                        {{ old('source_id', $transaction->source_id) == $source->id ? 'selected' : '' }}>
                        {{ $source->name }}
                    </option>
                    @endforeach
                </select>
            </div>

            {{-- Nota --}}
            <div class="tx-row">
                <div class="tx-row-icon" style="background:#87878714">
                    <svg width="15" height="15" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="color:#8e8e93">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                    </svg>
                </div>
                <span class="tx-row-label">Nota</span>
                <input type="text" name="description" form="mobile-form" maxlength="500"
                    value="{{ old('description', $transaction->description) }}"
                    placeholder="Opcional"
                    class="tx-row-value"
                    style="text-align:right;caret-color:#76a72b">
            </div>

            {{-- Fecha --}}
            <div class="tx-row" style="border-bottom:none">
                <div class="tx-row-icon" style="background:#87878714">
                    <svg width="15" height="15" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="color:#8e8e93">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                    </svg>
                </div>
                <span class="tx-row-label">Fecha</span>
                <input type="date" name="date" form="mobile-form" required
                    value="{{ old('date', $transaction->date?->format('Y-m-d') ?? now()->format('Y-m-d')) }}"
                    class="tx-row-value"
                    style="text-align:right">
            </div>

        </div>
    </div>

    {{-- Numpad --}}
    <div style="flex:0 0 auto;background:#f2f2f7;border-top:1px solid rgba(0,0,0,0.08);padding:8px 12px 28px">
        <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:8px;margin-bottom:8px">
            @foreach(['1','2','3','4','5','6','7','8','9','.','0','⌫'] as $key)
            <button type="button"
                @if($key === '⌫')    x-on:click="backspace()"
                @elseif($key === '.') x-on:click="appendDot()"
                @else                 x-on:click="append('{{ $key }}')"
                @endif
                style="height:52px;border-radius:11px;border:none;cursor:pointer;font-size:22px;font-weight:400;background:#ffffff;box-shadow:0 1px 0 rgba(0,0,0,0.2);
                    {{ $key === '⌫' ? 'color:#ef4444' : 'color:#000000' }};
                    -webkit-tap-highlight-color:transparent">
                @if($key === '⌫')
                <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="display:block;margin:auto">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2M3 12l6.414 6.414a2 2 0 001.414.586H19a2 2 0 002-2V7a2 2 0 00-2-2h-8.172a2 2 0 00-1.414.586L3 12z"/>
                </svg>
                @else
                    {{ $key }}
                @endif
            </button>
            @endforeach
        </div>

        @if(!$transaction->exists)
        <button type="submit" form="mobile-form" name="save_and_new" value="1"
            style="width:100%;padding:13px;border-radius:12px;border:none;cursor:pointer;font-size:13px;font-weight:600;-webkit-tap-highlight-color:transparent"
            :style="`background:${accentColor}18;color:${accentColor}`">
            + Guardar y registrar otro
        </button>
        @endif
    </div>

</div>{{-- /MOBILE --}}


{{-- ==============================================================
     DESKTOP
     ============================================================== --}}
<div id="tx-desktop" style="max-width:560px;margin:0 auto">

    <x-page-header
        :title="$transaction->exists ? 'Editar movimiento' : 'Nuevo movimiento'"
        :back="route('transactions.index')" />

    <x-card class="p-6">
        <form method="POST" action="{{ $action }}" class="space-y-5">
            @csrf
            @if($transaction->exists) @method('PATCH') @endif

            {{-- Tipo --}}
            <div class="grid grid-cols-4 gap-2">
                @foreach($typeLabels as $val => $label)
                <label class="cursor-pointer">
                    <input type="radio" name="type" value="{{ $val }}" x-model="type" class="sr-only"
                        {{ $initType === $val ? 'checked' : '' }}>
                    <div class="text-center py-2.5 rounded-xl text-xs font-bold transition-all duration-150 border-2 select-none"
                         :class="type === '{{ $val }}' ? 'border-transparent text-white' : 'border-[#ababab]/30 text-[#878787]'"
                         :style="type === '{{ $val }}' ? `background:${accentColor}` : ''">
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
                        value="{{ old('amount', $transaction->amount) }}"
                        placeholder="0.00"
                        class="w-full rounded-xl border border-[#ababab]/40 bg-[#efeded]/50 dark:bg-white/5 pl-9 pr-16 py-3 text-2xl font-bold text-[#373737] dark:text-white tabular-nums focus:outline-none focus:ring-2 focus:ring-[#76a72b] transition">
                    <span class="absolute right-4 top-1/2 -translate-y-1/2 text-[#ababab] text-xs font-semibold uppercase tracking-wider">MXN</span>
                </div>
                @error('amount')<p class="mt-1.5 text-xs text-red-500">{{ $message }}</p>@enderror
            </div>

            {{-- Cuenta --}}
            <div>
                <label class="block text-sm font-semibold text-[#373737] dark:text-white mb-1.5">
                    Cuenta <span class="text-[#76a72b]">*</span>
                </label>
                <select name="account_id" required
                    class="w-full rounded-xl border border-[#ababab]/40 bg-[#efeded]/50 dark:bg-white/5 px-4 py-3 text-[#373737] dark:text-white focus:outline-none focus:ring-2 focus:ring-[#76a72b] transition">
                    <option value="">Selecciona una cuenta</option>
                    @foreach($accounts as $account)
                    <option value="{{ $account->id }}"
                        {{ old('account_id', $transaction->account_id) == $account->id ? 'selected' : '' }}>
                        {{ $account->displayLabel() }}
                    </option>
                    @endforeach
                </select>
                @error('account_id')<p class="mt-1.5 text-xs text-red-500">{{ $message }}</p>@enderror
            </div>

            {{-- Cuenta destino --}}
            <div x-show="type === 'transfer'" x-cloak>
                <label class="block text-sm font-semibold text-[#373737] dark:text-white mb-1.5">
                    Cuenta destino <span class="text-[#76a72b]">*</span>
                </label>
                <select name="counterparty_account_id" :required="type === 'transfer'"
                    class="w-full rounded-xl border border-[#ababab]/40 bg-[#efeded]/50 dark:bg-white/5 px-4 py-3 text-[#373737] dark:text-white focus:outline-none focus:ring-2 focus:ring-[#76a72b] transition">
                    <option value="">Selecciona destino</option>
                    @foreach($accounts as $account)
                    <option value="{{ $account->id }}"
                        {{ old('counterparty_account_id', $transaction->counterparty_account_id) == $account->id ? 'selected' : '' }}>
                        {{ $account->displayLabel() }}
                    </option>
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
                    <option value="{{ $source->id }}"
                        {{ old('source_id', $transaction->source_id) == $source->id ? 'selected' : '' }}>
                        {{ $source->name }}
                    </option>
                    @endforeach
                </select>
            </div>

            {{-- Descripción --}}
            <div>
                <label class="block text-sm font-semibold text-[#373737] dark:text-white mb-1.5">Descripción</label>
                <input type="text" name="description" maxlength="500"
                    value="{{ old('description', $transaction->description) }}"
                    placeholder="Opcional"
                    class="w-full rounded-xl border border-[#ababab]/40 bg-[#efeded]/50 dark:bg-white/5 px-4 py-3 text-[#373737] dark:text-white placeholder-[#ababab] focus:outline-none focus:ring-2 focus:ring-[#76a72b] transition">
            </div>

            {{-- Fecha --}}
            <div>
                <label class="block text-sm font-semibold text-[#373737] dark:text-white mb-1.5">
                    Fecha <span class="text-[#76a72b]">*</span>
                </label>
                <input type="date" name="date" required
                    value="{{ old('date', $transaction->date?->format('Y-m-d') ?? now()->format('Y-m-d')) }}"
                    class="w-full rounded-xl border border-[#ababab]/40 bg-[#efeded]/50 dark:bg-white/5 px-4 py-3 text-[#373737] dark:text-white focus:outline-none focus:ring-2 focus:ring-[#76a72b] transition">
                @error('date')<p class="mt-1.5 text-xs text-red-500">{{ $message }}</p>@enderror
            </div>

            <div class="flex gap-3 pt-2">
                <x-btn variant="secondary" href="{{ route('transactions.index') }}" class="flex-1">Cancelar</x-btn>
                <x-btn type="submit" class="flex-1">
                    {{ $transaction->exists ? 'Guardar cambios' : 'Registrar' }}
                </x-btn>
            </div>

            @if(!$transaction->exists)
            <button type="submit" name="save_and_new" value="1"
                class="w-full text-center text-sm font-semibold py-2.5 rounded-xl transition-colors"
                :style="`background:${accentColor}15;color:${accentColor}`">
                + Registrar y agregar otro
            </button>
            @endif
        </form>
    </x-card>
</div>

</div>

<script>
function txForm(initialType, initialAmount, typeColors) {
    return {
        type: initialType,
        amount: initialAmount ? String(initialAmount) : '',
        get accentColor() { return typeColors[this.type] || typeColors['expense']; },
        get displayAmount() {
            if (!this.amount) return '0';
            const parts = this.amount.split('.');
            const intPart = parseInt(parts[0] || '0').toLocaleString('es-MX');
            return parts.length > 1 ? `${intPart}.${parts[1]}` : intPart;
        },
        append(digit) {
            if (this.amount === '' || this.amount === '0') { this.amount = digit; }
            else if (this.amount.length < 11) {
                const d = this.amount.indexOf('.');
                if (d !== -1 && this.amount.length - d > 2) return;
                this.amount += digit;
            }
        },
        appendDot() { if (!this.amount.includes('.')) this.amount = (this.amount || '0') + '.'; },
        backspace() { this.amount = this.amount.length > 1 ? this.amount.slice(0, -1) : ''; },
    }
}
</script>

</x-app-layout>
