<x-app-layout :title="'Ajustar saldo — ' . $account->name">
<div class="max-w-md mx-auto">

    <x-page-header title="Ajustar saldo" :back="route('accounts.show', $account)" />

    {{-- Card informativa --}}
    <div class="rounded-2xl p-4 mb-5 flex items-center gap-4" style="background-color: {{ $account->color ?? '#76a72b' }}18; border: 1px solid {{ $account->color ?? '#76a72b' }}30">
        <div class="w-10 h-10 rounded-xl flex items-center justify-center flex-shrink-0"
             style="background-color: {{ $account->color ?? '#76a72b' }}30">
            <span class="font-bold text-sm" style="color: {{ $account->color ?? '#76a72b' }}">
                {{ mb_strtoupper(mb_substr($account->name, 0, 1)) }}
            </span>
        </div>
        <div>
            <p class="font-semibold text-[#373737] dark:text-white text-sm">{{ $account->name }}</p>
            <p class="text-xs text-[#878787] mt-0.5">
                Saldo actual: <span class="font-bold tabular-nums text-[#373737] dark:text-white">${{ number_format((float)$balance, 2) }}</span>
            </p>
        </div>
    </div>

    <x-card class="p-6">
        <p class="text-sm text-[#878787] mb-5">
            Ingresa el saldo que debería tener la cuenta. FP creará automáticamente un ingreso o egreso por la diferencia para cuadrar el saldo.
        </p>

        <form method="POST" action="{{ route('accounts.adjust.store', $account) }}" class="space-y-5">
            @csrf

            {{-- Saldo deseado --}}
            <div>
                <label class="block text-sm font-semibold text-[#373737] dark:text-white mb-1.5">
                    Saldo deseado <span class="text-[#76a72b]">*</span>
                </label>
                <div class="relative">
                    <span class="absolute left-4 top-1/2 -translate-y-1/2 text-[#878787] font-semibold text-lg">$</span>
                    <input type="text" name="target_balance" data-money inputmode="decimal" required
                        value="{{ old('target_balance', number_format((float)$balance, 2, '.', '')) }}"
                        class="w-full rounded-xl border border-[#ababab]/40 bg-[#efeded]/50 dark:bg-white/5 pl-9 pr-16 py-3 text-2xl font-bold text-[#373737] dark:text-white tabular-nums focus:outline-none focus:ring-2 focus:ring-[#76a72b] transition">
                    <span class="absolute right-4 top-1/2 -translate-y-1/2 text-[#ababab] text-xs font-semibold uppercase tracking-wider">MXN</span>
                </div>
                @error('target_balance')<p class="mt-1.5 text-xs text-red-500">{{ $message }}</p>@enderror
            </div>

            {{-- Fecha --}}
            <div>
                <label class="block text-sm font-semibold text-[#373737] dark:text-white mb-1.5">
                    Fecha del ajuste <span class="text-[#76a72b]">*</span>
                </label>
                <input type="date" name="date" required
                    value="{{ old('date', now()->format('Y-m-d')) }}"
                    class="w-full rounded-xl border border-[#ababab]/40 bg-[#efeded]/50 dark:bg-white/5 px-4 py-3 text-[#373737] dark:text-white focus:outline-none focus:ring-2 focus:ring-[#76a72b] transition">
            </div>

            {{-- Descripción --}}
            <div>
                <label class="block text-sm font-semibold text-[#373737] dark:text-white mb-1.5">Descripción</label>
                <input type="text" name="description" maxlength="255"
                    value="{{ old('description', 'Ajuste de saldo') }}"
                    class="w-full rounded-xl border border-[#ababab]/40 bg-[#efeded]/50 dark:bg-white/5 px-4 py-3 text-[#373737] dark:text-white placeholder-[#ababab] focus:outline-none focus:ring-2 focus:ring-[#76a72b] transition">
            </div>

            <div class="flex gap-3 pt-1">
                <x-btn variant="secondary" href="{{ route('accounts.show', $account) }}" class="flex-1">Cancelar</x-btn>
                <x-btn type="submit" class="flex-1">Ajustar saldo</x-btn>
            </div>
        </form>
    </x-card>
</div>
</x-app-layout>
