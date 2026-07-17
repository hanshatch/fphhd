<x-app-layout title="Cargos recurrentes">
    <x-page-header title="Cargos recurrentes" action-route="{{ route('recurring.create') }}" action-label="Nuevo cargo" />

    {{-- Pendientes del mes vigente --}}
    @if($upcoming->isNotEmpty())
    <div class="mb-5">
        <h2 class="text-xs font-bold text-[#878787] uppercase tracking-wider mb-2">Este mes · {{ now()->translatedFormat('F') }}</h2>
        <div class="flex gap-2 overflow-x-auto pb-1">
            @foreach($upcoming->take(6) as $item)
            @php $days = (int) now()->startOfDay()->diffInDays($item->next_application_date->startOfDay(), false); @endphp
            <div class="flex-shrink-0 bg-white dark:bg-[#2a2a2a] border border-[#ababab]/20 rounded-xl p-3 min-w-[140px]">
                <p class="text-[10px] text-[#ababab] uppercase tracking-wider mb-1">
                    {{ $days === 0 ? 'Hoy' : ($days === 1 ? 'Mañana' : "En {$days} días") }}
                </p>
                <p class="text-sm font-semibold text-[#373737] dark:text-white truncate">{{ $item->name }}</p>
                <p class="text-sm font-bold {{ $item->type === 'expense' ? 'text-red-500' : 'text-[#76a72b]' }} mt-1">
                    {{ $item->type === 'expense' ? '−' : '+' }}${{ number_format((float)$item->amount, 2) }}
                </p>
            </div>
            @endforeach
        </div>
    </div>
    @endif

    {{-- Activos --}}
    <h2 class="text-xs font-bold text-[#878787] uppercase tracking-wider mb-2">Activos</h2>

    @if($active->isEmpty())
    <x-card class="text-center py-12 mb-5">
        <svg class="mx-auto w-10 h-10 text-[#ababab] mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
        <p class="text-[#878787] font-medium text-sm">No hay cargos recurrentes activos</p>
        <x-btn href="{{ route('recurring.create') }}" class="mt-4 text-sm">Crear primero</x-btn>
    </x-card>
    @else
    <div class="space-y-3 mb-6">
        @foreach($active as $charge)
        @php
            $daysUntil = (int) now()->startOfDay()->diffInDays($charge->next_application_date->startOfDay(), false);
            $urgency   = $daysUntil <= 3 ? 'red' : ($daysUntil <= 7 ? 'amber' : 'green');
        @endphp
        <x-card class="p-4">
            <div class="flex items-start gap-3">

                {{-- Indicador urgencia --}}
                <div class="flex-shrink-0 mt-0.5">
                    <div class="w-2.5 h-2.5 rounded-full mt-1
                        {{ $urgency === 'red' ? 'bg-red-500' : ($urgency === 'amber' ? 'bg-amber-400' : 'bg-[#76a72b]') }}">
                    </div>
                </div>

                {{-- Info --}}
                <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-2 flex-wrap">
                        <span class="font-semibold text-[#373737] dark:text-white text-sm">{{ $charge->name }}</span>
                        @if($charge->is_msi)
                            <span class="text-[10px] bg-blue-50 dark:bg-blue-500/10 text-blue-600 dark:text-blue-400 border border-blue-200 dark:border-blue-500/30 px-2 py-0.5 rounded-full font-bold">MSI</span>
                        @endif
                    </div>

                    <div class="flex items-center gap-2 mt-0.5 text-xs text-[#ababab] flex-wrap">
                        <span>{{ $charge->account->name }}</span>
                        @if($charge->category) <span>· {{ $charge->category->name }}</span> @endif
                        <span>· Día {{ $charge->day_of_month }} de cada mes</span>
                    </div>

                    {{-- Barra de progreso MSI --}}
                    @if($charge->is_msi && $charge->total_installments)
                    <div class="mt-2">
                        <div class="flex items-center justify-between text-[10px] text-[#ababab] mb-1">
                            <span>{{ $charge->applied_installments }} / {{ $charge->total_installments }} cuotas pagadas</span>
                            <span>${{ number_format((float)$charge->pendingAmount(), 2) }} pendiente</span>
                        </div>
                        <div class="h-1.5 bg-[#efeded] dark:bg-white/10 rounded-full overflow-hidden">
                            <div class="h-full bg-[#76a72b] rounded-full transition-all"
                                 style="width: {{ $charge->progressPercent() }}%"></div>
                        </div>
                    </div>
                    @endif

                    <div class="mt-2 text-xs">
                        <span class="{{ $urgency === 'red' ? 'text-red-500 font-semibold' : 'text-[#878787]' }}">
                            Próximo:
                            {{ $daysUntil === 0 ? 'Hoy' : ($daysUntil === 1 ? 'Mañana' : $charge->next_application_date->translatedFormat('d M Y')) }}
                        </span>
                    </div>
                </div>

                {{-- Monto --}}
                <div class="text-right flex-shrink-0">
                    <div class="font-bold text-sm {{ $charge->type === 'expense' ? 'text-red-500' : 'text-[#76a72b]' }}">
                        {{ $charge->type === 'expense' ? '−' : '+' }}${{ number_format((float)$charge->amount, 2) }}
                    </div>
                    <div class="text-[10px] text-[#ababab]">/ mes</div>
                </div>

            </div>

            {{-- Acciones --}}
            <div class="flex items-center justify-end gap-1 mt-2 pt-2 border-t border-[#efeded] dark:border-white/10" x-data>
                    <a href="{{ route('recurring.edit', $charge) }}"
                       class="w-8 h-8 flex items-center justify-center text-[#ababab] hover:text-[#76a72b] hover:bg-[#76a72b]/10 rounded-lg transition-colors">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                    </a>
                    <a href="{{ route('recurring.apply.show', $charge) }}" title="Aplicar (confirmar monto)"
                       class="w-8 h-8 flex items-center justify-center text-[#ababab] hover:text-blue-500 hover:bg-blue-50 dark:hover:bg-blue-500/10 rounded-lg transition-colors">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </a>
                    <form method="POST" action="{{ route('recurring.toggle', $charge) }}">
                        @csrf
                        <button type="submit" title="Pausar"
                            class="w-8 h-8 flex items-center justify-center text-[#ababab] hover:text-amber-500 hover:bg-amber-50 dark:hover:bg-amber-500/10 rounded-lg transition-colors">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 9v6m4-6v6m7-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        </button>
                    </form>
                    <form method="POST" action="{{ route('recurring.destroy', $charge) }}"
                          onsubmit="return confirm('¿Eliminar «{{ addslashes($charge->name) }}»? Los movimientos ya aplicados no se borran.')">
                        @csrf @method('DELETE')
                        <button type="submit" title="Eliminar"
                            class="w-8 h-8 flex items-center justify-center text-[#ababab] hover:text-red-500 hover:bg-red-50 dark:hover:bg-red-500/10 rounded-lg transition-colors">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                        </button>
                    </form>
            </div>
        </x-card>
        @endforeach
    </div>
    @endif

    {{-- Completados / Pausados --}}
    @if($completed->isNotEmpty())
    <details class="group">
        <summary class="text-xs font-bold text-[#ababab] uppercase tracking-wider cursor-pointer hover:text-[#878787] transition-colors list-none flex items-center gap-2 mb-2">
            <svg class="w-3.5 h-3.5 group-open:rotate-90 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            Completados / pausados ({{ $completed->count() }})
        </summary>
        <div class="space-y-2 mt-2">
            @foreach($completed as $charge)
            <x-card class="p-3 flex items-center gap-3 opacity-60">
                <div class="flex-1 min-w-0">
                    <span class="text-sm font-medium text-[#373737] dark:text-white">{{ $charge->name }}</span>
                    @if($charge->is_msi)
                        <span class="ml-2 text-[10px] bg-gray-100 dark:bg-gray-700 text-[#878787] px-1.5 py-0.5 rounded">{{ $charge->applied_installments }}/{{ $charge->total_installments }} cuotas</span>
                    @endif
                </div>
                <span class="text-xs text-[#ababab]">
                    {{ $charge->is_active ? 'Pausado' : 'Completado' }}
                </span>
                @if(! $charge->is_active && ! $charge->isCompleted())
                <form method="POST" action="{{ route('recurring.toggle', $charge) }}">
                    @csrf
                    <button type="submit" class="text-xs text-[#76a72b] hover:underline">Reactivar</button>
                </form>
                @endif
                <form method="POST" action="{{ route('recurring.destroy', $charge) }}"
                      onsubmit="return confirm('¿Eliminar?')">
                    @csrf @method('DELETE')
                    <button type="submit" class="w-7 h-7 flex items-center justify-center text-[#ababab] hover:text-red-500 rounded-lg hover:bg-red-50 transition-colors">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                    </button>
                </form>
            </x-card>
            @endforeach
        </div>
    </details>
    @endif
</x-app-layout>
