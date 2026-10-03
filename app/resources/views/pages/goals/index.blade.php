<x-app-layout title="Metas">

@php
    // «faltan 3 meses» / «faltan 12 días» / «vencida»
    $countdown = function ($goal) {
        $days = (int) now()->startOfDay()->diffInDays($goal->target_date, false);
        if ($days < 0)  return 'venció hace ' . abs($days) . ' ' . (abs($days) === 1 ? 'día' : 'días');
        if ($days === 0) return 'es hoy';
        if ($days < 45) return 'faltan ' . $days . ' ' . ($days === 1 ? 'día' : 'días');
        $m = (int) round($days / 30.4);
        return 'faltan ' . $m . ' meses';
    };
@endphp

<x-page-header title="Metas" action-route="{{ route('goals.create') }}" action-label="Nueva meta" />

@if($active->isEmpty() && $completed->isEmpty())
{{-- ── Estado vacío ─────────────────────────────────────────────── --}}
<x-card class="text-center py-14 px-6 max-w-2xl mx-auto">
    <div class="mx-auto w-14 h-14 rounded-2xl bg-[#76a72b]/10 text-positive flex items-center justify-center mb-4">
        <x-goal-kind-icon kind="other" class="w-7 h-7" />
    </div>
    <h2 class="text-lg font-bold text-[#373737] dark:text-white">Planea tus próximos gastos grandes</h2>
    <p class="text-sm text-[#878787] mt-1.5 max-w-md mx-auto">
        Registra un viaje, un proyecto o una compra con su fecha y su costo. FP te dice cuánto apartar cada mes
        para tener el dinero listo a tiempo.
    </p>
    <div class="flex flex-wrap justify-center gap-2 mt-6">
        @foreach(['trip' => 'Un viaje', 'project' => 'Un proyecto', 'purchase' => 'Una compra'] as $kind => $label)
        <a href="{{ route('goals.create', ['kind' => $kind]) }}"
           class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl border border-[#ababab]/40 bg-white dark:bg-[#2a2a2a] text-sm font-semibold text-[#373737] dark:text-white hover:border-[#76a72b] hover:text-positive transition-colors">
            <x-goal-kind-icon :kind="$kind" class="w-4 h-4" />
            {{ $label }}
        </a>
        @endforeach
    </div>
</x-card>
@else

{{-- ── Resumen ──────────────────────────────────────────────────── --}}
@if($active->isNotEmpty())
<div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-6">
    <div class="col-span-2 lg:col-span-1 rounded-2xl p-4 bg-[#373737] text-white">
        <p class="text-[11px] font-bold uppercase tracking-widest text-white/60">Aparta este mes</p>
        <p class="text-3xl font-bold tabular-nums mt-1">{{ format_currency($totals['month']) }}</p>
        <p class="text-xs text-white/60 mt-1">para {{ $active->count() }} {{ $active->count() === 1 ? 'meta' : 'metas' }} activas</p>
    </div>
    <x-card class="p-4">
        <p class="text-[11px] font-bold uppercase tracking-wider text-[#878787]">Apartado</p>
        <p class="text-xl font-bold tabular-nums text-positive mt-1">{{ format_currency($totals['saved']) }}</p>
        <p class="text-xs text-[#878787] mt-0.5 tabular-nums">de {{ format_currency($totals['target']) }}</p>
    </x-card>
    <x-card class="p-4">
        <p class="text-[11px] font-bold uppercase tracking-wider text-[#878787]">Te falta</p>
        <p class="text-xl font-bold tabular-nums text-[#373737] dark:text-white mt-1">{{ format_currency($totals['remaining']) }}</p>
        <p class="text-xs text-[#878787] mt-0.5">entre todas tus metas</p>
    </x-card>
    <x-card class="p-4 col-span-2 lg:col-span-1">
        <p class="text-[11px] font-bold uppercase tracking-wider text-[#878787]">Estado</p>
        @if($totals['behind'] > 0)
        <p class="text-xl font-bold text-warn mt-1">{{ $totals['behind'] }} {{ $totals['behind'] === 1 ? 'atrasada' : 'atrasadas' }}</p>
        <p class="text-xs text-[#878787] mt-0.5">aparta un poco más este mes</p>
        @else
        <p class="text-xl font-bold text-positive mt-1">Al corriente</p>
        <p class="text-xs text-[#878787] mt-0.5">vas a tiempo con todas</p>
        @endif
    </x-card>
</div>
@endif

<div class="lg:grid lg:grid-cols-[minmax(0,1fr)_340px] lg:gap-6 lg:items-start">

    {{-- ── Metas activas ────────────────────────────────────────── --}}
    <section>
        @if($active->isNotEmpty())
        <h2 class="text-xs font-bold text-[#878787] uppercase tracking-wider mb-2">Activas</h2>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            @foreach($active as $goal)
            @php $s = $summaries[$goal->id]; @endphp
            <a href="{{ route('goals.show', $goal) }}"
               class="group block bg-white dark:bg-[#2a2a2a] rounded-2xl border border-[#ababab]/20 dark:border-white/10 shadow-sm p-4 hover:border-[#76a72b]/50 hover:shadow transition-[border-color,box-shadow,transform] duration-150 ease-snappy active:scale-[0.99]">
                <div class="flex items-start gap-3">
                    <div class="w-10 h-10 rounded-xl flex items-center justify-center flex-shrink-0"
                         style="background-color: {{ $goal->color }}1f; color: {{ $goal->color }}">
                        <x-goal-kind-icon :kind="$goal->kind" />
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-bold text-[#373737] dark:text-white truncate" title="{{ $goal->name }}">{{ $goal->name }}</p>
                        <p class="text-xs text-[#878787] mt-0.5">
                            {{ $goal->target_date->translatedFormat('j M Y') }} · {{ $countdown($goal) }}
                        </p>
                    </div>
                    <x-goal-status :status="$s['status']" class="flex-shrink-0" />
                </div>

                <div class="mt-4">
                    <div class="flex items-baseline justify-between gap-2 text-sm">
                        <span class="font-bold tabular-nums text-[#373737] dark:text-white">{{ format_currency($s['saved']) }}</span>
                        <span class="text-xs text-[#878787] tabular-nums">de {{ format_currency($goal->target_amount) }}</span>
                    </div>
                    <div class="mt-1.5 h-2 rounded-full bg-[#efeded] dark:bg-white/10 overflow-hidden"
                         role="progressbar" aria-valuenow="{{ $s['pct'] }}" aria-valuemin="0" aria-valuemax="100" aria-label="Avance de {{ $goal->name }}">
                        <div class="h-full rounded-full" style="width: {{ $s['pct'] }}%; background-color: {{ $goal->color }}"></div>
                    </div>
                </div>

                <div class="mt-3 pt-3 border-t border-[#ababab]/10 flex items-center justify-between text-xs">
                    @if(bccomp($s['remaining'], '0', 2) > 0)
                    <span class="text-[#878787]">Aparta <strong class="text-[#373737] dark:text-white tabular-nums">{{ format_currency($s['quota']) }}</strong> al mes</span>
                    <span class="text-[#878787] tabular-nums">{{ $s['pct'] }}%</span>
                    @else
                    <span class="text-positive font-semibold">Ya tienes todo el dinero</span>
                    <span class="text-[#878787]">100%</span>
                    @endif
                </div>
            </a>
            @endforeach

            {{-- Agregar otra: misma forma que una tarjeta --}}
            <a href="{{ route('goals.create') }}"
               class="flex flex-col items-center justify-center gap-2 min-h-[88px] sm:min-h-[176px] rounded-2xl border-2 border-dashed border-[#ababab]/40 text-sm font-semibold text-[#878787] hover:border-[#76a72b] hover:text-positive transition-colors">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                Nueva meta
            </a>
        </div>
        @endif

        {{-- ── Dónde está lo apartado ─────────────────────────── --}}
        @if($byAccount->isNotEmpty())
        <h2 class="text-xs font-bold text-[#878787] uppercase tracking-wider mt-6 mb-2">Dónde está lo apartado</h2>
        <x-card class="divide-y divide-[#ababab]/10 overflow-hidden">
            @foreach($byAccount as $row)
            <div class="flex items-center gap-3 px-4 py-3">
                <span class="w-2.5 h-2.5 rounded-full flex-shrink-0" style="background-color: {{ $row['account']->color ?? '#878787' }}"></span>
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-semibold text-[#373737] dark:text-white truncate">{{ $row['account']->displayLabel() }}</p>
                    <p class="text-xs text-[#878787]">{{ $row['goals'] }} {{ $row['goals'] === 1 ? 'meta' : 'metas' }} · saldo {{ format_currency($row['balance']) }}</p>
                </div>
                <div class="text-right">
                    <p class="text-sm font-bold tabular-nums {{ $row['short'] ? 'text-negative' : 'text-[#373737] dark:text-white' }}">{{ format_currency($row['committed']) }}</p>
                    @if($row['short'])
                    <p class="text-[11px] font-semibold text-negative">más de lo que hay en la cuenta</p>
                    @else
                    <p class="text-[11px] text-[#878787] tabular-nums">libre {{ format_currency(bcsub($row['balance'], $row['committed'], 2)) }}</p>
                    @endif
                </div>
            </div>
            @endforeach
        </x-card>
        @endif

        {{-- ── Realizadas ─────────────────────────────────────── --}}
        @if($completed->isNotEmpty())
        <details class="group mt-6">
            <summary class="text-xs font-bold text-[#878787] uppercase tracking-wider cursor-pointer hover:text-[#373737] dark:hover:text-white transition-colors list-none flex items-center gap-2 mb-2">
                <svg class="w-3.5 h-3.5 group-open:rotate-90 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                Realizadas ({{ $completed->count() }})
            </summary>
            <x-card class="divide-y divide-[#ababab]/10 overflow-hidden">
                @foreach($completed as $goal)
                <a href="{{ route('goals.show', $goal) }}" class="flex items-center gap-3 px-4 py-3 hover:bg-[#f9f9f9] dark:hover:bg-white/5 transition-colors">
                    <x-goal-kind-icon :kind="$goal->kind" class="w-4 h-4 text-[#878787]" />
                    <span class="flex-1 min-w-0 text-sm text-[#373737] dark:text-white truncate">{{ $goal->name }}</span>
                    <span class="text-xs text-[#878787]">{{ $goal->completed_at?->translatedFormat('j M Y') }}</span>
                    <span class="text-sm font-semibold tabular-nums text-[#878787]">{{ format_currency($goal->target_amount) }}</span>
                </a>
                @endforeach
            </x-card>
        </details>
        @endif
    </section>

    {{-- ── Plan mes a mes ───────────────────────────────────────── --}}
    @if(! empty($schedule))
    <aside class="mt-6 lg:mt-0 lg:sticky lg:top-8">
        <h2 class="text-xs font-bold text-[#878787] uppercase tracking-wider mb-2">Plan mes a mes</h2>
        <x-card class="overflow-hidden">
            <p class="px-4 pt-4 pb-2 text-xs text-[#878787]">
                Cuánto apartar cada mes y cuánto deberías llevar guardado al cierre para tener cada meta lista a tiempo.
            </p>
            <ol class="divide-y divide-[#ababab]/10">
                @foreach($schedule as $i => $row)
                <li class="px-4 py-3 {{ $i === 0 ? 'bg-[#76a72b]/5' : '' }}">
                    <div class="flex items-center justify-between gap-3">
                        <span class="text-sm font-semibold text-[#373737] dark:text-white">
                            {{ $i === 0 ? 'Este mes' : ucfirst($row['month']->translatedFormat('F Y')) }}
                        </span>
                        <span class="text-sm font-bold tabular-nums {{ bccomp($row['contribute'], '0', 2) > 0 ? 'text-[#373737] dark:text-white' : 'text-[#878787]' }}">
                            {{ bccomp($row['contribute'], '0', 2) > 0 ? '+' . format_currency($row['contribute']) : '—' }}
                        </span>
                    </div>
                    @if(bccomp($row['reserved'], '0', 2) > 0)
                    <p class="text-xs text-[#878787] mt-0.5">
                        Deberías tener <span class="font-semibold tabular-nums text-[#373737] dark:text-white">{{ format_currency($row['reserved']) }}</span> apartado
                    </p>
                    @endif
                    @foreach($row['due'] as $dueGoal)
                    <p class="mt-1.5 flex items-center gap-1.5 text-xs font-semibold" style="color: {{ $dueGoal->color }}">
                        <x-goal-kind-icon :kind="$dueGoal->kind" class="w-3.5 h-3.5" />
                        <span class="truncate">{{ $dueGoal->name }}</span>
                        <span class="ml-auto tabular-nums">{{ format_currency($dueGoal->target_amount) }}</span>
                    </p>
                    @endforeach
                </li>
                @endforeach
            </ol>
        </x-card>
    </aside>
    @endif
</div>
@endif

</x-app-layout>
