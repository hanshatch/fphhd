<x-app-layout title="Revisar importación">

<div class="mb-5 flex items-center justify-between flex-wrap gap-3">
    <div>
        <h1 class="text-xl font-bold text-[#373737] dark:text-white font-['Nunito']">Revisar importación</h1>
        <p class="text-sm text-[#878787] mt-0.5">
            Cuenta: <span class="font-semibold text-[#373737] dark:text-white">{{ $account->name }}</span>
            · {{ $rows->count() }} movimientos encontrados
        </p>
    </div>
    <div class="flex gap-2">
        <x-btn variant="secondary" href="{{ route('import.create') }}">← Subir otro</x-btn>
        <button type="submit" form="import-form"
            class="inline-flex items-center gap-2 px-5 py-2.5 bg-[#76a72b] hover:bg-[#659220] text-white text-sm font-semibold rounded-xl shadow-sm transition-all active:scale-95">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
            Importar seleccionados
        </button>
    </div>
</div>

{{-- Leyenda rápida --}}
<x-card class="p-4 mb-4 flex flex-wrap gap-4 items-center">
    <p class="text-xs font-bold text-[#878787] uppercase tracking-wider">Leyenda:</p>
    <span class="flex items-center gap-1.5 text-xs text-[#878787]"><span class="w-3 h-3 rounded-full bg-[#76a72b] inline-block"></span> Ingreso</span>
    <span class="flex items-center gap-1.5 text-xs text-[#878787]"><span class="w-3 h-3 rounded-full bg-red-400 inline-block"></span> Egreso</span>
    <span class="flex items-center gap-1.5 text-xs text-[#878787]"><span class="w-3 h-3 rounded-full bg-[#ababab] inline-block"></span> Transferencia</span>
    <span class="ml-auto text-xs text-[#ababab]">Desmarca una fila para omitirla · Edita tipo y categoría libremente</span>
</x-card>

<form id="import-form" method="POST" action="{{ route('import.confirm') }}" class="space-y-2">
    @csrf

    @foreach($rows as $row)
    @php
    $isIncome = in_array($row['type'], ['income', 'interest']);
    $dotColor = $isIncome ? 'bg-[#76a72b]' : ($row['type'] === 'transfer' ? 'bg-[#ababab]' : 'bg-red-400');
    @endphp

    <div x-data="{ skipped: false }"
         :class="skipped ? 'opacity-40' : ''"
         class="bg-white dark:bg-[#2a2a2a] rounded-xl border border-[#ababab]/20 dark:border-white/10 p-3 transition-opacity">

        <div class="flex items-start gap-3">

            {{-- Checkbox omitir --}}
            <div class="pt-1">
                <input type="checkbox" name="skip[]" value="{{ $row['row_id'] }}"
                    x-on:change="skipped = $event.target.checked"
                    class="w-4 h-4 rounded border-[#ababab] text-red-400 focus:ring-red-300 cursor-pointer"
                    title="Omitir este movimiento">
            </div>

            {{-- Dot tipo --}}
            <div class="pt-1.5">
                <div class="w-2.5 h-2.5 rounded-full {{ $dotColor }}"></div>
            </div>

            {{-- Info principal --}}
            <div class="flex-1 min-w-0 grid grid-cols-1 sm:grid-cols-[1fr_auto] gap-2">
                <div>
                    {{-- Fecha + descripción editable --}}
                    <div class="flex items-center gap-2 mb-1.5">
                        <span class="text-xs text-[#ababab] flex-shrink-0 font-mono">{{ \Carbon\Carbon::parse($row['date'])->translatedFormat('d M Y') }}</span>
                        <input type="text" name="description[{{ $row['row_id'] }}]"
                            value="{{ $row['description'] }}"
                            class="flex-1 text-sm font-medium text-[#373737] dark:text-white bg-transparent border-0 border-b border-transparent hover:border-[#ababab]/40 focus:border-[#76a72b] focus:outline-none py-0 px-0 transition-colors min-w-0 truncate">
                    </div>

                    {{-- Tipo + Categoría --}}
                    <div class="flex flex-wrap gap-2">
                        {{-- Tipo --}}
                        <select name="type[{{ $row['row_id'] }}]"
                            class="rounded-lg border border-[#ababab]/30 bg-[#efeded]/50 dark:bg-white/5 px-2.5 py-1.5 text-xs text-[#373737] dark:text-white focus:outline-none focus:ring-1 focus:ring-[#76a72b]">
                            <option value="income"   {{ $row['type'] === 'income'   ? 'selected' : '' }}>💰 Ingreso</option>
                            <option value="expense"  {{ $row['type'] === 'expense'  ? 'selected' : '' }}>💸 Egreso</option>
                            <option value="transfer" {{ $row['type'] === 'transfer' ? 'selected' : '' }}>🔄 Transferencia</option>
                            <option value="interest" {{ $row['type'] === 'interest' ? 'selected' : '' }}>📈 Interés</option>
                            <option value="fee"      {{ $row['type'] === 'fee'      ? 'selected' : '' }}>🏦 Comisión</option>
                        </select>

                        {{-- Categoría --}}
                        <select name="category_id[{{ $row['row_id'] }}]"
                            class="rounded-lg border border-[#ababab]/30 bg-[#efeded]/50 dark:bg-white/5 px-2.5 py-1.5 text-xs text-[#373737] dark:text-white focus:outline-none focus:ring-1 focus:ring-[#76a72b] flex-1 min-w-[140px]">
                            <option value="">Sin categoría</option>
                            @foreach($categories as $cat)
                            <optgroup label="{{ $cat->name }}">
                                <option value="{{ $cat->id }}" {{ ($row['category_id'] ?? '') == $cat->id ? 'selected' : '' }}>
                                    {{ $cat->name }}
                                </option>
                                @foreach($cat->children as $child)
                                <option value="{{ $child->id }}" {{ ($row['category_id'] ?? '') == $child->id ? 'selected' : '' }}>
                                    &nbsp;&nbsp;{{ $child->name }}
                                </option>
                                @endforeach
                            </optgroup>
                            @endforeach
                        </select>
                    </div>
                </div>

                {{-- Monto --}}
                <div class="text-right flex-shrink-0">
                    <span class="font-bold text-base font-['Nunito'] {{ $isIncome ? 'text-[#76a72b]' : 'text-red-500' }}">
                        {{ $isIncome ? '+' : '-' }}${{ number_format((float)$row['amount'], 2) }}
                    </span>
                </div>
            </div>
        </div>
    </div>
    @endforeach

</form>

{{-- Botón fijo en mobile --}}
<div class="sticky bottom-20 lg:bottom-4 mt-4 flex justify-end">
    <button type="submit" form="import-form"
        class="inline-flex items-center gap-2 px-6 py-3 bg-[#76a72b] hover:bg-[#659220] text-white font-bold rounded-xl shadow-xl transition-all active:scale-95">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
        Importar movimientos
    </button>
</div>

</x-app-layout>
