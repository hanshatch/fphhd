<x-app-layout title="Revisar importación">

@php
$total   = $rows->count();
$income  = $rows->where('type', 'income')->count();
$expense = $rows->where('type', 'expense')->count();
$missing = $rows->whereNull('category_id')->count();
@endphp

<style>
.row-income  { --row-accent: #76a72b; }
.row-expense { --row-accent: #ef4444; }
.tx-row { border-left: 3px solid var(--row-accent); }
.tx-row.skipped { opacity: 0.35; }
.cat-select { font-size: 12px; }
.cat-select.missing { border-color: #f97316; background: #fff7ed; color: #9a3412; }
.cat-select.has-cat  { border-color: #76a72b40; background: #76a72b08; }
</style>

{{-- ── Sticky header ─────────────────────────────────────────── --}}
<div class="sticky top-14 lg:top-0 z-10 bg-[#efeded] dark:bg-[#1a1a1a] pt-2 pb-3">

    {{-- Title row --}}
    <div class="flex items-center justify-between mb-3">
        <div class="min-w-0">
            <h1 class="text-base font-bold text-[#373737] dark:text-white font-['Nunito'] leading-tight">Revisar importación</h1>
            <p class="text-xs text-[#878787] truncate">{{ $account->name }}</p>
        </div>
        <div class="flex items-center gap-2 flex-shrink-0 ml-3">
            <a href="{{ route('import.create') }}"
               class="h-8 px-3 flex items-center text-xs font-medium text-[#878787] border border-[#ababab]/50 rounded-lg hover:bg-white dark:hover:bg-white/10 transition-colors">
                ← Volver
            </a>
            <button type="submit" form="import-form"
                class="h-8 px-4 flex items-center gap-1.5 bg-[#76a72b] hover:bg-[#659220] text-white text-xs font-bold rounded-lg transition-all active:scale-95 shadow-sm">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                Importar
            </button>
        </div>
    </div>

    {{-- Stats pills — horizontal scroll en mobile --}}
    <div class="flex items-center gap-2 overflow-x-auto pb-0.5 scrollbar-hide">
        <span class="flex-shrink-0 flex items-center gap-1.5 h-7 px-3 bg-white dark:bg-white/10 border border-[#ababab]/30 rounded-full text-xs font-semibold text-[#373737] dark:text-white">
            <span class="w-1.5 h-1.5 rounded-full bg-[#ababab]"></span>
            {{ $total }} movimientos
        </span>
        <span class="flex-shrink-0 flex items-center gap-1.5 h-7 px-3 bg-[#76a72b]/10 border border-[#76a72b]/25 rounded-full text-xs font-semibold text-[#4a7018] dark:text-[#76a72b]">
            <span class="w-1.5 h-1.5 rounded-full bg-[#76a72b]"></span>
            {{ $income }} ingresos
        </span>
        <span class="flex-shrink-0 flex items-center gap-1.5 h-7 px-3 bg-red-50 dark:bg-red-500/10 border border-red-200 dark:border-red-500/25 rounded-full text-xs font-semibold text-red-600 dark:text-red-400">
            <span class="w-1.5 h-1.5 rounded-full bg-red-400"></span>
            {{ $expense }} egresos
        </span>
        @if($missing > 0)
        <span class="flex-shrink-0 flex items-center gap-1.5 h-7 px-3 bg-orange-50 dark:bg-orange-500/10 border border-orange-200 dark:border-orange-500/25 rounded-full text-xs font-semibold text-orange-600 dark:text-orange-400">
            <span class="w-1.5 h-1.5 rounded-full bg-orange-400"></span>
            {{ $missing }} sin categoría
        </span>
        @else
        <span class="flex-shrink-0 flex items-center gap-1.5 h-7 px-3 bg-[#76a72b]/10 border border-[#76a72b]/25 rounded-full text-xs font-semibold text-[#4a7018] dark:text-[#76a72b]">
            ✓ todo categorizado
        </span>
        @endif
        <span class="flex-shrink-0 text-[10px] text-[#ababab] ml-1 hidden sm:block">
            Desmarca para omitir · solo elige categoría
        </span>
    </div>
</div>

{{-- ── Lista de transacciones ───────────────────────────────── --}}
<form id="import-form" method="POST" action="{{ route('import.confirm') }}">
    @csrf

    <div class="bg-white dark:bg-[#2a2a2a] rounded-2xl border border-[#ababab]/20 dark:border-white/10 overflow-hidden">

        @foreach($rows as $loop_index => $row)
        @php
            $isIncome = $row['type'] === 'income';
            $hasCat   = ! empty($row['category_id']);
            $rowClass = $isIncome ? 'row-income' : 'row-expense';
        @endphp

        <div x-data="{ on: true }"
             :class="on ? '' : 'skipped'"
             class="tx-row {{ $rowClass }} {{ $loop_index > 0 ? 'border-t border-[#efeded] dark:border-white/5' : '' }} transition-all duration-150 px-3 py-2.5">

            {{-- Línea 1: checkbox + badge + descripción + monto --}}
            <div class="flex items-center gap-2.5">

                {{-- Checkbox --}}
                <input type="checkbox" name="include[]" value="{{ $row['row_id'] }}" checked
                    x-model="on"
                    class="w-3.5 h-3.5 rounded border-[#ababab] text-[#76a72b] focus:ring-[#76a72b] cursor-pointer flex-shrink-0">

                {{-- Badge tipo --}}
                <input type="hidden" name="type[{{ $row['row_id'] }}]" value="{{ $row['type'] }}">
                <span class="flex-shrink-0 text-[9px] font-black uppercase tracking-wider px-1.5 py-0.5 rounded
                    {{ $isIncome
                        ? 'bg-[#76a72b] text-white'
                        : 'bg-red-500 text-white' }}">
                    {{ $isIncome ? 'ING' : 'EGR' }}
                </span>

                {{-- Descripción --}}
                <input type="text" name="description[{{ $row['row_id'] }}]"
                    value="{{ $row['description'] }}"
                    class="flex-1 text-[13px] font-semibold text-[#373737] dark:text-white bg-transparent border-0 outline-none focus:bg-[#efeded]/50 dark:focus:bg-white/5 rounded px-1 py-0.5 min-w-0 transition-colors"
                    style="min-width:0">

                {{-- Monto --}}
                <span class="flex-shrink-0 text-[13px] font-bold font-['Nunito'] tabular-nums
                    {{ $isIncome ? 'text-[#76a72b]' : 'text-red-500' }}">
                    {{ $isIncome ? '+' : '−' }}${{ number_format((float)$row['amount'], 2) }}
                </span>
            </div>

            {{-- Línea 2: fecha + categoría --}}
            <div class="flex items-center gap-2 mt-1.5 pl-6">

                {{-- Fecha --}}
                <span class="flex-shrink-0 text-[10px] text-[#ababab] tabular-nums whitespace-nowrap">
                    {{ \Carbon\Carbon::parse($row['date'])->translatedFormat('d M y') }}
                </span>

                {{-- Divisor --}}
                <span class="text-[#efeded] dark:text-white/10 flex-shrink-0">·</span>

                {{-- Categoría --}}
                <select name="category_id[{{ $row['row_id'] }}]"
                    class="cat-select flex-1 h-6 rounded-md border px-2 focus:outline-none focus:ring-1 focus:ring-[#76a72b] transition-colors
                    {{ $hasCat ? 'has-cat' : 'missing' }}">
                    <option value="">{{ $hasCat ? '' : '⚠ Asignar categoría' }}</option>
                    @foreach($categories as $cat)
                    <optgroup label="{{ $cat->name }}">
                        <option value="{{ $cat->id }}" {{ ($row['category_id'] ?? '') == $cat->id ? 'selected' : '' }}>{{ $cat->name }}</option>
                        @foreach($cat->children as $child)
                        <option value="{{ $child->id }}" {{ ($row['category_id'] ?? '') == $child->id ? 'selected' : '' }}>↳ {{ $child->name }}</option>
                        @endforeach
                    </optgroup>
                    @endforeach
                </select>
            </div>
        </div>
        @endforeach

    </div>
</form>

{{-- ── Botón sticky bottom ──────────────────────────────────── --}}
<div class="sticky bottom-20 lg:bottom-6 mt-4 flex justify-end">
    <button type="submit" form="import-form"
        class="flex items-center gap-2 px-5 py-2.5 bg-[#76a72b] hover:bg-[#659220] text-white text-sm font-bold rounded-xl shadow-2xl ring-4 ring-[#76a72b]/20 transition-all active:scale-95">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
        Importar {{ $total }} movimientos
    </button>
</div>

</x-app-layout>
