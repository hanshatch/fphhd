<x-app-layout title="Presupuestos">

@php
$now       = now();
$prev      = $month->copy()->subMonth()->format('Y-m');
$next      = $month->copy()->addMonth()->format('Y-m');
$remaining = $totalLimit - $totalSpent;
$isCurrentMonth = $month->isSameMonth($now);
@endphp

<x-page-header title="Presupuestos" />

{{-- ── Navegación de mes ─────────────────────────────────────────── --}}
<div class="flex items-center justify-between mb-5">
    <a href="?month={{ $prev }}"
       class="w-9 h-9 flex items-center justify-center rounded-full bg-white dark:bg-[#2a2a2a] border border-[#ababab]/20 text-[#878787] hover:text-[#76a72b] hover:border-[#76a72b] transition-colors">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
    </a>
    <h2 class="font-bold text-[#373737] dark:text-white capitalize">
        {{ $month->translatedFormat('F Y') }}
    </h2>
    <a href="?month={{ $next }}"
       class="w-9 h-9 flex items-center justify-center rounded-full bg-white dark:bg-[#2a2a2a] border border-[#ababab]/20 text-[#878787] hover:text-[#76a72b] hover:border-[#76a72b] transition-colors">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
    </a>
</div>

{{-- ── Resumen global ────────────────────────────────────────────── --}}
@if($budgets->isNotEmpty())
<div class="bg-white dark:bg-[#2a2a2a] rounded-2xl border border-[#ababab]/15 shadow-sm p-5 mb-5">
    {{-- Barra global --}}
    @php
        $globalPct = $totalLimit > 0 ? min(($totalSpent / $totalLimit) * 100, 100) : 0;
        $globalOver = $totalSpent > $totalLimit;
        $barColor = $globalOver ? '#ef4444' : ($globalPct >= 80 ? '#f97316' : '#76a72b');
    @endphp
    <div class="flex items-end justify-between mb-3">
        <div>
            <p class="text-xs text-[#ababab] uppercase tracking-wider font-semibold mb-0.5">Gastado este mes</p>
            <p class="text-2xl font-bold tabular-nums" style="color: {{ $barColor }}">
                ${{ number_format($totalSpent, 2) }}
                <span class="text-sm font-normal text-[#ababab]">/ ${{ number_format($totalLimit, 2) }}</span>
            </p>
        </div>
        <div class="text-right">
            <p class="text-xs text-[#ababab] uppercase tracking-wider font-semibold mb-0.5">
                {{ $globalOver ? 'Excedido' : 'Disponible' }}
            </p>
            <p class="text-lg font-bold tabular-nums {{ $globalOver ? 'text-red-500' : 'text-[#76a72b]' }}">
                {{ $globalOver ? '-' : '' }}${{ number_format(abs($remaining), 2) }}
            </p>
        </div>
    </div>
    <div class="h-2.5 bg-[#efeded] dark:bg-white/10 rounded-full overflow-hidden">
        <div class="h-full rounded-full transition-all duration-500"
             style="width: {{ min($globalPct, 100) }}%; background-color: {{ $barColor }}"></div>
    </div>
    <p class="text-xs text-[#ababab] mt-1.5 text-right">{{ number_format($globalPct, 0) }}% del presupuesto total</p>
</div>
@endif

{{-- ── Lista de presupuestos ────────────────────────────────────── --}}
@if($budgets->isEmpty())
<x-card class="text-center py-14 mb-5">
    <svg class="mx-auto w-10 h-10 text-[#ababab] mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
    </svg>
    <p class="text-[#878787] font-medium text-sm">Sin presupuestos definidos</p>
    <p class="text-[#ababab] text-xs mt-1">Agrega un límite mensual por categoría</p>
</x-card>
@else
<div class="space-y-3 mb-5">
    @foreach($budgets as $item)
    @php
        $budget  = $item['budget'];
        $spent   = (float) $item['spent'];
        $limit   = (float) $budget->amount;
        $pct     = $item['percent'];
        $over    = $spent > $limit;
        $warn    = !$over && $pct >= 80;
        $barColor = $over ? '#ef4444' : ($warn ? '#f97316' : '#76a72b');
        $catColor = $budget->category?->color ?? '#878787';
    @endphp

    <div x-data="{ editing: false }"
         class="bg-white dark:bg-[#2a2a2a] rounded-2xl border border-[#ababab]/15 shadow-sm overflow-hidden">

        {{-- Fila principal --}}
        <div class="flex items-center gap-3 px-4 pt-4 pb-2">
            {{-- Ícono categoría --}}
            <div class="w-10 h-10 rounded-[10px] flex items-center justify-center flex-shrink-0 text-white font-bold text-sm"
                 style="background-color: {{ $catColor }}">
                {{ mb_strtoupper(mb_substr($budget->category?->name ?? '?', 0, 1)) }}
            </div>

            {{-- Nombre + monto --}}
            <div class="flex-1 min-w-0">
                <p class="font-semibold text-[#373737] dark:text-white text-sm truncate">
                    {{ $budget->category?->name ?? 'Sin categoría' }}
                </p>
                <p class="text-xs mt-0.5">
                    <span class="font-bold tabular-nums" style="color: {{ $barColor }}">
                        ${{ number_format($spent, 2) }}
                    </span>
                    <span class="text-[#ababab]"> / ${{ number_format($limit, 2) }}</span>
                    @if($over)
                    <span class="ml-1 text-[10px] bg-red-50 text-red-500 px-1.5 py-0.5 rounded-full font-semibold">
                        +${{ number_format($spent - $limit, 2) }} excedido
                    </span>
                    @elseif($warn)
                    <span class="ml-1 text-[10px] bg-orange-50 text-orange-500 px-1.5 py-0.5 rounded-full font-semibold">
                        {{ number_format(100 - $pct, 0) }}% restante
                    </span>
                    @endif
                </p>
            </div>

            {{-- Porcentaje + acciones --}}
            <div class="flex items-center gap-2 flex-shrink-0">
                <span class="text-xs font-bold tabular-nums" style="color: {{ $barColor }}">
                    {{ number_format($pct, 0) }}%
                </span>
                <button x-on:click="editing = !editing"
                    class="w-7 h-7 flex items-center justify-center text-[#ababab] hover:text-[#76a72b] hover:bg-[#76a72b]/10 rounded-lg transition-colors">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                    </svg>
                </button>
                <form method="POST" action="{{ route('budgets.destroy', $budget) }}"
                      onsubmit="return confirm('¿Eliminar presupuesto de {{ addslashes($budget->category?->name) }}?')">
                    @csrf @method('DELETE')
                    <button type="submit"
                        class="w-7 h-7 flex items-center justify-center text-[#ababab] hover:text-red-500 hover:bg-red-50 rounded-lg transition-colors">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                        </svg>
                    </button>
                </form>
            </div>
        </div>

        {{-- Barra de progreso --}}
        <div class="px-4 pb-3">
            <div class="h-2 bg-[#efeded] dark:bg-white/10 rounded-full overflow-hidden">
                <div class="h-full rounded-full transition-all duration-500"
                     style="width: {{ min($pct, 100) }}%; background-color: {{ $barColor }}"></div>
            </div>
        </div>

        {{-- Form de edición inline --}}
        <div x-show="editing" x-cloak class="border-t border-[#ababab]/10 px-4 py-3 bg-[#fafafa] dark:bg-white/[0.02]">
            <form method="POST" action="{{ route('budgets.update', $budget) }}" class="flex items-center gap-3">
                @csrf @method('PATCH')
                <label class="text-xs font-semibold text-[#878787] flex-shrink-0">Límite mensual</label>
                <div class="relative flex-1">
                    <span class="absolute left-3 top-1/2 -translate-y-1/2 text-[#878787] font-semibold text-sm">$</span>
                    <input type="number" name="amount" step="0.01" min="1" required
                        value="{{ number_format((float)$budget->amount, 2, '.', '') }}"
                        class="w-full rounded-xl border border-[#ababab]/40 bg-white dark:bg-white/5 pl-7 pr-3 py-2 text-sm font-bold text-[#373737] dark:text-white tabular-nums focus:outline-none focus:ring-2 focus:ring-[#76a72b] transition">
                </div>
                <x-btn type="submit" class="text-xs py-2 px-4 flex-shrink-0">Guardar</x-btn>
                <button type="button" x-on:click="editing = false"
                    class="text-xs text-[#ababab] hover:text-[#878787] flex-shrink-0">Cancelar</button>
            </form>
        </div>

    </div>
    @endforeach
</div>
@endif

{{-- ── Agregar presupuesto ──────────────────────────────────────── --}}
@if($available->isNotEmpty())
<x-card class="p-5">
    <h3 class="text-sm font-bold text-[#373737] dark:text-white mb-4">
        Agregar presupuesto
    </h3>
    <form method="POST" action="{{ route('budgets.store') }}" class="space-y-4">
        @csrf

        <div class="grid grid-cols-2 gap-3">
            <div>
                <label class="block text-sm font-semibold text-[#373737] dark:text-white mb-1.5">
                    Categoría <span class="text-[#76a72b]">*</span>
                </label>
                <select name="category_id" required
                    class="w-full rounded-xl border border-[#ababab]/40 bg-[#efeded]/50 dark:bg-white/5 px-4 py-3 text-[#373737] dark:text-white focus:outline-none focus:ring-2 focus:ring-[#76a72b] transition text-sm">
                    <option value="">Selecciona</option>
                    @foreach($available as $cat)
                    <option value="{{ $cat->id }}"
                        {{ old('category_id') == $cat->id ? 'selected' : '' }}>
                        {{ $cat->name }}
                    </option>
                    @endforeach
                </select>
                @error('category_id')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
            </div>

            <div>
                <label class="block text-sm font-semibold text-[#373737] dark:text-white mb-1.5">
                    Límite mensual <span class="text-[#76a72b]">*</span>
                </label>
                <div class="relative">
                    <span class="absolute left-3 top-1/2 -translate-y-1/2 text-[#878787] font-semibold">$</span>
                    <input type="text" name="amount" data-money inputmode="decimal" required
                        value="{{ old('amount') }}"
                        placeholder="0.00"
                        class="w-full rounded-xl border border-[#ababab]/40 bg-[#efeded]/50 dark:bg-white/5 pl-7 pr-12 py-3 text-[#373737] dark:text-white focus:outline-none focus:ring-2 focus:ring-[#76a72b] transition text-sm">
                    <span class="absolute right-3 top-1/2 -translate-y-1/2 text-[#ababab] text-xs font-semibold uppercase tracking-wider">MXN</span>
                </div>
                @error('amount')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
            </div>
        </div>

        <x-btn type="submit" class="w-full">Crear presupuesto</x-btn>
    </form>
</x-card>
@else
@if($budgets->isNotEmpty())
<p class="text-center text-xs text-[#ababab] mt-4">
    Todas las categorías de egreso ya tienen presupuesto.
</p>
@endif
@endif

</x-app-layout>
