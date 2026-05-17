<x-app-layout :title="$transaction->exists ? 'Editar movimiento' : 'Nuevo movimiento'">
<div class="max-w-lg mx-auto" x-data="txForm('{{ old('type', $transaction->type ?? 'expense') }}')">

    <x-page-header :title="$transaction->exists ? 'Editar movimiento' : 'Nuevo movimiento'" :back="route('transactions.index')" />

    <x-card class="p-5">
        <form method="POST" action="{{ $transaction->exists ? route('transactions.update', $transaction) : route('transactions.store') }}" class="space-y-5">
            @csrf
            @if($transaction->exists) @method('PATCH') @endif

            {{-- Selector de tipo --}}
            <div>
                <label class="block text-sm font-semibold text-[#373737] dark:text-white mb-2">Tipo <span class="text-[#76a72b]">*</span></label>
                <div class="grid grid-cols-2 gap-2 sm:grid-cols-4">
                    @foreach([
                        'expense'  => ['💸', 'Egreso'],
                        'income'   => ['💰', 'Ingreso'],
                        'transfer' => ['🔄', 'Transferencia'],
                        'interest' => ['📈', 'Interés'],
                    ] as $val => [$emoji, $label])
                    <label class="cursor-pointer select-none">
                        <input type="radio" name="type" value="{{ $val }}" x-model="type" class="sr-only">
                        <div :class="type === '{{ $val }}'
                                ? 'border-[#76a72b] bg-[#76a72b]/10 text-[#4a7018] dark:text-[#76a72b]'
                                : 'border-[#ababab]/30 text-[#878787] hover:border-[#76a72b]/50'"
                             class="border-2 rounded-xl p-3 text-center transition-all duration-150">
                            <div class="text-2xl mb-1 leading-none">{{ $emoji }}</div>
                            <div class="text-xs font-semibold">{{ $label }}</div>
                        </div>
                    </label>
                    @endforeach
                </div>
            </div>

            {{-- Monto --}}
            <div>
                <label class="block text-sm font-semibold text-[#373737] dark:text-white mb-1.5">Monto <span class="text-[#76a72b]">*</span></label>
                <div class="relative">
                    <span class="absolute left-4 top-1/2 -translate-y-1/2 text-[#878787] text-lg font-semibold">$</span>
                    <input type="number" name="amount" step="0.01" min="0.01" required inputmode="decimal"
                        value="{{ old('amount', $transaction->amount) }}"
                        class="w-full rounded-xl border-2 border-[#ababab]/30 bg-[#efeded]/50 dark:bg-white/5 pl-9 pr-16 py-4 text-2xl font-bold text-[#373737] dark:text-white placeholder-[#ababab] focus:outline-none focus:border-[#76a72b] transition"
                        placeholder="0.00" autofocus>
                    <span class="absolute right-4 top-1/2 -translate-y-1/2 text-[#ababab] text-xs font-bold uppercase tracking-widest">MXN</span>
                </div>
                @error('amount')<p class="mt-1.5 text-xs text-red-500">{{ $message }}</p>@enderror
            </div>

            {{-- Cuenta origen --}}
            <div>
                <label class="block text-sm font-semibold text-[#373737] dark:text-white mb-1.5">
                    <span x-text="type === 'transfer' ? 'Cuenta origen' : 'Cuenta'"></span> <span class="text-[#76a72b]">*</span>
                </label>
                <select name="account_id" required
                    class="w-full rounded-xl border border-[#ababab]/40 bg-[#efeded]/50 dark:bg-white/5 px-4 py-3 text-[#373737] dark:text-white focus:outline-none focus:ring-2 focus:ring-[#76a72b] transition">
                    <option value="">Selecciona una cuenta</option>
                    @foreach($accounts as $account)
                        <option value="{{ $account->id }}" {{ old('account_id', $transaction->account_id) == $account->id ? 'selected' : '' }}>
                            {{ $account->name }}
                        </option>
                    @endforeach
                </select>
                @error('account_id')<p class="mt-1.5 text-xs text-red-500">{{ $message }}</p>@enderror
            </div>

            {{-- Cuenta destino --}}
            <div x-show="type === 'transfer'" x-cloak>
                <label class="block text-sm font-semibold text-[#373737] dark:text-white mb-1.5">Cuenta destino <span class="text-[#76a72b]">*</span></label>
                <select name="counterparty_account_id"
                    class="w-full rounded-xl border border-[#ababab]/40 bg-[#efeded]/50 dark:bg-white/5 px-4 py-3 text-[#373737] dark:text-white focus:outline-none focus:ring-2 focus:ring-[#76a72b] transition">
                    <option value="">Selecciona cuenta destino</option>
                    @foreach($accounts as $account)
                        <option value="{{ $account->id }}" {{ old('counterparty_account_id', $transaction->counterparty_account_id) == $account->id ? 'selected' : '' }}>
                            {{ $account->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            {{-- Categoría --}}
            <div x-show="type !== 'transfer' && type !== 'interest'" x-cloak>
                <label class="block text-sm font-semibold text-[#373737] dark:text-white mb-1.5">Categoría</label>
                <select name="category_id"
                    class="w-full rounded-xl border border-[#ababab]/40 bg-[#efeded]/50 dark:bg-white/5 px-4 py-3 text-[#373737] dark:text-white focus:outline-none focus:ring-2 focus:ring-[#76a72b] transition">
                    <option value="">Sin categoría</option>
                    @foreach($categories->whereNull('parent_id') as $cat)
                    <optgroup label="{{ $cat->name }}">
                        <option value="{{ $cat->id }}" {{ old('category_id', $transaction->category_id) == $cat->id ? 'selected' : '' }}>
                            {{ $cat->name }}
                        </option>
                        @foreach($cat->children as $child)
                        <option value="{{ $child->id }}" {{ old('category_id', $transaction->category_id) == $child->id ? 'selected' : '' }}>
                            &nbsp;&nbsp;{{ $child->name }}
                        </option>
                        @endforeach
                    </optgroup>
                    @endforeach
                </select>
            </div>

            {{-- Fuente --}}
            <div x-show="type === 'income'" x-cloak>
                <label class="block text-sm font-semibold text-[#373737] dark:text-white mb-1.5">Fuente de ingreso</label>
                <select name="source_id"
                    class="w-full rounded-xl border border-[#ababab]/40 bg-[#efeded]/50 dark:bg-white/5 px-4 py-3 text-[#373737] dark:text-white focus:outline-none focus:ring-2 focus:ring-[#76a72b] transition">
                    <option value="">Sin fuente</option>
                    @foreach($sources as $source)
                        <option value="{{ $source->id }}" {{ old('source_id', $transaction->source_id) == $source->id ? 'selected' : '' }}>
                            {{ $source->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            {{-- Fecha --}}
            <div>
                <label class="block text-sm font-semibold text-[#373737] dark:text-white mb-1.5">Fecha <span class="text-[#76a72b]">*</span></label>
                <input type="date" name="date" required
                    value="{{ old('date', $transaction->date?->format('Y-m-d') ?? now()->format('Y-m-d')) }}"
                    class="w-full rounded-xl border border-[#ababab]/40 bg-[#efeded]/50 dark:bg-white/5 px-4 py-3 text-[#373737] dark:text-white focus:outline-none focus:ring-2 focus:ring-[#76a72b] transition">
            </div>

            {{-- Descripción --}}
            <div>
                <label class="block text-sm font-semibold text-[#373737] dark:text-white mb-1.5">Descripción</label>
                <input type="text" name="description" maxlength="500"
                    value="{{ old('description', $transaction->description) }}"
                    placeholder="Opcional — ej. Pago nómina mayo"
                    class="w-full rounded-xl border border-[#ababab]/40 bg-[#efeded]/50 dark:bg-white/5 px-4 py-3 text-[#373737] dark:text-white placeholder-[#ababab] focus:outline-none focus:ring-2 focus:ring-[#76a72b] transition">
            </div>

            {{-- Botones --}}
            <div class="flex gap-3 pt-2">
                <x-btn variant="secondary" href="{{ route('transactions.index') }}" class="flex-1">Cancelar</x-btn>
                @if(!$transaction->exists)
                <button type="submit" name="save_and_new" value="1"
                    class="px-4 py-2.5 border-2 border-[#76a72b]/40 text-[#76a72b] hover:bg-[#76a72b]/10 rounded-xl text-sm font-semibold transition-all">
                    + otro
                </button>
                @endif
                <x-btn type="submit" class="flex-1">{{ $transaction->exists ? 'Guardar' : 'Registrar' }}</x-btn>
            </div>
        </form>
    </x-card>
</div>
<script>
function txForm(initialType) { return { type: initialType } }
</script>
</x-app-layout>
