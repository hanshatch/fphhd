<x-app-layout title="Cargos recurrentes">
    <x-page-header title="Cargos recurrentes" action-route="{{ route('recurring.create') }}" action-label="Nuevo cargo" />

    @php
        $pending   = $board['overdue']->count() + $board['upcoming']->count();
        $doneCount = $board['done']->count();
        $month     = now()->translatedFormat('F');
        $monthly   = $active->where('type', 'expense')->reduce(fn ($c, $ch) => bcadd($c, (string) $ch->amount, 2), '0.00');
    @endphp

    @if($active->isEmpty() && $completed->isEmpty())
    <x-card class="text-center py-12">
        <svg class="mx-auto w-10 h-10 text-[#878787] mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
        <p class="text-[#878787] font-medium text-sm">Aún no tienes cargos recurrentes</p>
        <p class="text-xs text-[#878787] mt-1">Registra rentas, colegiaturas, suscripciones o compras a meses.</p>
        <x-btn href="{{ route('recurring.create') }}" class="mt-4 text-sm">Crear el primero</x-btn>
    </x-card>
    @else

    {{-- ── 1. Resumen del mes: cuánto falta y cuánto ya quedó ─────────── --}}
    <x-card class="p-4 mb-5">
        <p class="text-xs font-bold text-[#878787] uppercase tracking-wider">{{ ucfirst($month) }}</p>

        @if($pending > 0)
        <p class="mt-1 text-2xl font-bold tabular-nums text-[#373737] dark:text-white">{{ format_currency($board['pending_total']) }}</p>
        <p class="text-sm text-[#878787]">
            por aplicar en {{ $pending }} {{ $pending === 1 ? 'cargo' : 'cargos' }}@if($board['overdue']->isNotEmpty()),
            <span class="text-negative font-semibold">{{ $board['overdue']->count() }} {{ $board['overdue']->count() === 1 ? 'ya venció' : 'ya vencieron' }}</span>@endif
        </p>
        @else
        <p class="mt-1 text-lg font-bold text-positive">✓ Todo al día</p>
        <p class="text-sm text-[#878787]">No queda ningún cargo por aplicar este mes.</p>
        @endif

        <div class="mt-3 h-2 bg-[#efeded] dark:bg-white/10 rounded-full overflow-hidden" role="progressbar"
             aria-valuenow="{{ $board['progress'] }}" aria-valuemin="0" aria-valuemax="100" aria-label="Avance del mes">
            <div class="h-full bg-[#76a72b] rounded-full" style="width: {{ $board['progress'] }}%"></div>
        </div>
        <div class="mt-1.5 flex items-center justify-between text-xs text-[#878787] tabular-nums">
            <span>Aplicado {{ format_currency($board['done_total']) }} ({{ $doneCount }})</span>
            <span>Total del mes {{ format_currency($board['month_total']) }}</span>
        </div>
    </x-card>

    {{-- ── 2. Por aplicar: primero lo vencido, luego lo que viene ─────── --}}
    @if($pending > 0)
    <h2 class="text-xs font-bold text-[#878787] uppercase tracking-wider mb-2">Por aplicar</h2>
    <x-card class="mb-5 overflow-hidden">
        @if($board['overdue']->isNotEmpty())
        <p class="px-4 pt-3 text-[11px] font-bold uppercase tracking-wider text-negative">Vencidos · {{ $board['overdue']->count() }}</p>
        <ul class="divide-y divide-[#efeded] dark:divide-white/10">
            @foreach($board['overdue'] as $charge)
                @include('pages.recurring._due-row', ['charge' => $charge, 'state' => 'overdue'])
            @endforeach
        </ul>
        @endif

        @if($board['upcoming']->isNotEmpty())
        <p class="px-4 pt-3 text-[11px] font-bold uppercase tracking-wider text-[#878787] {{ $board['overdue']->isNotEmpty() ? 'border-t border-[#efeded] dark:border-white/10' : '' }}">
            Lo que falta de {{ $month }} · {{ $board['upcoming']->count() }}
        </p>
        <ul class="divide-y divide-[#efeded] dark:divide-white/10">
            @foreach($board['upcoming'] as $charge)
                @include('pages.recurring._due-row', ['charge' => $charge, 'state' => 'upcoming'])
            @endforeach
        </ul>
        @endif
    </x-card>
    @endif

    {{-- ── 3. Ya resueltos este mes ───────────────────────────────────── --}}
    @if($doneCount > 0 || $board['skipped']->isNotEmpty())
    <h2 class="text-xs font-bold text-[#878787] uppercase tracking-wider mb-2">Ya aplicados en {{ $month }}</h2>
    <x-card class="mb-5 overflow-hidden">
        <ul class="divide-y divide-[#efeded] dark:divide-white/10">
            @foreach($board['done'] as $item)
            <li>
                <a href="{{ route('transactions.edit', $item['transaction']) }}" class="flex items-center gap-3 px-4 py-3 min-h-[44px] hover:bg-[#efeded]/60 dark:hover:bg-white/5 transition-colors">
                    <svg class="flex-shrink-0 w-4 h-4 text-positive" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-medium text-[#373737] dark:text-white truncate">{{ $item['charge']->name }}</p>
                        <p class="text-xs text-[#878787] truncate">Aplicado el {{ $item['transaction']->date->translatedFormat('j M') }} · {{ $item['charge']->account->name }}</p>
                    </div>
                    <p class="flex-shrink-0 text-sm font-semibold tabular-nums text-[#878787]">{{ format_currency($item['transaction']->amount) }}</p>
                </a>
            </li>
            @endforeach
            @foreach($board['skipped'] as $charge)
            <li class="flex items-center gap-3 px-4 py-3 min-h-[44px]">
                <svg class="flex-shrink-0 w-4 h-4 text-[#ababab]" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 5l7 7-7 7M5 5l7 7-7 7"/></svg>
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-medium text-[#878787] truncate">{{ $charge->name }}</p>
                    <p class="text-xs text-[#878787] truncate">Sin movimiento este mes · siguiente {{ $charge->next_application_date->translatedFormat('j M') }}</p>
                </div>
                <p class="flex-shrink-0 text-sm tabular-nums text-[#ababab] line-through">{{ format_currency($charge->amount) }}</p>
            </li>
            @endforeach
        </ul>
    </x-card>
    @endif

    {{-- ── 4. Catálogo: todo lo programado, en el orden del mes ───────── --}}
    @if($active->isNotEmpty())
    <div class="flex items-baseline justify-between gap-3 mb-2">
        <h2 class="text-xs font-bold text-[#878787] uppercase tracking-wider">Programados · {{ $active->count() }}</h2>
        <p class="text-xs text-[#878787] tabular-nums">{{ format_currency($monthly) }} al mes</p>
    </div>
    <x-card class="mb-6 overflow-hidden">
        <ul class="divide-y divide-[#efeded] dark:divide-white/10">
            @foreach($active as $charge)
            <li class="px-4 py-3">
                <div class="flex items-start gap-3">
                    {{-- Día del mes en que cae --}}
                    <div class="flex-shrink-0 w-10 text-center rounded-lg bg-[#efeded] dark:bg-white/10 py-1">
                        <p class="text-[9px] font-bold uppercase text-[#878787] leading-none">Día</p>
                        <p class="text-base font-bold tabular-nums text-[#373737] dark:text-white leading-tight">{{ $charge->day_of_month }}</p>
                    </div>

                    <div class="flex-1 min-w-0">
                        <div class="flex items-baseline justify-between gap-3">
                            <p class="text-sm font-semibold text-[#373737] dark:text-white truncate">{{ $charge->name }}</p>
                            <p class="flex-shrink-0 text-sm font-bold tabular-nums {{ $charge->type === 'expense' ? 'text-negative' : 'text-positive' }}">
                                {{ $charge->type === 'expense' ? '−' : '+' }}{{ format_currency($charge->amount) }}
                            </p>
                        </div>
                        <p class="mt-0.5 text-xs text-[#878787] truncate">
                            {{ $charge->account->name }}@if($charge->category) · {{ $charge->category->name }}@endif
                            · próximo {{ $charge->next_application_date->translatedFormat('j M') }}
                        </p>

                        @if($charge->is_msi && $charge->total_installments)
                        <div class="mt-2">
                            <div class="h-1.5 bg-[#efeded] dark:bg-white/10 rounded-full overflow-hidden">
                                <div class="h-full bg-blue-500 rounded-full" style="width: {{ $charge->progressPercent() }}%"></div>
                            </div>
                            <p class="mt-1 text-[11px] text-[#878787] tabular-nums">
                                {{ $charge->applied_installments }} de {{ $charge->total_installments }} cuotas · faltan {{ format_currency($charge->pendingAmount()) }}
                            </p>
                        </div>
                        @endif
                    </div>
                </div>

                <div class="mt-1 -mb-1 flex items-center justify-end gap-1">
                    <a href="{{ route('recurring.apply.show', $charge) }}" class="inline-flex items-center min-h-[44px] px-3 rounded-lg text-xs font-semibold text-positive hover:bg-[#76a72b]/10 transition-colors">Aplicar</a>
                    <a href="{{ route('recurring.edit', $charge) }}" class="inline-flex items-center min-h-[44px] px-3 rounded-lg text-xs font-semibold text-[#878787] hover:text-[#373737] dark:hover:text-white hover:bg-[#efeded] dark:hover:bg-white/10 transition-colors">Editar</a>
                    <form method="POST" action="{{ route('recurring.toggle', $charge) }}">
                        @csrf
                        <button type="submit" class="inline-flex items-center min-h-[44px] px-3 rounded-lg text-xs font-semibold text-[#878787] hover:text-warn hover:bg-amber-50 dark:hover:bg-amber-500/10 transition-colors">Pausar</button>
                    </form>
                    <form method="POST" action="{{ route('recurring.destroy', $charge) }}"
                          data-confirm="¿Eliminar «{{ $charge->name }}»? Los movimientos ya aplicados no se borran." data-confirm-label="Eliminar">
                        @csrf @method('DELETE')
                        <button type="submit" title="Eliminar" aria-label="Eliminar {{ $charge->name }}"
                            class="w-11 h-11 flex items-center justify-center text-[#878787] hover:text-negative hover:bg-red-50 dark:hover:bg-red-500/10 rounded-lg transition-colors">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                        </button>
                    </form>
                </div>
            </li>
            @endforeach
        </ul>
    </x-card>
    @endif

    {{-- ── 5. Terminados o en pausa ───────────────────────────────────── --}}
    @if($completed->isNotEmpty())
    <details class="group">
        <summary class="text-xs font-bold text-[#878787] uppercase tracking-wider cursor-pointer list-none flex items-center gap-2 mb-2 min-h-[44px]">
            <svg class="w-3.5 h-3.5 group-open:rotate-90 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            Terminados o en pausa · {{ $completed->count() }}
        </summary>
        <x-card class="overflow-hidden">
            <ul class="divide-y divide-[#efeded] dark:divide-white/10">
                @foreach($completed as $charge)
                @php $finished = $charge->total_installments && $charge->applied_installments >= $charge->total_installments; @endphp
                <li class="flex items-center gap-2 px-4 py-2">
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-medium text-[#878787] truncate">{{ $charge->name }}</p>
                        <p class="text-xs text-[#878787]">
                            {{ $finished ? 'Terminado' : 'En pausa' }}@if($charge->is_msi) · <span class="tabular-nums">{{ $charge->applied_installments }}/{{ $charge->total_installments }} cuotas</span>@endif
                        </p>
                    </div>
                    @unless($finished)
                    <form method="POST" action="{{ route('recurring.toggle', $charge) }}">
                        @csrf
                        <button type="submit" class="inline-flex items-center min-h-[44px] px-3 rounded-lg text-xs font-semibold text-positive hover:bg-[#76a72b]/10 transition-colors">Reactivar</button>
                    </form>
                    @endunless
                    <form method="POST" action="{{ route('recurring.destroy', $charge) }}"
                          data-confirm="¿Eliminar «{{ $charge->name }}»?" data-confirm-label="Eliminar">
                        @csrf @method('DELETE')
                        <button type="submit" title="Eliminar" aria-label="Eliminar {{ $charge->name }}" class="w-11 h-11 flex items-center justify-center text-[#878787] hover:text-negative rounded-lg hover:bg-red-50 dark:hover:bg-red-500/10 transition-colors">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                        </button>
                    </form>
                </li>
                @endforeach
            </ul>
        </x-card>
    </details>
    @endif

    @endif
</x-app-layout>
