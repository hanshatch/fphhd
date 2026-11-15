<x-app-layout title="Transferencias por conciliar">
<div class="max-w-3xl mx-auto">
    <x-page-header title="Transferencias por conciliar" :back="route('dashboard')" />

    @if($items->isEmpty())
    <x-card class="text-center py-14 px-6">
        <div class="mx-auto w-14 h-14 rounded-2xl bg-[#76a72b]/10 text-positive flex items-center justify-center mb-4">
            <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
        </div>
        <h2 class="text-lg font-bold text-[#373737] dark:text-white">Todo conciliado</h2>
        <p class="text-sm text-[#878787] mt-1.5 max-w-md mx-auto">
            Cuando importes un movimiento entre tus cuentas y no sepas a cuál fue, déjalo «Por conciliar».
            Al cargar la otra cuenta, FP une los dos lados solo.
        </p>
        <x-btn href="{{ route('accounts.index') }}" variant="secondary" class="mt-5 text-sm">Ir a mis cuentas</x-btn>
    </x-card>
    @else

    <div class="flex items-center justify-between gap-3 mb-3">
        <p class="text-sm text-[#878787]">
            <strong class="text-[#373737] dark:text-white">{{ $items->count() }}</strong>
            {{ $items->count() === 1 ? 'movimiento espera' : 'movimientos esperan' }} su otra cuenta.
            Ya afectan el saldo; no cuentan como ingreso ni gasto.
        </p>
        <form method="POST" action="{{ route('transfers.pending.auto') }}" class="flex-shrink-0">
            @csrf
            <x-btn type="submit" variant="secondary" class="text-sm">Conciliar solo</x-btn>
        </form>
    </div>

    <div class="space-y-3">
        @foreach($items as $item)
        @php
            $tx  = $item['tx'];
            $out = $tx->transfer_pending === 'out';
        @endphp
        <x-card class="p-4">
            <div class="flex items-start gap-3">
                <div class="w-9 h-9 rounded-xl flex items-center justify-center flex-shrink-0 bg-amber-50 dark:bg-amber-500/10 text-warn">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/></svg>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-semibold text-[#373737] dark:text-white truncate">{{ $tx->description }}</p>
                    <p class="text-xs text-[#878787]">
                        {{ $tx->date->translatedFormat('j M Y') }} ·
                        {{ $out ? 'Salió de' : 'Entró a' }} {{ $tx->account->displayLabel() }}
                    </p>
                </div>
                <span class="text-sm font-bold tabular-nums text-[#878787]">{{ $out ? '−' : '+' }}{{ format_currency($tx->amount) }}</span>
            </div>

            @if($item['candidates']->isNotEmpty())
            <div class="mt-3 space-y-1.5">
                <p class="text-[11px] font-bold text-[#878787] uppercase tracking-wider">{{ $item['candidates']->count() === 1 ? 'Posible otro lado' : 'Posibles otros lados' }}</p>
                @foreach($item['candidates'] as $c)
                <form method="POST" action="{{ route('transfers.pending.resolve', $tx) }}"
                      class="flex items-center gap-3 rounded-xl border border-[#ababab]/30 px-3 py-2">
                    @csrf
                    <input type="hidden" name="match_id" value="{{ $c['tx']->id }}">
                    <div class="flex-1 min-w-0">
                        <p class="text-xs font-semibold text-[#373737] dark:text-white truncate">
                            {{ $c['tx']->account->displayLabel() }} · {{ $c['tx']->date->translatedFormat('j M') }}
                        </p>
                        <p class="text-[11px] text-[#878787] truncate">
                            {{ $c['tx']->description }}{{ $c['pending'] ? '' : ' · registrado como ' . ($c['tx']->type === 'income' ? 'ingreso' : 'gasto') }}
                        </p>
                    </div>
                    <x-btn type="submit" class="text-xs min-h-[44px]">Es este</x-btn>
                </form>
                @endforeach
            </div>
            @endif

            <div class="mt-3 flex flex-wrap items-center gap-2">
                <form method="POST" action="{{ route('transfers.pending.resolve', $tx) }}" class="flex flex-1 min-w-[240px] items-center gap-2">
                    @csrf
                    <label for="acc-{{ $tx->id }}" class="text-xs text-[#878787] flex-shrink-0">{{ $out ? 'Fue hacia' : 'Vino de' }}</label>
                    <select id="acc-{{ $tx->id }}" name="account_id" required
                        class="w-full min-w-0 min-h-[44px] rounded-lg border border-[#ababab]/40 bg-[#efeded]/50 dark:bg-white/5 px-2 py-2 text-xs text-[#373737] dark:text-white focus:outline-none focus:ring-2 focus:ring-[#76a72b]">
                        <option value="">Elige la cuenta…</option>
                        @foreach($accounts->where('id', '<>', $tx->account_id) as $account)
                        <option value="{{ $account->id }}">{{ $account->displayLabel() }}</option>
                        @endforeach
                    </select>
                    <x-btn type="submit" variant="secondary" class="text-xs min-h-[44px]">Asignar</x-btn>
                </form>
                <form method="POST" action="{{ route('transfers.pending.resolve', $tx) }}"
                      data-confirm="«{{ $tx->description }}» dejará de ser transferencia y contará como {{ $out ? 'gasto' : 'ingreso' }}."
                      data-confirm-label="Convertir" data-confirm-title="No era entre mis cuentas">
                    @csrf
                    <input type="hidden" name="as_movement" value="1">
                    <x-btn type="submit" variant="ghost" class="text-xs min-h-[44px]">Es {{ $out ? 'gasto' : 'ingreso' }}</x-btn>
                </form>
            </div>
        </x-card>
        @endforeach
    </div>
    @endif
</div>
</x-app-layout>
