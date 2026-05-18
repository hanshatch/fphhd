<x-app-layout :title="$plan->exists ? 'Editar ingreso esperado' : 'Nuevo ingreso esperado'">
<div class="max-w-lg mx-auto"
     x-data="{ freq: '{{ old('frequency', $plan->frequency ?? 'biweekly') }}' }"
>

    <x-page-header :title="$plan->exists ? 'Editar ingreso' : 'Nuevo ingreso esperado'" :back="route('income-plans.index')" />

    <x-card class="p-6">
        <form method="POST"
              action="{{ $plan->exists ? route('income-plans.update', $plan) : route('income-plans.store') }}"
              class="space-y-5">
            @csrf
            @if($plan->exists) @method('PATCH') @endif

            {{-- Nombre --}}
            <div>
                <label class="block text-sm font-semibold text-[#373737] dark:text-white mb-1.5">
                    Nombre <span class="text-[#76a72b]">*</span>
                </label>
                <input type="text" name="name" value="{{ old('name', $plan->name) }}" required autofocus
                    placeholder="ej. Agencia — quincena, UNAM — mensual"
                    class="w-full rounded-xl border border-[#ababab]/40 bg-[#efeded]/50 dark:bg-white/5 px-4 py-3 text-[#373737] dark:text-white placeholder-[#ababab] focus:outline-none focus:ring-2 focus:ring-[#76a72b] transition">
                @error('name')<p class="mt-1.5 text-xs text-red-500">{{ $message }}</p>@enderror
            </div>

            {{-- Frecuencia --}}
            <div>
                <label class="block text-sm font-semibold text-[#373737] dark:text-white mb-2">
                    Frecuencia <span class="text-[#76a72b]">*</span>
                </label>
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
                    @foreach(['once' => ['⚡', 'Único', 'Un solo pago'], 'biweekly' => ['🗓️', 'Quincenal', 'Cada 15 días'], 'monthly' => ['📅', 'Mensual', 'Una vez al mes'], 'weekly' => ['📆', 'Semanal', 'Cada semana']] as $val => [$emoji, $label, $sub])
                    <label class="cursor-pointer">
                        <input type="radio" name="frequency" value="{{ $val }}" x-model="freq" class="sr-only"
                            {{ old('frequency', $plan->frequency ?? 'biweekly') === $val ? 'checked' : '' }}>
                        <div :class="freq === '{{ $val }}' ? 'border-[#76a72b] bg-[#76a72b]/10' : 'border-[#ababab]/30'"
                             class="border-2 rounded-xl p-3 text-center transition-all cursor-pointer">
                            <div class="text-xl mb-1">{{ $emoji }}</div>
                            <div class="text-xs font-bold text-[#373737] dark:text-white">{{ $label }}</div>
                            <div class="text-[10px] text-[#ababab] mt-0.5">{{ $sub }}</div>
                        </div>
                    </label>
                    @endforeach
                </div>
            </div>

            {{-- Días del mes (quincenal/mensual) --}}
            <div x-show="freq !== 'weekly' && freq !== 'once'" x-cloak>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-semibold text-[#373737] dark:text-white mb-1.5">
                            <span x-text="freq === 'biweekly' ? 'Día 1 del mes' : 'Día del mes'"></span>
                        </label>
                        <input type="number" name="day_1" min="1" max="31" inputmode="numeric"
                            value="{{ old('day_1', $plan->day_1) }}"
                            placeholder="ej. 15"
                            class="w-full rounded-xl border border-[#ababab]/40 bg-[#efeded]/50 dark:bg-white/5 px-4 py-3 text-[#373737] dark:text-white focus:outline-none focus:ring-2 focus:ring-[#76a72b] transition">
                    </div>
                    <div x-show="freq === 'biweekly'" x-cloak>
                        <label class="block text-sm font-semibold text-[#373737] dark:text-white mb-1.5">Día 2 del mes</label>
                        <input type="number" name="day_2" min="1" max="31" inputmode="numeric"
                            value="{{ old('day_2', $plan->day_2) }}"
                            placeholder="ej. 30"
                            class="w-full rounded-xl border border-[#ababab]/40 bg-[#efeded]/50 dark:bg-white/5 px-4 py-3 text-[#373737] dark:text-white focus:outline-none focus:ring-2 focus:ring-[#76a72b] transition">
                        <p class="mt-1 text-[10px] text-[#ababab]">Días 15 y 30 = cobros quincenales</p>
                    </div>
                </div>
            </div>

            {{-- Monto estimado --}}
            <div>
                <label class="block text-sm font-semibold text-[#373737] dark:text-white mb-1.5">
                    Monto estimado <span class="text-[#76a72b]">*</span>
                </label>
                <div class="relative">
                    <span class="absolute left-4 top-1/2 -translate-y-1/2 text-[#878787] font-semibold">$</span>
                    <input type="number" name="expected_amount" step="0.01" min="0.01" inputmode="decimal" required
                        value="{{ old('expected_amount', $plan->expected_amount) }}"
                        placeholder="Aproximado — lo ajustas al registrar"
                        class="w-full rounded-xl border border-[#ababab]/40 bg-[#efeded]/50 dark:bg-white/5 pl-8 pr-16 py-3 text-[#373737] dark:text-white focus:outline-none focus:ring-2 focus:ring-[#76a72b] transition">
                    <span class="absolute right-4 top-1/2 -translate-y-1/2 text-[#ababab] text-xs font-semibold uppercase tracking-wider">MXN</span>
                </div>
                <p class="mt-1 text-[10px] text-[#ababab]">Solo para planeación — el monto real lo ingresas cuando llega el pago</p>
                @error('expected_amount')<p class="mt-1.5 text-xs text-red-500">{{ $message }}</p>@enderror
            </div>

            {{-- Cuenta + Fuente --}}
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-semibold text-[#373737] dark:text-white mb-1.5">
                        Cuenta de depósito <span class="text-[#76a72b]">*</span>
                    </label>
                    <select name="account_id" required
                        class="w-full rounded-xl border border-[#ababab]/40 bg-[#efeded]/50 dark:bg-white/5 px-4 py-3 text-[#373737] dark:text-white focus:outline-none focus:ring-2 focus:ring-[#76a72b] transition">
                        <option value="">Selecciona</option>
                        @foreach($accounts as $account)
                            <option value="{{ $account->id }}" {{ old('account_id', $plan->account_id) == $account->id ? 'selected' : '' }}>
                                {{ $account->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-[#373737] dark:text-white mb-1.5">Fuente</label>
                    <select name="source_id"
                        class="w-full rounded-xl border border-[#ababab]/40 bg-[#efeded]/50 dark:bg-white/5 px-4 py-3 text-[#373737] dark:text-white focus:outline-none focus:ring-2 focus:ring-[#76a72b] transition">
                        <option value="">Sin fuente</option>
                        @foreach($sources as $source)
                            <option value="{{ $source->id }}" {{ old('source_id', $plan->source_id) == $source->id ? 'selected' : '' }}>
                                {{ $source->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>

            {{-- Próxima fecha esperada --}}
            <div>
                <label class="block text-sm font-semibold text-[#373737] dark:text-white mb-1.5">
                    <span x-text="freq === 'once' ? 'Fecha esperada del pago' : 'Próxima fecha esperada'"></span>
                    <span class="text-[#76a72b]">*</span>
                </label>
                <input type="date" name="next_expected_date" required
                    value="{{ old('next_expected_date', $plan->next_expected_date?->format('Y-m-d') ?? now()->format('Y-m-d')) }}"
                    class="w-full rounded-xl border border-[#ababab]/40 bg-[#efeded]/50 dark:bg-white/5 px-4 py-3 text-[#373737] dark:text-white focus:outline-none focus:ring-2 focus:ring-[#76a72b] transition">
                <p class="mt-1 text-[10px] text-[#ababab]"
                   x-text="freq === 'once' ? 'Al registrar el pago, este ingreso se marca como completado.' : 'La fecha se avanza automáticamente cada vez que registras un pago'">
                </p>
            </div>

            {{-- Notas --}}
            <div>
                <label class="block text-sm font-semibold text-[#373737] dark:text-white mb-1.5">Notas</label>
                <textarea name="notes" rows="2" maxlength="500" placeholder="Opcional"
                    class="w-full rounded-xl border border-[#ababab]/40 bg-[#efeded]/50 dark:bg-white/5 px-4 py-3 text-[#373737] dark:text-white placeholder-[#ababab] focus:outline-none focus:ring-2 focus:ring-[#76a72b] transition resize-none">{{ old('notes', $plan->notes) }}</textarea>
            </div>

            <div class="flex gap-3 pt-2">
                <x-btn variant="secondary" href="{{ route('income-plans.index') }}" class="flex-1">Cancelar</x-btn>
                <x-btn type="submit" class="flex-1">{{ $plan->exists ? 'Guardar' : 'Crear' }}</x-btn>
            </div>
        </form>
    </x-card>
</div>
</x-app-layout>
