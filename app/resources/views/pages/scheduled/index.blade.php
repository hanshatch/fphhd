<x-app-layout title="Flujo programado">

@php
$today     = now()->day;
$isToday   = $month->isSameMonth(now());

// Primer día del mes (0=Dom … 6=Sáb) y días totales
$firstDow  = (int) $month->copy()->startOfMonth()->dayOfWeek; // 0=Dom
$daysInMonth = $month->daysInMonth;

$days = ['Dom','Lun','Mar','Mié','Jue','Vie','Sáb'];
@endphp

<x-page-header title="Flujo programado" />

{{-- ── Resumen del mes ───────────────────────────────────────────── --}}
<div class="grid grid-cols-3 gap-3 mb-5">
    <div class="bg-white dark:bg-[#2a2a2a] rounded-xl border border-[#ababab]/20 p-3 text-center">
        <p class="text-[10px] text-[#ababab] uppercase tracking-wider mb-1">Ingresos</p>
        <p class="text-base font-bold text-[#76a72b] tabular-nums">${{ number_format($totalIn, 2) }}</p>
    </div>
    <div class="bg-white dark:bg-[#2a2a2a] rounded-xl border border-[#ababab]/20 p-3 text-center">
        <p class="text-[10px] text-[#ababab] uppercase tracking-wider mb-1">Egresos</p>
        <p class="text-base font-bold text-red-500 tabular-nums">${{ number_format($totalOut, 2) }}</p>
    </div>
    <div class="rounded-xl p-3 text-center {{ ($totalIn - $totalOut) >= 0 ? 'bg-[#76a72b]/10 border border-[#76a72b]/20' : 'bg-red-50 border border-red-100' }}">
        <p class="text-[10px] text-[#ababab] uppercase tracking-wider mb-1">Neto</p>
        <p class="text-base font-bold tabular-nums {{ ($totalIn - $totalOut) >= 0 ? 'text-[#76a72b]' : 'text-red-500' }}">
            ${{ number_format($totalIn - $totalOut, 2) }}
        </p>
    </div>
</div>

{{-- ── Navegación de mes ─────────────────────────────────────────── --}}
<div class="flex items-center justify-between mb-4">
    <a href="?month={{ $prev }}"
       class="w-9 h-9 flex items-center justify-center rounded-full bg-white dark:bg-[#2a2a2a] border border-[#ababab]/20 text-[#878787] hover:text-[#76a72b] hover:border-[#76a72b] transition-colors">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
        </svg>
    </a>

    <h2 class="font-bold text-[#373737] dark:text-white capitalize">
        {{ $month->translatedFormat('F Y') }}
    </h2>

    <a href="?month={{ $next }}"
       class="w-9 h-9 flex items-center justify-center rounded-full bg-white dark:bg-[#2a2a2a] border border-[#ababab]/20 text-[#878787] hover:text-[#76a72b] hover:border-[#76a72b] transition-colors">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
        </svg>
    </a>
</div>

{{-- ── Calendario ────────────────────────────────────────────────── --}}
<div class="bg-white dark:bg-[#2a2a2a] rounded-2xl border border-[#ababab]/15 shadow-sm overflow-hidden mb-5">

    {{-- Cabecera días --}}
    <div class="grid grid-cols-7 border-b border-[#ababab]/10">
        @foreach($days as $d)
        <div class="py-2 text-center text-[10px] font-bold text-[#ababab] uppercase tracking-wider">{{ $d }}</div>
        @endforeach
    </div>

    {{-- Grilla de días --}}
    <div class="grid grid-cols-7">
        {{-- Celdas vacías al inicio --}}
        @for($i = 0; $i < $firstDow; $i++)
        <div class="border-b border-r border-[#ababab]/08 min-h-[52px] lg:min-h-[72px]"></div>
        @endfor

        {{-- Días del mes --}}
        @for($d = 1; $d <= $daysInMonth; $d++)
        @php
            $items     = $dayMap[$d] ?? [];
            $hasIncome = collect($items)->where('type', 'income')->isNotEmpty();
            $hasCharge = collect($items)->where('type', 'charge')->isNotEmpty();
            $isCurrentDay = $isToday && $d === $today;
            $col = ($firstDow + $d - 1) % 7; // columna 0=Dom
            $isSat = $col === 6;
            $isSun = $col === 0;
        @endphp
        <a href="#day-{{ $d }}"
           class="border-b border-r border-[#ababab]/08 min-h-[52px] lg:min-h-[72px] p-1.5 flex flex-col
               {{ count($items) ? 'cursor-pointer hover:bg-[#76a72b]/5 transition-colors' : '' }}
               {{ ($isSun || $isSat) ? 'bg-[#fafafa] dark:bg-white/[0.02]' : '' }}">

            {{-- Número de día --}}
            <span class="text-xs font-semibold w-6 h-6 flex items-center justify-center rounded-full flex-shrink-0 self-start
                {{ $isCurrentDay ? 'bg-[#76a72b] text-white' : 'text-[#878787]' }}">
                {{ $d }}
            </span>

            {{-- Indicadores --}}
            @if(count($items) > 0)
            <div class="flex flex-col gap-0.5 mt-1">
                @if($hasIncome)
                @php $inc = collect($items)->where('type','income')->sum('amount') @endphp
                <span class="text-[9px] lg:text-[10px] font-bold text-[#76a72b] tabular-nums leading-tight truncate">
                    +${{ number_format($inc, 0) }}
                </span>
                @endif
                @if($hasCharge)
                @php $out = collect($items)->where('type','charge')->sum('amount') @endphp
                <span class="text-[9px] lg:text-[10px] font-bold text-red-500 tabular-nums leading-tight truncate">
                    -${{ number_format($out, 0) }}
                </span>
                @endif
            </div>
            @endif
        </a>
        @endfor

        {{-- Celdas vacías al final para completar semana --}}
        @php $lastCol = ($firstDow + $daysInMonth - 1) % 7; @endphp
        @if($lastCol < 6)
        @for($i = $lastCol + 1; $i <= 6; $i++)
        <div class="border-b border-r border-[#ababab]/08 min-h-[52px] lg:min-h-[72px]"></div>
        @endfor
        @endif
    </div>
</div>

{{-- ── Detalle por día ───────────────────────────────────────────── --}}
@if(empty($dayMap))
<x-card class="text-center py-12">
    <svg class="mx-auto w-10 h-10 text-[#ababab] mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
    </svg>
    <p class="text-[#878787] font-medium text-sm">Sin movimientos programados este mes</p>
    <div class="flex gap-3 justify-center mt-4">
        <x-btn href="{{ route('recurring.create') }}" variant="secondary" class="text-sm">+ Cargo recurrente</x-btn>
        <x-btn href="{{ route('income-plans.create') }}" class="text-sm">+ Ingreso planeado</x-btn>
    </div>
</x-card>
@else

<div class="space-y-3">
@foreach($dayMap as $day => $items)
@php
    $date = $month->copy()->setDay($day);
    $isCurrentDay = $isToday && $day === $today;
    $isPast = $date->isPast() && !$isCurrentDay;
    $dayIncome = collect($items)->where('type','income')->sum('amount');
    $dayCharge = collect($items)->where('type','charge')->sum('amount');
@endphp

<div id="day-{{ $day }}" class="scroll-mt-4">
    {{-- Header del día --}}
    <div class="flex items-center gap-3 mb-2">
        <div class="w-8 h-8 rounded-full flex items-center justify-center text-sm font-bold flex-shrink-0
            {{ $isCurrentDay ? 'bg-[#76a72b] text-white' : 'bg-white dark:bg-[#2a2a2a] border border-[#ababab]/20 text-[#878787]' }}">
            {{ $day }}
        </div>
        <div class="flex-1">
            <span class="text-xs font-bold text-[#373737] dark:text-white capitalize">
                {{ $date->translatedFormat('l d \d\e F') }}
            </span>
            @if($isPast)
            <span class="ml-2 text-[10px] text-[#ababab] bg-[#efeded] dark:bg-white/10 px-1.5 py-0.5 rounded-full">Pasado</span>
            @endif
        </div>
        <div class="flex items-center gap-2 text-xs font-semibold">
            @if($dayIncome > 0)<span class="text-[#76a72b]">+${{ number_format($dayIncome, 2) }}</span>@endif
            @if($dayCharge > 0)<span class="text-red-500">-${{ number_format($dayCharge, 2) }}</span>@endif
        </div>
    </div>

    {{-- Items del día --}}
    <div class="bg-white dark:bg-[#2a2a2a] rounded-2xl overflow-hidden border border-[#ababab]/15 shadow-sm {{ $isPast ? 'opacity-60' : '' }}">
        @foreach($items as $i => $item)
        <div class="flex items-center gap-3 px-4 py-3 {{ $i < count($items) - 1 ? 'border-b border-[#ababab]/10' : '' }}">

            {{-- Ícono tipo --}}
            <div class="w-9 h-9 rounded-[10px] flex items-center justify-center flex-shrink-0"
                 style="background-color: {{ $item['color'] }}18">
                @if($item['type'] === 'income')
                <svg class="w-4 h-4" style="color: {{ $item['color'] }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 11l5-5m0 0l5 5m-5-5v12"/>
                </svg>
                @else
                <svg class="w-4 h-4" style="color: {{ $item['color'] }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 13l-5 5m0 0l-5-5m5 5V6"/>
                </svg>
                @endif
            </div>

            {{-- Info --}}
            <div class="flex-1 min-w-0">
                <p class="text-sm font-semibold text-[#373737] dark:text-white truncate">{{ $item['label'] }}</p>
                <div class="flex items-center gap-1.5 mt-0.5 flex-wrap">
                    <span class="text-[11px] px-1.5 py-0.5 rounded-full font-medium"
                          style="background-color: {{ $item['color'] }}15; color: {{ $item['color'] }}">
                        {{ $item['type'] === 'income' ? 'Ingreso' : 'Cargo' }}
                    </span>
                    @if(!empty($item['account']))
                    <span class="text-[11px] text-[#ababab]">· {{ $item['account'] }}</span>
                    @endif
                    @if(!empty($item['category']))
                    <span class="text-[11px] text-[#ababab]">· {{ $item['category'] }}</span>
                    @endif
                    @if(!empty($item['source']))
                    <span class="text-[11px] text-[#ababab]">· {{ $item['source'] }}</span>
                    @endif
                </div>
            </div>

            {{-- Monto --}}
            <p class="text-sm font-bold tabular-nums flex-shrink-0"
               style="color: {{ $item['color'] }}">
                {{ $item['type'] === 'income' ? '+' : '-' }}${{ number_format($item['amount'], 2) }}
            </p>
        </div>
        @endforeach
    </div>
</div>
@endforeach
</div>
@endif

</x-app-layout>
