<x-app-layout title="Registrar ingreso">
<div class="max-w-lg mx-auto">

    <x-page-header title="Registrar ingreso recibido" :back="route('income-plans.index')" />

    {{-- Info del plan --}}
    <div class="bg-[#76a72b]/10 border border-[#76a72b]/20 rounded-xl p-4 mb-5">
        <div class="flex items-center gap-3">
            <div class="w-9 h-9 rounded-xl bg-[#76a72b]/20 flex items-center justify-center flex-shrink-0">
                <svg class="w-5 h-5 text-[#76a72b]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </div>
            <div>
                <p class="font-semibold text-[#373737] dark:text-white text-sm">{{ $plan->name }}</p>
                <p class="text-xs text-[#878787]">
                    Estimado: <span class="text-[#76a72b] font-bold">${{ number_format((float)$plan->expected_amount, 2) }}</span>
                    · {{ $plan->account->name }}
                    @if($plan->source) · {{ $plan->source->name }} @endif
                </p>
            </div>
        </div>
    </div>

    <x-card class="p-6">
        <form method="POST" action="{{ route('income-plans.register.store', $plan) }}" class="space-y-5">
            @csrf

            {{-- Monto real --}}
            <div>
                <label class="block text-sm font-semibold text-[#373737] dark:text-white mb-1.5">
                    Monto recibido <span class="text-[#76a72b]">*</span>
                </label>
                <div class="relative">
                    <span class="absolute left-4 top-1/2 -translate-y-1/2 text-[#878787] text-lg font-semibold">$</span>
                    <input type="number" name="amount" step="0.01" min="0.01" inputmode="decimal" required autofocus
                        value="{{ old('amount', $plan->expected_amount) }}"
                        class="w-full rounded-xl border-2 border-[#ababab]/30 bg-[#efeded]/50 dark:bg-white/5 pl-9 pr-16 py-4 text-2xl font-bold text-[#373737] dark:text-white focus:outline-none focus:border-[#76a72b] transition"
                        placeholder="0.00">
                    <span class="absolute right-4 top-1/2 -translate-y-1/2 text-[#ababab] text-xs font-bold uppercase tracking-widest">MXN</span>
                </div>
                <p class="mt-1 text-[10px] text-[#ababab]">Pre-llenado con el estimado — ajusta si el monto fue diferente</p>
                @error('amount')<p class="mt-1.5 text-xs text-red-500">{{ $message }}</p>@enderror
            </div>

            {{-- Fecha --}}
            <div>
                <label class="block text-sm font-semibold text-[#373737] dark:text-white mb-1.5">
                    Fecha de recepción <span class="text-[#76a72b]">*</span>
                </label>
                <input type="date" name="date" required
                    value="{{ old('date', now()->format('Y-m-d')) }}"
                    class="w-full rounded-xl border border-[#ababab]/40 bg-[#efeded]/50 dark:bg-white/5 px-4 py-3 text-[#373737] dark:text-white focus:outline-none focus:ring-2 focus:ring-[#76a72b] transition">
            </div>

            {{-- Descripción --}}
            <div>
                <label class="block text-sm font-semibold text-[#373737] dark:text-white mb-1.5">Descripción</label>
                <input type="text" name="description" maxlength="500"
                    value="{{ old('description', $plan->name) }}"
                    class="w-full rounded-xl border border-[#ababab]/40 bg-[#efeded]/50 dark:bg-white/5 px-4 py-3 text-[#373737] dark:text-white placeholder-[#ababab] focus:outline-none focus:ring-2 focus:ring-[#76a72b] transition">
            </div>

            <div class="flex gap-3 pt-2">
                <x-btn variant="secondary" href="{{ route('income-plans.index') }}" class="flex-1">Cancelar</x-btn>
                <x-btn type="submit" class="flex-1">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    Registrar ingreso
                </x-btn>
            </div>
        </form>
    </x-card>
</div>
</x-app-layout>
