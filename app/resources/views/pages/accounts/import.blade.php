<x-app-layout title="Importar movimientos">
    <x-page-header title="Importar a {{ $account->name }}" :back="route('accounts.show', $account)" />

    @php
        $dupes = collect($rows)->filter(fn ($r) => $r['duplicate'])->count();
        $twins = collect($rows)->filter(fn ($r) => $r['twin'] ?? null)->count();
        $recs  = collect($rows)->filter(fn ($r) => $r['recurring'] ?? null)->count();
    @endphp

    @if($errors->any())
    <div class="mb-3 rounded-xl bg-red-50 dark:bg-red-500/10 border border-red-200 dark:border-red-500/30 px-4 py-3 text-sm text-red-600">
        @foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach
    </div>
    @endif

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
        @if($twins)
            <span class="text-blue-600 font-semibold">{{ $twins }} {{ $twins === 1 ? 'parece transferencia' : 'parecen transferencias' }} entre tus cuentas</span>:
            el otro lado ya está registrado y se convertirá en transferencia, sin duplicar.
        @endif
        @if($recs)
            <span class="text-[#76a72b] font-semibold">{{ $recs }} {{ $recs === 1 ? 'corresponde' : 'corresponden' }} a cargos recurrentes</span>
            y se aplicarán con el monto real.
        @endif
    </p>

    <form method="POST" action="{{ route('accounts.import.store', [$account, $token]) }}">
        @csrf

        <div class="space-y-2 mb-24">
            @foreach($rows as $i => $row)
            @php
                $dup  = $row['duplicate'];
                $twin = $row['twin'] ?? null;
                $rec  = $row['recurring'] ?? null;
            @endphp
            <x-card class="p-3 {{ $dup ? 'border-amber-300 dark:border-amber-500/40' : ($twin ? 'border-blue-300 dark:border-blue-500/40' : '') }}"
                    x-data="{
                        on: {{ $dup ? 'false' : 'true' }},
                        type: '{{ $row['type'] }}',
                        counterparty: '{{ $row['counterparty_account_id'] ?? '' }}',
                        linked: {{ $rec ? 'true' : 'false' }},
                        get isTransfer() { return this.type === 'transfer_out' || this.type === 'transfer_in'; },
                        get isIn() { return this.type === 'income' || this.type === 'transfer_in'; },
                        get amountClass() { return this.isIn ? 'text-[#76a72b]' : (this.type === 'transfer_out' ? 'text-[#878787]' : 'text-red-500'); },
                    }"
                    x-bind:class="on ? '' : 'opacity-50'">
                @if($twin)
                <input type="hidden" name="rows[{{ $i }}][twin_id]" value="{{ $twin['id'] }}" x-bind:disabled="!isTransfer">
                @endif
                @if($rec)
                <input type="hidden" name="rows[{{ $i }}][{{ $rec['mode'] === 'apply' ? 'recurring_id' : 'adjust_tx_id' }}]"
                       value="{{ $rec['mode'] === 'apply' ? $rec['id'] : $rec['transaction_id'] }}" x-bind:disabled="!linked || isTransfer">
                @endif

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
                                    @if($twin)
                                        <span class="text-[10px] font-bold text-blue-600 bg-blue-500/10 px-1.5 py-0.5 rounded-full">🔁 Transferencia</span>
                                    @elseif($rec)
                                        <span class="text-[10px] font-bold text-[#76a72b] bg-[#76a72b]/10 px-1.5 py-0.5 rounded-full" x-show="linked">↻ Recurrente</span>
                                    @elseif(($row['category_source'] ?? null) === 'memory')
                                        <span class="text-[10px] font-bold text-[#76a72b] bg-[#76a72b]/10 px-1.5 py-0.5 rounded-full" title="Categoría aprendida de tus movimientos anteriores">✓ Aprendida</span>
                                    @elseif(($row['category_source'] ?? null) === 'model')
                                        <span class="text-[10px] font-bold text-blue-500 bg-blue-500/10 px-1.5 py-0.5 rounded-full" title="Sugerida por el lector de imágenes">Sugerida</span>
                                    @endif
                                </div>
                            </div>
                            <div class="relative flex-shrink-0 w-32">
                                <span class="absolute left-2 top-1/2 -translate-y-1/2 text-sm font-bold" x-bind:class="amountClass" x-text="isIn ? '+$' : '−$'"></span>
                                <input type="text" name="rows[{{ $i }}][amount]" value="{{ number_format((float) $row['amount'], 2) }}" inputmode="decimal" data-money required
                                    class="w-full rounded-lg border border-transparent hover:border-[#ababab]/40 focus:border-[#ababab]/40 bg-transparent focus:bg-[#efeded]/50 dark:focus:bg-white/5 pl-7 pr-2 py-1 text-sm font-bold tabular-nums text-right focus:outline-none focus:ring-2 focus:ring-[#76a72b] transition"
                                    x-bind:class="amountClass">
                            </div>
                        </div>

                        @if($dup)
                        <p class="mt-1 text-[11px] text-amber-600">
                            ⚠️ Ya existe: {{ $dup['description'] }} · {{ $dup['account'] }} · {{ $dup['date'] }}
                        </p>
                        @endif

                        @if($twin)
                        <p class="mt-1 text-[11px] text-blue-600" x-show="isTransfer">
                            🔁 El otro lado ya está en {{ $twin['account'] }}: {{ $twin['description'] }} · {{ $twin['date'] }}.
                            Se convertirá en transferencia, no se duplica.
                        </p>
                        @endif

                        @if($rec)
                        @php
                            $diff = bcsub($row['amount'], $rec['expected'], 2);
                            $sign = bccomp($diff, '0', 2) >= 0 ? '+' : '−';
                            $abs  = ltrim($diff, '-');
                        @endphp
                        <div class="mt-1.5 rounded-lg bg-[#76a72b]/5 border border-[#76a72b]/20 px-2.5 py-1.5 text-[11px] text-[#373737] dark:text-white/80 flex items-start gap-2" x-show="!isTransfer">
                            <div class="flex-1 min-w-0" x-bind:class="linked ? '' : 'line-through opacity-50'">
                                ↻ <strong>{{ $rec['name'] }}</strong>
                                @if($rec['mode'] === 'apply')
                                    · vence {{ $rec['due'] }} · estimado {{ format_currency($rec['expected']) }}
                                    ({{ $sign }}{{ format_currency($abs) }}).
                                    Se aplicará con el monto real y pasará al siguiente mes.
                                @else
                                    · ya aplicado el {{ $rec['due'] }} con {{ format_currency($rec['expected']) }}
                                    ({{ $sign }}{{ format_currency($abs) }}).
                                    Se ajustará al monto real, no se duplica.
                                @endif
                            </div>
                            <button type="button" data-no-spinner="true" x-on:click="linked = !linked"
                                class="flex-shrink-0 font-semibold text-[#878787] hover:text-[#373737] dark:hover:text-white underline"
                                x-text="linked ? 'No es este' : 'Ligar'"></button>
                        </div>
                        @endif

                        <div class="grid grid-cols-[auto_1fr] gap-2 mt-2">
                            <select name="rows[{{ $i }}][type]" x-model="type"
                                class="rounded-lg border border-[#ababab]/40 bg-[#efeded]/50 dark:bg-white/5 px-2 py-2 text-xs text-[#373737] dark:text-white focus:outline-none focus:ring-2 focus:ring-[#76a72b]">
                                <option value="expense">Cargo</option>
                                <option value="income">Abono</option>
                                <option value="transfer_out">Transferencia enviada</option>
                                <option value="transfer_in">Transferencia recibida</option>
                            </select>

                            <div class="min-w-0" x-show="!isTransfer">
                                <x-category-picker :categories="$categories" :selected="$row['category_id']"
                                    name="rows[{{ $i }}][category_id]" kind-expr="type"
                                    disabled-expr="isTransfer"
                                    class="!py-2 !px-3 !rounded-lg text-xs min-h-[38px]" />
                            </div>

                            @if(! $rec && $recurrings->isNotEmpty())
                            <div class="col-span-2 min-w-0 flex items-center gap-2" x-show="!isTransfer">
                                <span class="text-xs text-[#878787] flex-shrink-0">↻ Recurrente</span>
                                <select name="rows[{{ $i }}][recurring_id]" x-bind:disabled="isTransfer"
                                    class="w-full min-w-0 rounded-lg border border-[#ababab]/40 bg-[#efeded]/50 dark:bg-white/5 px-2 py-2 text-xs text-[#373737] dark:text-white focus:outline-none focus:ring-2 focus:ring-[#76a72b]">
                                    <option value="">No es un cargo recurrente</option>
                                    @foreach($recurrings as $charge)
                                    <option value="{{ $charge->id }}" x-show="type === '{{ $charge->type }}'">
                                        {{ $charge->name }} · {{ format_currency($charge->amount) }} · vence {{ $charge->next_application_date->translatedFormat('j M') }}
                                    </option>
                                    @endforeach
                                </select>
                            </div>
                            @endif

                            <div class="min-w-0 flex items-center gap-2" x-show="isTransfer" x-cloak>
                                <span class="text-xs text-[#878787] flex-shrink-0" x-text="type === 'transfer_out' ? 'Hacia' : 'Desde'"></span>
                                <select name="rows[{{ $i }}][counterparty_account_id]" x-model="counterparty"
                                    x-bind:disabled="!isTransfer" x-bind:required="on && isTransfer"
                                    class="w-full min-w-0 rounded-lg border border-[#ababab]/40 bg-[#efeded]/50 dark:bg-white/5 px-2 py-2 text-xs text-[#373737] dark:text-white focus:outline-none focus:ring-2 focus:ring-[#76a72b]">
                                    <option value="">Elige la otra cuenta…</option>
                                    @foreach($others as $other)
                                    <option value="{{ $other->id }}">{{ $other->displayLabel() }}</option>
                                    @endforeach
                                </select>
                            </div>
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
