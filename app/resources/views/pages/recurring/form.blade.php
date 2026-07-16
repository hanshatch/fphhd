<x-app-layout :title="$charge->exists ? 'Editar cargo' : 'Nuevo cargo recurrente'">
<div class="max-w-lg mx-auto"
     x-data="{ isMsi: {{ $charge->is_msi ? 'true' : 'false' }} }">

    <x-page-header :title="$charge->exists ? 'Editar cargo recurrente' : 'Nuevo cargo recurrente'" :back="route('recurring.index')" />

    <x-card class="p-6">
        <form method="POST"
              action="{{ $charge->exists ? route('recurring.update', $charge) : route('recurring.store') }}"
              class="space-y-5">
            @csrf
            @if($charge->exists) @method('PATCH') @endif

            {{-- Tipo de cargo --}}
            <div class="grid grid-cols-2 gap-3">
                <label class="cursor-pointer">
                    <input type="radio" name="is_msi" value="0" x-model="isMsi"
                           {{ ! $charge->is_msi ? 'checked' : '' }} class="sr-only">
                    <div :class="!isMsi ? 'border-[#76a72b] bg-[#76a72b]/10' : 'border-[#ababab]/30'"
                         class="border-2 rounded-xl p-4 text-center transition-all">
                        <div class="text-xl mb-1">🔄</div>
                        <div class="text-sm font-semibold text-[#373737] dark:text-white">Recurrente</div>
                        <div class="text-[10px] text-[#ababab] mt-0.5">Sin fecha fin o con fecha límite</div>
                    </div>
                </label>
                <label class="cursor-pointer">
                    <input type="radio" name="is_msi" value="1" x-model="isMsi"
                           {{ $charge->is_msi ? 'checked' : '' }} class="sr-only">
                    <div :class="isMsi ? 'border-[#76a72b] bg-[#76a72b]/10' : 'border-[#ababab]/30'"
                         class="border-2 rounded-xl p-4 text-center transition-all">
                        <div class="text-xl mb-1">💳</div>
                        <div class="text-sm font-semibold text-[#373737] dark:text-white">MSI</div>
                        <div class="text-[10px] text-[#ababab] mt-0.5">Meses sin intereses</div>
                    </div>
                </label>
            </div>

            {{-- Nombre --}}
            <div>
                <label class="block text-sm font-semibold text-[#373737] dark:text-white mb-1.5">
                    Nombre <span class="text-[#76a72b]">*</span>
                </label>
                <input type="text" name="name" value="{{ old('name', $charge->name) }}" required autofocus
                    :placeholder="isMsi ? 'ej. iPhone 16 Pro MSI' : 'ej. Netflix, Spotify, Renta'"
                    class="w-full rounded-xl border border-[#ababab]/40 bg-[#efeded]/50 dark:bg-white/5 px-4 py-3 text-[#373737] dark:text-white placeholder-[#ababab] focus:outline-none focus:ring-2 focus:ring-[#76a72b] transition">
                @error('name')<p class="mt-1.5 text-xs text-red-500">{{ $message }}</p>@enderror
            </div>

            {{-- MSI: monto original + número de cuotas --}}
            <div x-show="isMsi" x-cloak class="space-y-4 p-4 bg-blue-50/50 dark:bg-blue-500/5 border border-blue-100 dark:border-blue-500/20 rounded-xl">
                <p class="text-xs font-bold text-blue-500 uppercase tracking-wider">Detalles MSI</p>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-semibold text-[#373737] dark:text-white mb-1.5">Precio total</label>
                        <div class="relative">
                            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-[#878787]">$</span>
                            <input type="number" name="original_amount" step="0.01" min="0" inputmode="decimal"
                                value="{{ old('original_amount', $charge->original_amount) }}"
                                placeholder="ej. 24000.00"
                                class="w-full rounded-xl border border-[#ababab]/40 bg-white dark:bg-white/5 pl-7 pr-3 py-3 text-[#373737] dark:text-white focus:outline-none focus:ring-2 focus:ring-[#76a72b] transition">
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-[#373737] dark:text-white mb-1.5">
                            Número de meses <span class="text-[#76a72b]">*</span>
                        </label>
                        <input type="number" name="total_installments" min="2" max="360" inputmode="numeric"
                            value="{{ old('total_installments', $charge->total_installments) }}"
                            placeholder="ej. 12"
                            :required="isMsi"
                            class="w-full rounded-xl border border-[#ababab]/40 bg-white dark:bg-white/5 px-4 py-3 text-[#373737] dark:text-white focus:outline-none focus:ring-2 focus:ring-[#76a72b] transition">
                        <p class="mt-1 text-[10px] text-[#ababab]">La fecha fin se calcula automáticamente</p>
                    </div>
                </div>
            </div>

            {{-- Cuenta + Categoría --}}
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-semibold text-[#373737] dark:text-white mb-1.5">
                        Cuenta <span class="text-[#76a72b]">*</span>
                    </label>
                    <select name="account_id" required
                        class="w-full rounded-xl border border-[#ababab]/40 bg-[#efeded]/50 dark:bg-white/5 px-4 py-3 text-[#373737] dark:text-white focus:outline-none focus:ring-2 focus:ring-[#76a72b] transition">
                        <option value="">Selecciona</option>
                        @foreach($accounts as $account)
                            <option value="{{ $account->id }}" {{ old('account_id', $charge->account_id) == $account->id ? 'selected' : '' }}>
                                {{ $account->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-[#373737] dark:text-white mb-1.5">Tipo</label>
                    <select name="type" required
                        class="w-full rounded-xl border border-[#ababab]/40 bg-[#efeded]/50 dark:bg-white/5 px-4 py-3 text-[#373737] dark:text-white focus:outline-none focus:ring-2 focus:ring-[#76a72b] transition">
                        <option value="expense" {{ old('type', $charge->type ?? 'expense') === 'expense' ? 'selected' : '' }}>💸 Egreso</option>
                        <option value="income"  {{ old('type', $charge->type) === 'income'  ? 'selected' : '' }}>💰 Ingreso</option>
                    </select>
                </div>
            </div>

            {{-- Monto mensual --}}
            <div>
                <label class="block text-sm font-semibold text-[#373737] dark:text-white mb-1.5">
                    <span x-text="isMsi ? 'Cuota mensual' : 'Monto mensual'"></span>
                    <span class="text-[#76a72b]">*</span>
                </label>
                <div class="relative">
                    <span class="absolute left-4 top-1/2 -translate-y-1/2 text-[#878787] font-semibold">$</span>
                    <input type="number" name="amount" step="0.01" min="0.01" inputmode="decimal" required
                        value="{{ old('amount', $charge->amount) }}"
                        class="w-full rounded-xl border border-[#ababab]/40 bg-[#efeded]/50 dark:bg-white/5 pl-8 pr-16 py-3 text-[#373737] dark:text-white focus:outline-none focus:ring-2 focus:ring-[#76a72b] transition"
                        placeholder="0.00">
                    <span class="absolute right-4 top-1/2 -translate-y-1/2 text-[#ababab] text-xs font-semibold uppercase tracking-wider">MXN</span>
                </div>
                @error('amount')<p class="mt-1.5 text-xs text-red-500">{{ $message }}</p>@enderror
            </div>

            {{-- Categoría --}}
            <div>
                <label class="block text-sm font-semibold text-[#373737] dark:text-white mb-1.5">Categoría</label>
                <x-category-picker :categories="$categories" :selected="$charge->category_id" />
            </div>

            {{-- Día de cargo + Fecha inicio --}}
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-semibold text-[#373737] dark:text-white mb-1.5">
                        Día del mes <span class="text-[#76a72b]">*</span>
                    </label>
                    <input type="number" name="day_of_month" min="1" max="31" inputmode="numeric" required
                        value="{{ old('day_of_month', $charge->day_of_month) }}"
                        placeholder="ej. 15"
                        class="w-full rounded-xl border border-[#ababab]/40 bg-[#efeded]/50 dark:bg-white/5 px-4 py-3 text-[#373737] dark:text-white focus:outline-none focus:ring-2 focus:ring-[#76a72b] transition">
                    <p class="mt-1 text-[10px] text-[#ababab]">Día en que se aplica cada mes</p>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-[#373737] dark:text-white mb-1.5">
                        Fecha inicio <span class="text-[#76a72b]">*</span>
                    </label>
                    <input type="date" name="start_date" required
                        value="{{ old('start_date', $charge->start_date?->format('Y-m-d') ?? now()->format('Y-m-d')) }}"
                        class="w-full rounded-xl border border-[#ababab]/40 bg-[#efeded]/50 dark:bg-white/5 px-4 py-3 text-[#373737] dark:text-white focus:outline-none focus:ring-2 focus:ring-[#76a72b] transition">
                </div>
            </div>

            {{-- Fecha fin (solo recurrente, no MSI) --}}
            <div x-show="!isMsi" x-cloak>
                <label class="block text-sm font-semibold text-[#373737] dark:text-white mb-1.5">Fecha fin</label>
                <input type="date" name="end_date"
                    value="{{ old('end_date', $charge->end_date?->format('Y-m-d')) }}"
                    class="w-full rounded-xl border border-[#ababab]/40 bg-[#efeded]/50 dark:bg-white/5 px-4 py-3 text-[#373737] dark:text-white focus:outline-none focus:ring-2 focus:ring-[#76a72b] transition">
                <p class="mt-1 text-[10px] text-[#ababab]">Opcional — vacío = indefinido</p>
            </div>

            {{-- Notas --}}
            <div>
                <label class="block text-sm font-semibold text-[#373737] dark:text-white mb-1.5">Notas</label>
                <textarea name="notes" rows="2" maxlength="500" placeholder="Opcional"
                    class="w-full rounded-xl border border-[#ababab]/40 bg-[#efeded]/50 dark:bg-white/5 px-4 py-3 text-[#373737] dark:text-white placeholder-[#ababab] focus:outline-none focus:ring-2 focus:ring-[#76a72b] transition resize-none">{{ old('notes', $charge->notes) }}</textarea>
            </div>

            <div class="flex gap-3 pt-2">
                <x-btn variant="secondary" href="{{ route('recurring.index') }}" class="flex-1">Cancelar</x-btn>
                <x-btn type="submit" class="flex-1">{{ $charge->exists ? 'Guardar' : 'Crear cargo' }}</x-btn>
            </div>
        </form>
    </x-card>
</div>
</x-app-layout>
