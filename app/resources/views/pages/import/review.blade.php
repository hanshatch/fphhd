<x-app-layout title="Revisar importación">

@php
$total   = $rows->count();
$income  = $rows->where('type', 'income')->count();
$expense = $rows->where('type', 'expense')->count();
$missing = $rows->whereNull('category_id')->count();

// Agrupar por fecha para mostrar separadores de fecha
$grouped = $rows->groupBy('date')->sortKeysDesc();
@endphp

<style>
/* Category select styled as chip */
.cat-chip {
    height: 26px;
    padding: 0 10px;
    border-radius: 999px;
    font-size: 11px;
    font-weight: 600;
    cursor: pointer;
    max-width: 160px;
    white-space: nowrap;
    overflow: hidden;
    transition: all 0.15s;
    font-family: 'Roboto', sans-serif;
}
.cat-chip:focus { outline: none; box-shadow: 0 0 0 2px #76a72b66; }
.cat-chip.ok  { border: 1.5px solid #76a72b44; background: #76a72b0d; color: #3a5f0f; }
.cat-chip.miss{ border: 1.5px solid #f9731680; background: #fff7ed; color: #9a3412; }
.dark .cat-chip.ok  { background: rgba(118,167,43,0.12); color: #a3d46a; border-color: #76a72b55; }
.dark .cat-chip.miss{ background: rgba(249,115,22,0.12); color: #fb923c; border-color: #f9731655; }

/* Row hover */
.tx-row:hover { background: #fafaf8; }
.dark .tx-row:hover { background: rgba(255,255,255,0.03); }

/* Hide scrollbar for pills */
.pills-row { scrollbar-width: none; -ms-overflow-style: none; }
.pills-row::-webkit-scrollbar { display: none; }

/* Amount tabular nums */
.amount { font-variant-numeric: tabular-nums; }

/* Date header */
.date-sep { font-size: 10px; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; }
</style>

{{-- ── Sticky top bar ──────────────────────────────────────── --}}
<div class="sticky top-14 lg:top-0 z-10 bg-[#efeded] dark:bg-[#111] pt-3 pb-2 space-y-2">

    <div class="flex items-center justify-between">
        <div>
            <p class="text-base font-bold text-[#373737] dark:text-white font-['Nunito'] leading-none">Revisar importación</p>
            <p class="text-xs text-[#ababab] mt-0.5">{{ $account->name }}</p>
        </div>
        <div class="flex gap-2">
            <a href="{{ route('import.create') }}"
               class="h-8 px-3 text-xs font-semibold text-[#878787] border border-[#ababab]/50 rounded-lg hover:bg-white dark:hover:bg-white/10 transition-colors flex items-center">
                ← Volver
            </a>
            <button type="submit" form="import-form"
                class="h-8 px-4 text-xs font-bold text-white bg-[#76a72b] hover:bg-[#5e8a22] rounded-lg transition-all active:scale-95 shadow flex items-center gap-1.5">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                Importar
            </button>
        </div>
    </div>

    {{-- Pills stats --}}
    <div class="pills-row flex gap-1.5 overflow-x-auto">
        <span class="flex-none h-6 px-2.5 flex items-center gap-1.5 rounded-full bg-white dark:bg-white/10 border border-[#ababab]/30 text-[11px] font-semibold text-[#373737] dark:text-white">
            <span class="w-1.5 h-1.5 rounded-full bg-[#ababab]"></span>{{ $total }} total
        </span>
        <span class="flex-none h-6 px-2.5 flex items-center gap-1.5 rounded-full bg-[#76a72b]/10 border border-[#76a72b]/20 text-[11px] font-semibold text-[#3a6b10] dark:text-[#86c63a]">
            <span class="w-1.5 h-1.5 rounded-full bg-[#76a72b]"></span>{{ $income }} ingresos
        </span>
        <span class="flex-none h-6 px-2.5 flex items-center gap-1.5 rounded-full bg-red-50 dark:bg-red-500/10 border border-red-200 dark:border-red-500/20 text-[11px] font-semibold text-red-600 dark:text-red-400">
            <span class="w-1.5 h-1.5 rounded-full bg-red-400"></span>{{ $expense }} egresos
        </span>
        @if($missing > 0)
        <span class="flex-none h-6 px-2.5 flex items-center gap-1.5 rounded-full bg-amber-50 dark:bg-amber-500/10 border border-amber-300 dark:border-amber-500/20 text-[11px] font-semibold text-amber-700 dark:text-amber-400">
            <span class="w-1.5 h-1.5 rounded-full bg-amber-400"></span>{{ $missing }} sin categoría
        </span>
        @else
        <span class="flex-none h-6 px-2.5 flex items-center gap-1.5 rounded-full bg-[#76a72b]/10 border border-[#76a72b]/20 text-[11px] font-semibold text-[#3a6b10] dark:text-[#86c63a]">
            ✓ Todo categorizado
        </span>
        @endif
        <span class="flex-none text-[10px] text-[#ababab] flex items-center pl-1">
            Desmarca para omitir
        </span>
    </div>
</div>

{{-- ── Tabla de transacciones ───────────────────────────────── --}}
<form id="import-form" method="POST" action="{{ route('import.confirm') }}">
    @csrf

    <div class="bg-white dark:bg-[#1e1e1e] rounded-2xl border border-[#e8e8e4] dark:border-white/10 overflow-hidden mt-1">

        @php $prevDate = null; @endphp
        @foreach($rows as $row)
        @php
            $isIncome = $row['type'] === 'income';
            $hasCat   = !empty($row['category_id']);
            $accent   = $isIncome ? '#76a72b' : '#ef4444';
        @endphp

        {{-- Date separator --}}
        @if($row['date'] !== $prevDate)
        @php $prevDate = $row['date']; @endphp
        <div class="date-sep px-4 py-1.5 bg-[#f6f6f3] dark:bg-white/[0.03] text-[#ababab] border-b border-[#efefeb] dark:border-white/5
            {{ $loop->first ? '' : 'border-t border-[#efefeb] dark:border-white/5' }}">
            {{ \Carbon\Carbon::parse($row['date'])->translatedFormat('l, d \d\e M \d\e Y') }}
        </div>
        @endif

        {{-- Transaction row --}}
        <div x-data="{ on: true }"
             class="tx-row relative flex items-center gap-3 px-4 py-2.5 border-b border-[#f0f0ec] dark:border-white/[0.04] transition-all"
             :class="!on && 'opacity-30 line-through'">

            {{-- Left accent bar --}}
            <div class="absolute left-0 top-0 bottom-0 w-[3px] rounded-l-sm transition-colors"
                 style="background:{{ $accent }}" :style="on ? '' : 'opacity:0.2'">
            </div>

            {{-- Checkbox --}}
            <input type="checkbox" name="include[]" value="{{ $row['row_id'] }}" checked
                x-model="on"
                class="w-3.5 h-3.5 flex-none rounded border-[#ababab] text-[#76a72b] focus:ring-[#76a72b]/30 cursor-pointer">

            {{-- Hidden type --}}
            <input type="hidden" name="type[{{ $row['row_id'] }}]" value="{{ $row['type'] }}">

            {{-- Color dot --}}
            <span class="flex-none w-2 h-2 rounded-full mt-px" style="background:{{ $accent }}"></span>

            {{-- Description --}}
            <div class="flex-1 min-w-0">
                <input type="text" name="description[{{ $row['row_id'] }}]"
                    value="{{ $row['description'] }}"
                    class="w-full text-[13px] font-semibold text-[#2d2d2d] dark:text-white bg-transparent border-0 p-0 focus:outline-none focus:bg-[#f6f6f3] dark:focus:bg-white/5 rounded px-1 -mx-1 transition-colors truncate"
                    style="font-family:'Nunito',sans-serif">
            </div>

            {{-- Category chip --}}
            <select name="category_id[{{ $row['row_id'] }}]"
                class="cat-chip flex-none {{ $hasCat ? 'ok' : 'miss' }}">
                <option value="">{{ $hasCat ? '' : '⚠ Categoría' }}</option>
                @foreach($categories as $cat)
                <optgroup label="{{ $cat->name }}">
                    <option value="{{ $cat->id }}" {{ ($row['category_id'] ?? '') == $cat->id ? 'selected' : '' }}>{{ $cat->name }}</option>
                    @foreach($cat->children as $child)
                    <option value="{{ $child->id }}" {{ ($row['category_id'] ?? '') == $child->id ? 'selected' : '' }}>↳ {{ $child->name }}</option>
                    @endforeach
                </optgroup>
                @endforeach
            </select>

            {{-- Amount --}}
            <span class="amount flex-none w-24 text-right text-[13px] font-bold"
                  style="color:{{ $accent }};font-family:'Nunito',sans-serif">
                {{ $isIncome ? '+' : '−' }}${{ number_format((float)$row['amount'], 2) }}
            </span>

        </div>
        @endforeach

    </div>
</form>

{{-- Floating import button --}}
<div class="sticky bottom-20 lg:bottom-6 mt-4 flex justify-end pointer-events-none">
    <button type="submit" form="import-form"
        class="pointer-events-auto flex items-center gap-2 px-5 py-2.5 bg-[#76a72b] hover:bg-[#5e8a22] text-white text-sm font-bold rounded-xl shadow-2xl ring-4 ring-[#76a72b]/20 transition-all active:scale-95">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
        Importar {{ $total }} movimientos
    </button>
</div>

</x-app-layout>
