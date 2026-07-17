<x-app-layout title="Aplicar cargo">
<div class="max-w-lg mx-auto">

    <x-page-header title="Aplicar cargo recurrente" :back="route('recurring.index')" />

    {{-- Info del cargo --}}
    <div class="bg-red-50 dark:bg-red-500/10 border border-red-100 dark:border-red-500/20 rounded-xl p-4 mb-5">
        <div class="flex items-center gap-3">
            <div class="w-9 h-9 rounded-xl bg-red-100 dark:bg-red-500/20 flex items-center justify-center flex-shrink-0">
                <svg class="w-5 h-5 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
            </div>
            <div>
                <p class="font-semibold text-[#373737] dark:text-white text-sm">
                    {{ $charge->name }}
                    @if($charge->is_msi)
                        <span class="ml-1 text-[10px] bg-blue-50 dark:bg-blue-500/10 text-blue-600 dark:text-blue-400 border border-blue-200 dark:border-blue-500/30 px-2 py-0.5 rounded-full font-bold">
                            Cuota {{ $charge->applied_installments + 1 }} de {{ $charge->total_installments }}
                        </span>
                    @endif
                </p>
                <p class="text-xs text-[#878787]">
                    Configurado: <span class="text-red-500 font-bold">${{ number_format((float)$charge->amount, 2) }}</span>
                    · {{ $charge->account->displayLabel() }}
                    @if($charge->category) · {{ $charge->category->name }} @endif
                    · vence {{ $charge->next_application_date->translatedFormat('j M Y') }}
                </p>
            </div>
        </div>
    </div>

    <x-card class="p-6">
        <form method="POST" action="{{ route('recurring.apply.store', $charge) }}" class="space-y-5">
            @csrf

            {{-- Monto final --}}
            <div>
                <label class="block text-sm font-semibold text-[#373737] dark:text-white mb-1.5">
                    Monto final a aplicar <span class="text-[#76a72b]">*</span>
                </label>
                <div class="relative">
                    <span class="absolute left-4 top-1/2 -translate-y-1/2 text-[#878787] text-lg font-semibold">$</span>
                    <input type="text" name="amount" data-money inputmode="decimal" required autofocus
                        value="{{ old('amount', $charge->amount) }}"
                        class="w-full rounded-xl border-2 border-[#ababab]/30 bg-[#efeded]/50 dark:bg-white/5 pl-9 pr-16 py-4 text-2xl font-bold text-[#373737] dark:text-white focus:outline-none focus:border-[#76a72b] transition"
                        placeholder="0.00">
                    <span class="absolute right-4 top-1/2 -translate-y-1/2 text-[#ababab] text-xs font-bold uppercase tracking-widest">MXN</span>
                </div>
                <p class="mt-1 text-[10px] text-[#ababab]">Pre-llenado con lo configurado — ajústalo si el cargo en USD varió con el tipo de cambio</p>
                @error('amount')<p class="mt-1.5 text-xs text-red-500">{{ $message }}</p>@enderror
            </div>

            {{-- Fecha --}}
            <div>
                <label class="block text-sm font-semibold text-[#373737] dark:text-white mb-1.5">
                    Fecha del cobro <span class="text-[#76a72b]">*</span>
                </label>
                <input type="date" name="date" required
                    value="{{ old('date', now()->format('Y-m-d')) }}"
                    class="w-full rounded-xl border border-[#ababab]/40 bg-[#efeded]/50 dark:bg-white/5 px-4 py-3 text-[#373737] dark:text-white focus:outline-none focus:ring-2 focus:ring-[#76a72b] transition">
                @error('date')<p class="mt-1.5 text-xs text-red-500">{{ $message }}</p>@enderror
            </div>

            <div class="flex gap-3 pt-2">
                <x-btn variant="secondary" href="{{ route('recurring.index') }}" class="flex-1">Cancelar</x-btn>
                <x-btn type="submit" class="flex-1">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    Aplicar a la cuenta
                </x-btn>
            </div>
        </form>
    </x-card>
</div>
</x-app-layout>
