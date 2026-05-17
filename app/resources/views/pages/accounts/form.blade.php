<x-app-layout :title="$account->exists ? 'Editar cuenta' : 'Nueva cuenta'">
    <div class="max-w-lg mx-auto">
        <x-page-header :title="$account->exists ? 'Editar cuenta' : 'Nueva cuenta'" :back="route('accounts.index')" />

        <x-card class="p-6">
            <form method="POST" action="{{ $account->exists ? route('accounts.update', $account) : route('accounts.store') }}" class="space-y-5">
                @csrf
                @if($account->exists) @method('PATCH') @endif

                {{-- Nombre --}}
                <div>
                    <label class="block text-sm font-semibold text-[#373737] dark:text-white mb-1.5">Nombre <span class="text-[#76a72b]">*</span></label>
                    <input type="text" name="name" value="{{ old('name', $account->name) }}" required autofocus
                        class="w-full rounded-xl border border-[#ababab]/40 bg-[#efeded]/50 dark:bg-white/5 px-4 py-3 text-[#373737] dark:text-white placeholder-[#ababab] focus:outline-none focus:ring-2 focus:ring-[#76a72b] focus:border-transparent transition"
                        placeholder="ej. Banamex débito">
                    @error('name')<p class="mt-1.5 text-xs text-red-500">{{ $message }}</p>@enderror
                </div>

                {{-- Tipo + Institución --}}
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-semibold text-[#373737] dark:text-white mb-1.5">Tipo <span class="text-[#76a72b]">*</span></label>
                        <select name="type" required
                            class="w-full rounded-xl border border-[#ababab]/40 bg-[#efeded]/50 dark:bg-white/5 px-4 py-3 text-[#373737] dark:text-white focus:outline-none focus:ring-2 focus:ring-[#76a72b] transition">
                            @foreach(['debit' => 'Débito', 'credit' => 'TDC', 'savings' => 'Caja de ahorro', 'investment' => 'Inversión', 'cash' => 'Efectivo'] as $val => $label)
                                <option value="{{ $val }}" {{ old('type', $account->type) === $val ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-[#373737] dark:text-white mb-1.5">Institución <span class="text-[#76a72b]">*</span></label>
                        <select name="institution" required
                            class="w-full rounded-xl border border-[#ababab]/40 bg-[#efeded]/50 dark:bg-white/5 px-4 py-3 text-[#373737] dark:text-white focus:outline-none focus:ring-2 focus:ring-[#76a72b] transition">
                            @foreach(['banamex' => 'Banamex', 'mercadopago' => 'MercadoPago', 'nu' => 'Nu', 'revolut' => 'Revolut', 'amex' => 'American Express', 'other' => 'Otra'] as $val => $label)
                                <option value="{{ $val }}" {{ old('institution', $account->institution) === $val ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                {{-- Saldo inicial --}}
                <div>
                    <label class="block text-sm font-semibold text-[#373737] dark:text-white mb-1.5">Saldo inicial <span class="text-[#76a72b]">*</span></label>
                    <div class="relative">
                        <span class="absolute left-4 top-1/2 -translate-y-1/2 text-[#878787] font-semibold">$</span>
                        <input type="number" name="initial_balance" step="0.01" min="0" inputmode="decimal" required
                            value="{{ old('initial_balance', $account->initial_balance ?? '0.00') }}"
                            class="w-full rounded-xl border border-[#ababab]/40 bg-[#efeded]/50 dark:bg-white/5 pl-8 pr-16 py-3 text-[#373737] dark:text-white focus:outline-none focus:ring-2 focus:ring-[#76a72b] transition">
                        <span class="absolute right-4 top-1/2 -translate-y-1/2 text-[#ababab] text-xs font-semibold uppercase tracking-wider">MXN</span>
                    </div>
                    @error('initial_balance')<p class="mt-1.5 text-xs text-red-500">{{ $message }}</p>@enderror
                </div>

                {{-- APR --}}
                <div>
                    <label class="block text-sm font-semibold text-[#373737] dark:text-white mb-1.5">APR nominal anual (%)</label>
                    <input type="number" name="invest_apr" step="0.01" min="0" max="100" inputmode="decimal"
                        value="{{ old('invest_apr', $account->invest_apr) }}"
                        placeholder="Solo cajas de ahorro · ej. 13.00"
                        class="w-full rounded-xl border border-[#ababab]/40 bg-[#efeded]/50 dark:bg-white/5 px-4 py-3 text-[#373737] dark:text-white placeholder-[#ababab] focus:outline-none focus:ring-2 focus:ring-[#76a72b] transition">
                </div>

                {{-- Color --}}
                <div>
                    <label class="block text-sm font-semibold text-[#373737] dark:text-white mb-1.5">Color identificador</label>
                    <div class="flex items-center gap-3">
                        <input type="color" name="color" value="{{ old('color', $account->color ?? '#76a72b') }}"
                            class="h-11 w-20 rounded-xl border border-[#ababab]/40 cursor-pointer p-1 bg-white">
                        <span class="text-sm text-[#878787]">Elige el color que identifica esta cuenta en la UI</span>
                    </div>
                </div>

                @if($account->exists)
                <div class="flex items-center gap-3 p-3 rounded-xl bg-[#efeded]/50 dark:bg-white/5">
                    <input type="checkbox" name="is_active" id="is_active" value="1" {{ $account->is_active ? 'checked' : '' }}
                        class="w-4 h-4 text-[#76a72b] rounded border-[#ababab] focus:ring-[#76a72b]">
                    <label for="is_active" class="text-sm text-[#373737] dark:text-white font-medium">Cuenta activa</label>
                </div>
                @endif

                <div>
                    <label class="block text-sm font-semibold text-[#373737] dark:text-white mb-1.5">Notas</label>
                    <textarea name="notes" rows="2" maxlength="500" placeholder="Opcional"
                        class="w-full rounded-xl border border-[#ababab]/40 bg-[#efeded]/50 dark:bg-white/5 px-4 py-3 text-[#373737] dark:text-white placeholder-[#ababab] focus:outline-none focus:ring-2 focus:ring-[#76a72b] transition resize-none">{{ old('notes', $account->notes) }}</textarea>
                </div>

                <div class="flex gap-3 pt-2">
                    <x-btn variant="secondary" href="{{ route('accounts.index') }}" class="flex-1">Cancelar</x-btn>
                    <x-btn type="submit" class="flex-1">{{ $account->exists ? 'Guardar cambios' : 'Crear cuenta' }}</x-btn>
                </div>
            </form>
        </x-card>
    </div>
</x-app-layout>
