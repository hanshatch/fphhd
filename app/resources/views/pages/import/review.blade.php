<x-app-layout title="Revisar importación">

@php
$total   = $rows->count();
$income  = $rows->where('type', 'income')->count();
$expense = $rows->where('type', 'expense')->count();
$withCat = $rows->whereNotNull('category_id')->count();
$missing = $total - $withCat;
@endphp

{{-- Header compacto --}}
<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-4">
    <div>
        <h1 class="text-lg font-bold text-[#373737] dark:text-white font-['Nunito']">Revisar importación</h1>
        <p class="text-sm text-[#878787]">{{ $account->name }}</p>
    </div>
    <div class="flex items-center gap-2">
        <a href="{{ route('import.create') }}" class="px-3 py-2 text-sm text-[#878787] hover:text-[#373737] border border-[#ababab]/40 rounded-lg hover:bg-[#efeded] transition-colors">← Volver</a>
        <button type="submit" form="import-form"
            class="flex items-center gap-2 px-4 py-2 bg-[#76a72b] hover:bg-[#659220] text-white text-sm font-bold rounded-lg transition-all active:scale-95 shadow-sm">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
            Importar
        </button>
    </div>
</div>

{{-- Stats bar --}}
<div class="grid grid-cols-4 gap-2 mb-4">
    <div class="bg-white dark:bg-[#2a2a2a] rounded-xl p-3 border border-[#ababab]/20 text-center">
        <div class="text-xl font-bold text-[#373737] dark:text-white font-['Nunito']">{{ $total }}</div>
        <div class="text-[10px] text-[#ababab] uppercase tracking-wider">Total</div>
    </div>
    <div class="bg-[#76a72b]/10 rounded-xl p-3 border border-[#76a72b]/20 text-center">
        <div class="text-xl font-bold text-[#76a72b] font-['Nunito']">{{ $income }}</div>
        <div class="text-[10px] text-[#76a72b]/70 uppercase tracking-wider">Ingresos</div>
    </div>
    <div class="bg-red-50 dark:bg-red-500/10 rounded-xl p-3 border border-red-200/50 dark:border-red-500/20 text-center">
        <div class="text-xl font-bold text-red-500 font-['Nunito']">{{ $expense }}</div>
        <div class="text-[10px] text-red-400 uppercase tracking-wider">Egresos</div>
    </div>
    <div class="{{ $missing > 0 ? 'bg-orange-50 border-orange-200/50 dark:bg-orange-500/10 dark:border-orange-500/20' : 'bg-[#efeded] border-[#ababab]/20 dark:bg-white/5' }} rounded-xl p-3 border text-center">
        <div class="text-xl font-bold {{ $missing > 0 ? 'text-orange-500' : 'text-[#76a72b]' }} font-['Nunito']">{{ $missing > 0 ? $missing : '✓' }}</div>
        <div class="text-[10px] {{ $missing > 0 ? 'text-orange-400' : 'text-[#ababab]' }} uppercase tracking-wider">{{ $missing > 0 ? 'Sin cat.' : 'Completo' }}</div>
    </div>
</div>

{{-- Instrucción --}}
<div class="flex items-center gap-2 mb-3 text-xs text-[#878787]">
    <svg class="w-4 h-4 text-[#76a72b] flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
    Tipo asignado automáticamente · Solo elige <strong class="text-[#373737] dark:text-white mx-1">categoría</strong> · Desmarca para omitir una fila
</div>

<form id="import-form" method="POST" action="{{ route('import.confirm') }}">
    @csrf

    {{-- Encabezado de tabla --}}
    <div class="hidden sm:grid grid-cols-[auto_1fr_auto_auto] gap-3 px-4 pb-2 text-[10px] font-bold text-[#ababab] uppercase tracking-wider">
        <span class="w-5"></span>
        <span>Descripción · Fecha</span>
        <span class="w-48 text-center">Categoría</span>
        <span class="w-28 text-right">Monto</span>
    </div>

    <div class="space-y-1.5">
    @foreach($rows as $row)
    @php
        $isIncome = $row['type'] === 'income';
        $hasCat   = ! empty($row['category_id']);
    @endphp

    <div x-data="{ included: true }"
         class="group relative bg-white dark:bg-[#2a2a2a] rounded-xl border transition-all duration-150"
         :class="included
            ? 'border-[#ababab]/20 dark:border-white/10'
            : 'border-dashed border-[#ababab]/30 opacity-40'"
    >
        {{-- Barra lateral de color --}}
        <div class="absolute left-0 top-0 bottom-0 w-1 rounded-l-xl {{ $isIncome ? 'bg-[#76a72b]' : 'bg-red-400' }}"
             :class="included ? 'opacity-100' : 'opacity-30'">
        </div>

        <div class="pl-4 pr-3 py-3 grid grid-cols-[auto_1fr_auto] sm:grid-cols-[auto_1fr_auto_auto] gap-x-3 gap-y-1 items-center">

            {{-- Checkbox incluir (checked = importar, uncheck = omitir) --}}
            <input type="checkbox" name="include[]" value="{{ $row['row_id'] }}" checked
                x-model="included"
                class="w-4 h-4 rounded border-[#ababab] text-[#76a72b] focus:ring-[#76a72b] cursor-pointer"
                title="Desmarcar para omitir">

            {{-- Descripción + fecha + tipo --}}
            <div class="min-w-0">
                <div class="flex items-center gap-2 flex-wrap">
                    <span class="text-[10px] font-bold px-1.5 py-0.5 rounded-md {{ $isIncome ? 'bg-[#76a72b]/15 text-[#4a7018] dark:text-[#76a72b]' : 'bg-red-50 text-red-600 dark:bg-red-500/10 dark:text-red-400' }}">
                        {{ $isIncome ? 'Ingreso' : 'Egreso' }}
                    </span>
                    <input type="hidden" name="type[{{ $row['row_id'] }}]" value="{{ $row['type'] }}">
                    <input type="text" name="description[{{ $row['row_id'] }}]"
                        value="{{ $row['description'] }}"
                        class="flex-1 text-sm font-semibold text-[#373737] dark:text-white bg-transparent border-0 focus:outline-none focus:border-b focus:border-[#76a72b] min-w-0 py-0"
                        style="min-width:100px">
                </div>
                <div class="text-[11px] text-[#ababab] mt-0.5">
                    {{ \Carbon\Carbon::parse($row['date'])->translatedFormat('d \d\e M \d\e Y') }}
                </div>
            </div>

            {{-- Categoría --}}
            <div class="col-span-1 sm:col-span-1 sm:w-48">
                <select name="category_id[{{ $row['row_id'] }}]"
                    class="w-full text-xs rounded-lg px-2.5 py-2 border focus:outline-none focus:ring-1 focus:ring-[#76a72b] transition-colors
                    {{ $hasCat
                        ? 'border-[#76a72b]/30 bg-[#76a72b]/5 text-[#373737] dark:text-white dark:bg-[#76a72b]/10'
                        : 'border-orange-300 bg-orange-50 text-orange-800 dark:border-orange-500/40 dark:bg-orange-500/10 dark:text-orange-300' }}">
                    <option value="">{{ $hasCat ? '' : '⚠ sin categoría' }}</option>
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

            {{-- Monto --}}
            <div class="text-right col-start-3 sm:col-start-4 row-start-1 sm:row-start-auto">
                <span class="font-bold text-sm font-['Nunito'] {{ $isIncome ? 'text-[#76a72b]' : 'text-red-500' }}">
                    {{ $isIncome ? '+' : '−' }}${{ number_format((float)$row['amount'], 2) }}
                </span>
            </div>

        </div>
    </div>
    @endforeach
    </div>
</form>

{{-- Botón sticky bottom --}}
<div class="sticky bottom-20 lg:bottom-6 mt-5 flex justify-end pointer-events-none">
    <button type="submit" form="import-form" pointer-events-auto
        class="pointer-events-auto flex items-center gap-2 px-6 py-3 bg-[#76a72b] hover:bg-[#659220] text-white font-bold rounded-xl shadow-2xl transition-all active:scale-95 text-sm">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
        Importar {{ $total }} movimientos
    </button>
</div>

</x-app-layout>
