<x-app-layout :title="$goal->name">

@php
    $s      = $summary;
    $input  = 'w-full rounded-xl border border-[#ababab]/40 bg-[#efeded]/50 dark:bg-white/5 px-4 py-3 text-[#373737] dark:text-white placeholder-[#ababab] focus:outline-none focus:ring-2 focus:ring-[#76a72b] transition';
    $days   = (int) now()->startOfDay()->diffInDays($goal->target_date, false);
    $when   = match (true) {
        $goal->isCompleted() => 'Realizada el ' . $goal->completed_at?->translatedFormat('j \d\e F \d\e Y'),
        $days < 0            => 'Venció hace ' . abs($days) . ' ' . (abs($days) === 1 ? 'día' : 'días'),
        $days === 0          => 'Es hoy',
        $days < 45           => 'Faltan ' . $days . ' ' . ($days === 1 ? 'día' : 'días'),
        default              => 'Faltan ' . (int) round($days / 30.4) . ' meses',
    };
    $plan = $goal->isCompleted() || bccomp($s['remaining'], '0', 2) === 0
        ? []
        : app(\App\Services\GoalService::class)->schedule(collect([$goal]), null, 1, 24);
@endphp

<x-page-header :title="$goal->name" :back="route('goals.index')" />

{{-- ── Tarjeta principal ────────────────────────────────────────── --}}
<div class="relative overflow-hidden rounded-2xl p-5 lg:p-6 text-white shadow-lg mb-4"
     style="background: linear-gradient(135deg, {{ $goal->color }} 0%, color-mix(in srgb, {{ $goal->color }} 65%, #000) 100%)">
    <div class="pointer-events-none absolute -right-12 -top-16 w-52 h-52 rounded-full bg-white/10"></div>

    <div class="relative flex items-start gap-3">
        <div class="w-12 h-12 rounded-xl bg-white/20 flex items-center justify-center flex-shrink-0">
            <x-goal-kind-icon :kind="$goal->kind" class="w-6 h-6" />
        </div>
        <div class="flex-1 min-w-0">
            <p class="text-white/80 text-xs font-semibold uppercase tracking-wider">{{ $goal->kindLabel() }}</p>
            <p class="font-bold text-lg leading-tight">{{ $goal->target_date->translatedFormat('j \d\e F \d\e Y') }}</p>
            <p class="text-white/80 text-sm">{{ $when }}</p>
        </div>
        <x-goal-status :status="$s['status']" class="!bg-white/90 flex-shrink-0" />
    </div>

    <div class="relative mt-6 grid grid-cols-1 sm:grid-cols-3 gap-4 items-end">
        <div class="sm:col-span-2">
            <p class="text-white/80 text-[11px] font-semibold uppercase tracking-wider">Apartado</p>
            <p class="text-[34px] leading-none font-bold tabular-nums mt-1">
                {{ format_currency($s['saved']) }}
                <span class="text-base font-normal text-white/80">de {{ format_currency($goal->target_amount) }}</span>
            </p>
            <div class="mt-3 h-2.5 rounded-full bg-white/20 overflow-hidden"
                 role="progressbar" aria-valuenow="{{ $s['pct'] }}" aria-valuemin="0" aria-valuemax="100" aria-label="Avance">
                <div class="h-full rounded-full bg-white" style="width: {{ $s['pct'] }}%"></div>
            </div>
        </div>
        <div class="sm:text-right">
            @if(! $goal->isCompleted() && bccomp($s['remaining'], '0', 2) > 0)
            <p class="text-white/80 text-[11px] font-semibold uppercase tracking-wider">Aparta al mes</p>
            <p class="text-2xl font-bold tabular-nums">{{ format_currency($s['quota']) }}</p>
            <p class="text-white/80 text-xs tabular-nums">te faltan {{ format_currency($s['remaining']) }}</p>
            @elseif(! $goal->isCompleted())
            <p class="text-lg font-bold">Ya tienes todo</p>
            <p class="text-white/80 text-xs">márcala como realizada cuando la pagues</p>
            @endif
        </div>
    </div>
</div>

{{-- ── Acciones ─────────────────────────────────────────────────── --}}
<div class="flex flex-wrap gap-2 mb-6">
    @if($goal->isCompleted())
        <form method="POST" action="{{ route('goals.reopen', $goal) }}">
            @csrf
            <x-btn type="submit" variant="secondary">Reactivar meta</x-btn>
        </form>
    @else
        <x-btn href="#apartar" variant="primary">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/></svg>
            Apartar dinero
        </x-btn>
        <form method="POST" action="{{ route('goals.complete', $goal) }}"
              data-confirm-title="Marcar como realizada"
              data-confirm="«{{ $goal->name }}» pasará a Realizadas y ya no contará en tu plan mensual."
              data-confirm-label="Marcar realizada">
            @csrf
            <x-btn type="submit" variant="secondary">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                Marcar como realizada
            </x-btn>
        </form>
    @endif
    <x-btn href="{{ route('goals.edit', $goal) }}" variant="secondary">Editar</x-btn>
    <form method="POST" action="{{ route('goals.destroy', $goal) }}" class="ml-auto"
          data-confirm="¿Eliminar «{{ $goal->name }}» y su historial de apartados? Tus cuentas no se modifican."
          data-confirm-label="Eliminar">
        @csrf @method('DELETE')
        <x-btn type="submit" variant="ghost" class="!text-negative">Eliminar</x-btn>
    </form>
</div>

<div class="lg:grid lg:grid-cols-[minmax(0,1fr)_340px] lg:gap-6 lg:items-start space-y-6 lg:space-y-0">

    <div class="space-y-6">
        {{-- ── Apartar / retirar ─────────────────────────────────── --}}
        @unless($goal->isCompleted())
        <section id="apartar" class="scroll-mt-6">
            <h2 class="text-xs font-bold text-[#878787] uppercase tracking-wider mb-2">Apartar dinero</h2>
            <x-card class="p-5">
                <form method="POST" action="{{ route('goals.contribute', $goal) }}" x-data="{ direction: 'add' }" class="space-y-4">
                    @csrf
                    <div class="grid grid-cols-2 gap-2" role="radiogroup" aria-label="Tipo de movimiento">
                        <label class="cursor-pointer">
                            <input type="radio" name="direction" value="add" x-model="direction" class="sr-only peer">
                            <span class="block text-center py-2.5 rounded-xl border-2 text-sm font-semibold transition-colors peer-focus-visible:ring-2 peer-focus-visible:ring-[#76a72b]"
                                  x-bind:class="direction === 'add' ? 'border-[#76a72b] bg-[#76a72b]/10 text-positive' : 'border-[#ababab]/30 text-[#878787]'">Aparté</span>
                        </label>
                        <label class="cursor-pointer">
                            <input type="radio" name="direction" value="withdraw" x-model="direction" class="sr-only peer">
                            <span class="block text-center py-2.5 rounded-xl border-2 text-sm font-semibold transition-colors peer-focus-visible:ring-2 peer-focus-visible:ring-[#76a72b]"
                                  x-bind:class="direction === 'withdraw' ? 'border-red-400 bg-red-50 dark:bg-red-500/10 text-negative' : 'border-[#ababab]/30 text-[#878787]'">Retiré</span>
                        </label>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label for="amount" class="block text-sm font-semibold text-[#373737] dark:text-white mb-1.5">Monto <span class="text-positive">*</span></label>
                            <div class="relative">
                                <span class="absolute left-4 top-1/2 -translate-y-1/2 text-[#878787] font-semibold">$</span>
                                <input id="amount" type="text" name="amount" data-money inputmode="decimal" required
                                       value="{{ old('amount', bccomp($s['quota'], '0', 2) > 0 ? format_currency($s['quota'], false) : '') }}"
                                       placeholder="0.00" class="{{ $input }} pl-8 font-bold tabular-nums">
                            </div>
                            @error('amount')<p class="mt-1.5 text-xs text-negative">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label for="date" class="block text-sm font-semibold text-[#373737] dark:text-white mb-1.5">Fecha <span class="text-positive">*</span></label>
                            <input id="date" type="date" name="date" required value="{{ old('date', now()->format('Y-m-d')) }}" class="{{ $input }}">
                        </div>
                    </div>

                    <div>
                        <label for="note" class="block text-sm font-semibold text-[#373737] dark:text-white mb-1.5">Nota</label>
                        <input id="note" type="text" name="note" maxlength="255" value="{{ old('note') }}"
                               placeholder="{{ $goal->account ? 'Ej. transferí a ' . $goal->account->name : 'Opcional' }}" class="{{ $input }}">
                    </div>

                    <div class="flex items-center gap-3">
                        <x-btn type="submit" class="flex-1 sm:flex-none">
                            <span x-text="direction === 'add' ? 'Apartar' : 'Retirar'">Apartar</span>
                        </x-btn>
                        <p class="text-xs text-[#878787]">No crea movimientos ni cambia saldos: solo marca el dinero como comprometido.</p>
                    </div>
                </form>
            </x-card>
        </section>
        @endunless

        {{-- ── Historial ─────────────────────────────────────────── --}}
        <section>
            <h2 class="text-xs font-bold text-[#878787] uppercase tracking-wider mb-2">Historial</h2>
            @if($goal->contributions->isEmpty())
            <x-card class="text-center py-10 px-6">
                <p class="text-sm text-[#878787]">Todavía no apartas dinero para esta meta.</p>
                @unless($goal->isCompleted())
                <x-btn href="#apartar" variant="secondary" class="mt-3 text-sm">Apartar el primero</x-btn>
                @endunless
            </x-card>
            @else
            <x-card class="divide-y divide-[#ababab]/10 overflow-hidden">
                @foreach($goal->contributions as $c)
                @php $out = bccomp((string) $c->amount, '0', 2) < 0; @endphp
                <div class="group flex items-center gap-3 px-4 py-3 hover:bg-[#f9f9f9] dark:hover:bg-white/5 transition-colors">
                    <div class="w-9 h-9 rounded-xl flex items-center justify-center flex-shrink-0 {{ $out ? 'bg-red-50 dark:bg-red-500/10 text-negative' : 'bg-[#76a72b]/10 text-positive' }}">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="{{ $out ? 'M20 12H4' : 'M12 4v16m8-8H4' }}"/></svg>
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-semibold text-[#373737] dark:text-white truncate">{{ $c->note ?: ($out ? 'Retiro' : 'Apartado') }}</p>
                        <p class="text-xs text-[#878787]">{{ $c->date->translatedFormat('j M Y') }}</p>
                    </div>
                    <span class="text-sm font-bold tabular-nums {{ $out ? 'text-negative' : 'text-positive' }}">
                        {{ $out ? '−' : '+' }}{{ format_currency(ltrim((string) $c->amount, '-')) }}
                    </span>
                    <form method="POST" action="{{ route('goals.contributions.destroy', [$goal, $c]) }}" class="row-actions"
                          data-confirm="¿Eliminar este registro de {{ format_currency(ltrim((string) $c->amount, '-')) }}?" data-confirm-label="Eliminar">
                        @csrf @method('DELETE')
                        <button type="submit" title="Eliminar" aria-label="Eliminar registro"
                                class="w-8 h-8 flex items-center justify-center text-[#878787] hover:text-negative hover:bg-red-50 dark:hover:bg-red-500/10 rounded-lg transition-colors">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                        </button>
                    </form>
                </div>
                @endforeach
            </x-card>
            @endif
        </section>
    </div>

    <aside class="space-y-6 lg:sticky lg:top-8">
        {{-- ── Desglose ──────────────────────────────────────────── --}}
        <section>
            <h2 class="text-xs font-bold text-[#878787] uppercase tracking-wider mb-2">Costo</h2>
            <x-card class="overflow-hidden">
                @if($goal->items->isNotEmpty())
                <ul class="divide-y divide-[#ababab]/10">
                    @foreach($goal->items as $item)
                    <li class="flex items-center justify-between gap-3 px-4 py-2.5 text-sm">
                        <span class="text-[#373737] dark:text-white truncate">{{ $item->description }}</span>
                        <span class="tabular-nums text-[#878787] flex-shrink-0">{{ format_currency($item->amount) }}</span>
                    </li>
                    @endforeach
                </ul>
                @endif
                <div class="flex items-center justify-between gap-3 px-4 py-3 {{ $goal->items->isNotEmpty() ? 'border-t border-[#ababab]/15 bg-[#f9f9f9] dark:bg-white/5' : '' }}">
                    <span class="text-sm font-semibold text-[#373737] dark:text-white">Total</span>
                    <span class="text-base font-bold tabular-nums text-[#373737] dark:text-white">{{ format_currency($goal->target_amount) }}</span>
                </div>
            </x-card>
            @if($goal->account)
            <p class="text-xs text-[#878787] mt-2 px-1">Se guarda en <a href="{{ route('accounts.show', $goal->account) }}" class="font-semibold text-positive hover:underline">{{ $goal->account->displayLabel() }}</a></p>
            @endif
            @if($goal->notes)
            <p class="text-sm text-[#878787] mt-3 px-1 whitespace-pre-line">{{ $goal->notes }}</p>
            @endif
        </section>

        {{-- ── Plan de esta meta ─────────────────────────────────── --}}
        @if(! empty($plan))
        <section>
            <h2 class="text-xs font-bold text-[#878787] uppercase tracking-wider mb-2">Plan</h2>
            <x-card class="overflow-hidden">
                <ol class="divide-y divide-[#ababab]/10">
                    @foreach($plan as $i => $row)
                    @if(bccomp($row['contribute'], '0', 2) > 0 || ! empty($row['due']))
                    <li class="flex items-center justify-between gap-3 px-4 py-2.5 text-sm {{ $i === 0 ? 'bg-[#76a72b]/5' : '' }}">
                        <span class="text-[#373737] dark:text-white">{{ $i === 0 ? 'Este mes' : ucfirst($row['month']->translatedFormat('F Y')) }}</span>
                        @if(! empty($row['due']))
                        <span class="font-semibold text-positive">Se paga</span>
                        @else
                        <span class="font-semibold tabular-nums text-[#373737] dark:text-white">+{{ format_currency($row['contribute']) }}</span>
                        @endif
                    </li>
                    @endif
                    @endforeach
                </ol>
            </x-card>
        </section>
        @endif
    </aside>
</div>

</x-app-layout>
