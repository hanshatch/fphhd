<x-app-layout title="Importar movimientos">
    <x-page-header title="Importar a {{ $account->name }}" :back="route('accounts.show', $account)" />

    @php
        $dupes = collect($rows)->filter(fn ($r) => $r['duplicate'])->count();
    @endphp

    @if(empty($rows))
    <x-card class="text-center py-12">
        <p class="text-[#878787] font-medium text-sm">No encontré movimientos legibles en la captura.</p>
        <p class="text-xs text-[#ababab] mt-1">Prueba con un recorte más cerrado a la lista de cargos.</p>
        <x-btn href="{{ route('accounts.show', $account) }}" variant="secondary" class="mt-4 text-sm">Volver a la cuenta</x-btn>
    </x-card>
    @else

    <p class="text-sm text-[#878787] mb-3">
        Detecté <strong class="text-[#373737] dark:text-white">{{ count($rows) }}</strong> movimientos.
        Revisa la categoría de cada uno y desmarca los que no quieras registrar.
        @if($dupes)
            <span class="text-amber-600 font-semibold">{{ $dupes }} parecen ya registrados</span> y vienen desmarcados.
        @endif
    </p>

    <form method="POST" action="{{ route('accounts.import.store', [$account, $token]) }}">
        @csrf

        <div class="space-y-2 mb-24">
            @foreach($rows as $i => $row)
            @php
                $isIncome = $row['type'] === 'income';
                $dup      = $row['duplicate'];
            @endphp
            <x-card class="p-3 {{ $dup ? 'border-amber-300 dark:border-amber-500/40' : '' }}" x-data="{ on: {{ $dup ? 'false' : 'true' }}, type: '{{ $row['type'] }}' }"
                    x-bind:class="on ? '' : 'opacity-50'">

                <div class="flex items-start gap-3">
                    <label class="flex-shrink-0 min-w-[44px] min-h-[44px] flex items-center justify-center -ml-2 -mt-2 cursor-pointer">
                        <input type="checkbox" name="rows[{{ $i }}][include]" value="1" data-include x-model="on"
                               class="w-5 h-5 rounded border-[#ababab] text-[#76a72b] focus:ring-[#76a72b]">
                    </label>

                    <div class="flex-1 min-w-0">
                        {{-- Descripción, fecha y monto editables: el lector puede equivocarse --}}
                        <div class="flex items-start gap-2">
                            <div class="flex-1 min-w-0">
                                <input type="text" name="rows[{{ $i }}][description]" value="{{ $row['description'] }}" maxlength="500" required
                                    class="w-full rounded-lg border border-transparent hover:border-[#ababab]/40 focus:border-[#ababab]/40 bg-transparent focus:bg-[#efeded]/50 dark:focus:bg-white/5 px-2 py-1 -ml-2 text-sm font-semibold text-[#373737] dark:text-white focus:outline-none focus:ring-2 focus:ring-[#76a72b] transition">
                                <div class="flex items-center gap-1.5 flex-wrap mt-0.5">
                                    <input type="date" name="rows[{{ $i }}][date]" value="{{ $row['date'] }}" required
                                        class="rounded-lg border border-transparent hover:border-[#ababab]/40 focus:border-[#ababab]/40 bg-transparent px-2 py-0.5 -ml-2 text-xs text-[#878787] dark:text-white/70 focus:outline-none focus:ring-2 focus:ring-[#76a72b] transition">
                                    @if(($row['category_source'] ?? null) === 'memory')
                                        <span class="text-[10px] font-bold text-[#76a72b] bg-[#76a72b]/10 px-1.5 py-0.5 rounded-full" title="Categoría aprendida de tus movimientos anteriores">✓ Aprendida</span>
                                    @elseif(($row['category_source'] ?? null) === 'model')
                                        <span class="text-[10px] font-bold text-blue-500 bg-blue-500/10 px-1.5 py-0.5 rounded-full" title="Sugerida por el lector de imágenes">Sugerida</span>
                                    @endif
                                </div>
                            </div>
                            <div class="relative flex-shrink-0 w-32">
                                <span class="absolute left-2 top-1/2 -translate-y-1/2 text-sm font-bold" x-bind:class="type === 'income' ? 'text-[#76a72b]' : 'text-red-500'" x-text="type === 'income' ? '+$' : '−$'"></span>
                                <input type="text" name="rows[{{ $i }}][amount]" value="{{ number_format((float) $row['amount'], 2) }}" inputmode="decimal" data-money required
                                    class="w-full rounded-lg border border-transparent hover:border-[#ababab]/40 focus:border-[#ababab]/40 bg-transparent focus:bg-[#efeded]/50 dark:focus:bg-white/5 pl-7 pr-2 py-1 text-sm font-bold tabular-nums text-right focus:outline-none focus:ring-2 focus:ring-[#76a72b] transition"
                                    x-bind:class="type === 'income' ? 'text-[#76a72b]' : 'text-red-500'">
                            </div>
                        </div>

                        @if($dup)
                        <p class="mt-1 text-[11px] text-amber-600">
                            ⚠️ Ya existe: {{ $dup['description'] }} · {{ $dup['date'] }}
                        </p>
                        @endif

                        <div class="grid grid-cols-[auto_1fr] gap-2 mt-2">
                            <select name="rows[{{ $i }}][type]" x-model="type"
                                class="rounded-lg border border-[#ababab]/40 bg-[#efeded]/50 dark:bg-white/5 px-2 py-2 text-xs text-[#373737] dark:text-white focus:outline-none focus:ring-2 focus:ring-[#76a72b]">
                                <option value="expense">Cargo</option>
                                <option value="income">Abono</option>
                            </select>

                            <x-category-picker :categories="$categories" :selected="$row['category_id']"
                                name="rows[{{ $i }}][category_id]" kind-expr="type"
                                class="!py-2 !px-3 !rounded-lg text-xs min-h-[38px]" />
                        </div>
                    </div>
                </div>
            </x-card>
            @endforeach
        </div>

        {{-- Barra fija inferior --}}
        <div class="fixed bottom-20 lg:bottom-4 left-0 lg:left-60 right-0 px-4 z-40 pointer-events-none">
            <div class="max-w-2xl mx-auto flex gap-2 pointer-events-auto">
                <x-btn href="{{ route('accounts.show', $account) }}" variant="secondary" class="flex-1 text-center">Cancelar</x-btn>
                <x-btn type="submit" class="flex-[2] text-center">
                    Registrar seleccionados
                </x-btn>
            </div>
        </div>
    </form>
    @endif
</x-app-layout>
