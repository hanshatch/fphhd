<x-app-layout title="Ingresos esperados">
    <x-page-header title="Ingresos esperados" action-route="{{ route('income-plans.create') }}" action-label="Nuevo ingreso" />

    {{-- Resumen del mes --}}
    <div class="grid grid-cols-3 gap-3 mb-5">
        <div class="bg-white dark:bg-[#2a2a2a] rounded-xl border border-[#ababab]/20 p-4 text-center">
            <p class="text-[10px] text-[#ababab] uppercase tracking-wider mb-1">Esperado</p>
            <p class="text-lg font-bold text-[#373737] dark:text-white">${{ number_format((float)$summary['expected'], 2) }}</p>
        </div>
        <div class="bg-[#76a72b]/10 border border-[#76a72b]/20 rounded-xl p-4 text-center">
            <p class="text-[10px] text-[#76a72b]/70 uppercase tracking-wider mb-1">Registrado</p>
            <p class="text-lg font-bold text-[#76a72b]">${{ number_format((float)$summary['registered'], 2) }}</p>
        </div>
        <div class="bg-white dark:bg-[#2a2a2a] rounded-xl border border-[#ababab]/20 p-4 text-center">
            <p class="text-[10px] text-[#ababab] uppercase tracking-wider mb-1">Pendiente</p>
            <p class="text-lg font-bold {{ (float)$summary['pending'] > 0 ? 'text-amber-500' : 'text-[#ababab]' }}">
                ${{ number_format((float)$summary['pending'], 2) }}
            </p>
        </div>
    </div>

    {{-- Próximos 30 días --}}
    @if($upcoming->isNotEmpty())
    <h2 class="text-xs font-bold text-[#878787] uppercase tracking-wider mb-2">Próximos 30 días</h2>
    <div class="flex gap-2 overflow-x-auto pb-1 mb-5">
        @foreach($upcoming as $plan)
        @php $days = $plan->daysUntilNext(); @endphp
        <div class="flex-shrink-0 bg-white dark:bg-[#2a2a2a] border border-[#ababab]/20 rounded-xl p-3 min-w-[160px]">
            <p class="text-[10px] text-[#76a72b] font-bold uppercase tracking-wider mb-1">
                {{ $days === 0 ? 'Hoy' : ($days === 1 ? 'Mañana' : "En {$days} días") }}
            </p>
            <p class="text-sm font-semibold text-[#373737] dark:text-white truncate">{{ $plan->name }}</p>
            <p class="text-sm font-bold text-[#76a72b] mt-1">~${{ number_format((float)$plan->expected_amount, 2) }}</p>
            <p class="text-[10px] text-[#ababab] mt-0.5">{{ $plan->next_expected_date->translatedFormat('d M') }}</p>
        </div>
        @endforeach
    </div>
    @endif

    {{-- Lista de planes --}}
    <h2 class="text-xs font-bold text-[#878787] uppercase tracking-wider mb-2">Todos los ingresos</h2>

    @if($plans->isEmpty())
    <x-card class="text-center py-12">
        <svg class="mx-auto w-10 h-10 text-[#ababab] mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
        </svg>
        <p class="text-[#878787] font-medium text-sm">No hay ingresos esperados</p>
        <x-btn href="{{ route('income-plans.create') }}" class="mt-4 text-sm">Agregar primero</x-btn>
    </x-card>
    @else
    <div class="space-y-3">
        @foreach($plans as $plan)
        @php $days = $plan->daysUntilNext(); $urgency = $days <= 2 ? 'green' : ($days <= 5 ? 'amber' : 'gray'); @endphp
        <x-card class="{{ ! $plan->is_active ? 'opacity-50' : '' }} p-4">
            <div class="flex items-center gap-3">

                {{-- Indicador --}}
                <div class="w-2.5 h-2.5 rounded-full flex-shrink-0 mt-0.5
                    {{ $urgency === 'green' ? 'bg-[#76a72b]' : ($urgency === 'amber' ? 'bg-amber-400' : 'bg-[#ababab]') }}">
                </div>

                {{-- Info --}}
                <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-2 flex-wrap">
                        <span class="font-semibold text-[#373737] dark:text-white text-sm">{{ $plan->name }}</span>
                        <span class="text-[10px] px-2 py-0.5 rounded-full font-semibold
                            {{ $plan->isOnce() ? 'bg-amber-50 text-amber-600 dark:bg-amber-500/10 dark:text-amber-400' : 'bg-[#76a72b]/10 text-[#4a7018] dark:text-[#76a72b]' }}">
                            {{ $plan->frequencyLabel() }}
                        </span>
                        @if(! $plan->is_active)
                            <span class="text-[10px] bg-[#efeded] text-[#878787] px-2 py-0.5 rounded-full">Pausado</span>
                        @endif
                    </div>
                    <div class="flex items-center gap-2 mt-0.5 text-xs text-[#ababab] flex-wrap">
                        <span>{{ $plan->account->name }}</span>
                        @if($plan->source) <span>· {{ $plan->source->name }}</span> @endif
                        <span class="{{ $urgency === 'green' ? 'text-[#76a72b] font-semibold' : '' }}">
                            · {{ $days === 0 ? 'Hoy' : ($days < 0 ? 'Vencido hace '.abs($days).'d' : "En {$days} días") }}
                            ({{ $plan->next_expected_date->translatedFormat('d M') }})
                        </span>
                    </div>
                </div>

                {{-- Monto estimado --}}
                <div class="text-right flex-shrink-0">
                    <div class="font-bold text-sm text-[#76a72b]">~${{ number_format((float)$plan->expected_amount, 2) }}</div>
                    <div class="text-[10px] text-[#ababab]">estimado</div>
                </div>
            </div>

            {{-- Acciones --}}
            <div class="flex items-center justify-end gap-1 mt-2 pt-2 border-t border-[#efeded] dark:border-white/10">
                    {{-- Registrar --}}
                    @if($plan->is_active)
                    <a href="{{ route('income-plans.register.show', $plan) }}"
                       class="w-8 h-8 flex items-center justify-center text-[#ababab] hover:text-[#76a72b] hover:bg-[#76a72b]/10 rounded-lg transition-colors"
                       title="Registrar ingreso real">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </a>
                    @endif
                    {{-- Editar --}}
                    <a href="{{ route('income-plans.edit', $plan) }}"
                       class="w-8 h-8 flex items-center justify-center text-[#ababab] hover:text-[#76a72b] hover:bg-[#76a72b]/10 rounded-lg transition-colors">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                    </a>
                    {{-- Clonar --}}
                    <form method="POST" action="{{ route('income-plans.duplicate', $plan) }}">
                        @csrf
                        <button type="submit" title="Clonar"
                            class="w-8 h-8 flex items-center justify-center text-[#ababab] hover:text-blue-500 hover:bg-blue-50 dark:hover:bg-blue-500/10 rounded-lg transition-colors">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                        </button>
                    </form>
                    {{-- Pausar/Reactivar --}}
                    <form method="POST" action="{{ route('income-plans.toggle', $plan) }}">
                        @csrf
                        <button type="submit" class="w-8 h-8 flex items-center justify-center text-[#ababab] hover:text-amber-500 hover:bg-amber-50 rounded-lg transition-colors"
                                title="{{ $plan->is_active ? 'Pausar' : 'Reactivar' }}">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                @if($plan->is_active)
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 9v6m4-6v6m7-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                @else
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                @endif
                            </svg>
                        </button>
                    </form>
            </div>
        </x-card>
        @endforeach
    </div>
    @endif
</x-app-layout>
