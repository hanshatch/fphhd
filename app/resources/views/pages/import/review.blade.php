<x-app-layout title="Revisar importación">

@php
$income  = $rows->where('type', 'income')->count();
$expense = $rows->where('type', 'expense')->count();
$withCat = $rows->whereNotNull('category_id')->count();
@endphp

<div class="mb-5 flex items-center justify-between flex-wrap gap-3">
    <div>
        <h1 class="text-xl font-bold text-[#373737] dark:text-white font-['Nunito']">Revisar importación</h1>
        <p class="text-sm text-[#878787] mt-0.5">
            <span class="font-semibold text-[#373737] dark:text-white">{{ $account->name }}</span>
            · <span class="text-[#76a72b] font-semibold">{{ $income }} ingresos</span>
            · <span class="text-red-500 font-semibold">{{ $expense }} egresos</span>
            · {{ $withCat }} de {{ $rows->count() }} con categoría
        </p>
    </div>
    <div class="flex gap-2">
        <x-btn variant="secondary" href="{{ route('import.create') }}">← Subir otro</x-btn>
        <button type="submit" form="import-form"
            class="inline-flex items-center gap-2 px-5 py-2.5 bg-[#76a72b] hover:bg-[#659220] text-white text-sm font-semibold rounded-xl shadow-sm transition-all active:scale-95">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
            Importar {{ $rows->count() }} movimientos
        </button>
    </div>
</div>

{{-- Instrucción clara --}}
<div class="mb-4 p-3 bg-[#76a72b]/10 border border-[#76a72b]/20 rounded-xl flex items-start gap-3">
    <svg class="w-5 h-5 text-[#76a72b] flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
    </svg>
    <div class="text-xs text-[#4a7018] dark:text-[#76a72b]">
        <p class="font-semibold mb-0.5">El tipo (ingreso/egreso) ya está asignado automáticamente según la columna del estado de cuenta.</p>
        <p>Solo revisa la <strong>categoría</strong> de cada movimiento. Desmarca los que no quieras importar.</p>
    </div>
</div>

<form id="import-form" method="POST" action="{{ route('import.confirm') }}" class="space-y-2">
    @csrf

    @foreach($rows as $row)
    @php
    $isIncome = $row['type'] === 'income';
    $typeLabel = match($row['type']) {
        'income'   => 'Ingreso',
        'expense'  => 'Egreso',
        'transfer' => 'Transferencia',
        'interest' => 'Interés',
        'fee'      => 'Comisión',
        default    => $row['type'],
    };
    $typeBadge = $isIncome
        ? 'bg-[#76a72b]/15 text-[#4a7018] dark:text-[#76a72b]'
        : 'bg-red-50 text-red-600 dark:bg-red-500/10 dark:text-red-400';
    @endphp

    <div x-data="{ skipped: false }"
         :class="skipped ? 'opacity-30 scale-[0.99]' : ''"
         class="bg-white dark:bg-[#2a2a2a] rounded-xl border border-[#ababab]/20 dark:border-white/10 p-3.5 transition-all duration-150">

        {{-- Input oculto para el tipo (no editable en esta pantalla) --}}
        <input type="hidden" name="type[{{ $row['row_id'] }}]" value="{{ $row['type'] }}">

        <div class="flex items-center gap-3">

            {{-- Checkbox omitir --}}
            <input type="checkbox" name="skip[]" value="{{ $row['row_id'] }}"
                x-on:change="skipped = $event.target.checked"
                class="w-4 h-4 rounded border-[#ababab] text-red-400 focus:ring-red-300 cursor-pointer flex-shrink-0"
                title="Omitir este movimiento">

            {{-- Badge tipo --}}
            <span class="text-[10px] font-bold px-2 py-1 rounded-lg flex-shrink-0 {{ $typeBadge }}">
                {{ $typeLabel }}
            </span>

            {{-- Fecha --}}
            <span class="text-xs text-[#ababab] flex-shrink-0 hidden sm:block font-mono">
                {{ \Carbon\Carbon::parse($row['date'])->translatedFormat('d M') }}
            </span>

            {{-- Descripción --}}
            <input type="text" name="description[{{ $row['row_id'] }}]"
                value="{{ $row['description'] }}"
                class="flex-1 text-sm text-[#373737] dark:text-white bg-transparent border-0 border-b border-transparent hover:border-[#ababab]/40 focus:border-[#76a72b] focus:outline-none py-0.5 px-0 transition-colors min-w-0">

            {{-- Categoría --}}
            <select name="category_id[{{ $row['row_id'] }}]"
                class="rounded-lg border {{ ($row['category_id'] ?? null) ? 'border-[#76a72b]/40 bg-[#76a72b]/5' : 'border-orange-300 bg-orange-50 dark:border-orange-600/40 dark:bg-orange-500/10' }} px-2.5 py-1.5 text-xs text-[#373737] dark:text-white focus:outline-none focus:ring-1 focus:ring-[#76a72b] flex-shrink-0 min-w-[150px] max-w-[200px]">
                <option value="">— sin categoría —</option>
                @foreach($categories as $cat)
                <optgroup label="{{ $cat->name }}">
                    <option value="{{ $cat->id }}" {{ ($row['category_id'] ?? '') == $cat->id ? 'selected' : '' }}>
                        {{ $cat->name }}
                    </option>
                    @foreach($cat->children as $child)
                    <option value="{{ $child->id }}" {{ ($row['category_id'] ?? '') == $child->id ? 'selected' : '' }}>
                        ↳ {{ $child->name }}
                    </option>
                    @endforeach
                </optgroup>
                @endforeach
            </select>

            {{-- Monto --}}
            <span class="font-bold text-sm font-['Nunito'] flex-shrink-0 {{ $isIncome ? 'text-[#76a72b]' : 'text-red-500' }}">
                {{ $isIncome ? '+' : '-' }}${{ number_format((float)$row['amount'], 2) }}
            </span>

        </div>
    </div>
    @endforeach

</form>

{{-- Botón sticky --}}
<div class="sticky bottom-20 lg:bottom-4 mt-4 flex justify-end">
    <button type="submit" form="import-form"
        class="inline-flex items-center gap-2 px-6 py-3 bg-[#76a72b] hover:bg-[#659220] text-white font-bold rounded-xl shadow-xl transition-all active:scale-95">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
        Importar movimientos
    </button>
</div>

</x-app-layout>
