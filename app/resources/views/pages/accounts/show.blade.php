<x-app-layout :title="$account->name">

@php
$typeConfig = [
    'income'   => ['sign' => '+', 'color' => '#76a72b', 'label' => 'Ingreso'],
    'interest' => ['sign' => '+', 'color' => '#76a72b', 'label' => 'Interés'],
    'expense'  => ['sign' => '-', 'color' => '#ef4444', 'label' => 'Egreso'],
    'fee'      => ['sign' => '-', 'color' => '#ef4444', 'label' => 'Comisión'],
    'transfer' => ['sign' => '',  'color' => '#878787', 'label' => 'Transferencia'],
];

$isCredit   = $account->type === 'credit';
$balanceColor = $isCredit
    ? ((float)$balance > 0 ? '#ef4444' : '#76a72b')
    : ((float)$balance >= 0 ? '#76a72b' : '#ef4444');

$now = now();

// Prellenado del modal desde una liga externa (ej. Telegram): ?new=1&description=…&date=…&type=…
$prefill = collect(request()->only(['description', 'date', 'type']))->filter()
    ->map(fn ($v, $k) => '&' . $k . '=' . rawurlencode((string) $v))->implode('');
@endphp

{{-- ── Header de cuenta ─────────────────────────────────────────── --}}
@php
$typeLabels = [
    'debit'      => 'Cuenta bancaria',
    'credit'     => 'Tarjeta de crédito',
    'savings'    => 'Caja de ahorro',
    'investment' => 'Inversión',
    'cash'       => 'Efectivo',
];
$card        = $isCredit ? $account->creditCard : null;
$accentColor = $account->color ?? '#76a72b';
$usedPct     = ($card && (float) $card->credit_limit > 0)
    ? min(100, (int) round(((float) abs($balance) / (float) $card->credit_limit) * 100))
    : null;
@endphp
<div class="mb-5">
    <x-page-header :title="$account->name" :back="route('accounts.index')" />

    {{-- Card de saldo --}}
    <div class="relative overflow-hidden rounded-2xl p-5 text-white shadow-lg mb-3"
         style="background: linear-gradient(135deg, {{ $accentColor }} 0%, color-mix(in srgb, {{ $accentColor }} 70%, #000) 100%)">
        {{-- Decoración --}}
        <div class="pointer-events-none absolute -right-10 -top-14 w-44 h-44 rounded-full bg-white/10"></div>
        <div class="pointer-events-none absolute -right-2 top-16 w-24 h-24 rounded-full bg-white/5"></div>

        <div class="relative flex items-center gap-3">
            @if($account->logo_path)
            <img src="{{ $account->logoUrl() }}" alt="{{ $account->name }}"
                 class="w-12 h-12 rounded-xl object-contain bg-white p-1.5 shadow-sm flex-shrink-0">
            @else
            <div class="w-12 h-12 rounded-xl bg-white/20 backdrop-blur flex items-center justify-center font-bold text-xl flex-shrink-0">
                {{ mb_strtoupper(mb_substr($account->name, 0, 1)) }}
            </div>
            @endif
            <div class="min-w-0 flex-1">
                <p class="font-bold text-lg leading-tight truncate">{{ $account->name }}</p>
                <p class="text-white/70 text-xs mt-0.5 truncate">
                    {{ $typeLabels[$account->type] ?? ucfirst($account->type) }}
                    @if($account->institution && $account->institution !== 'other')
                        · {{ $account->institutionLabel() }}
                    @endif
                </p>
            </div>
            <a href="{{ route('accounts.edit', $account) }}" title="Editar cuenta"
               class="w-11 h-11 -mr-2 -mt-1 flex items-center justify-center rounded-full text-white/80 hover:text-white hover:bg-white/15 transition-colors flex-shrink-0">
                <svg class="w-4.5 h-4.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                </svg>
            </a>
        </div>

        <div class="relative mt-5">
            <p class="text-white/70 text-[11px] font-semibold uppercase tracking-wider">{{ $isCredit ? 'Saldo deudor' : 'Saldo disponible' }}</p>
            <p class="text-[34px] leading-none font-bold tabular-nums mt-1.5">
                {{-- Débito en negativo = sobregiro o captura incompleta: debe verse el signo --}}
                @if(! $isCredit && bccomp((string) $balance, '0', 2) < 0)−@endif${{ number_format(abs((float)$balance), 2) }}
                <span class="text-sm font-normal text-white/60 ml-1">MXN</span>
            </p>
        </div>

        @if($card && ($card->statement_day || $card->payment_day || $usedPct !== null))
        <div class="relative mt-4 pt-3 border-t border-white/15 flex flex-wrap gap-x-4 gap-y-1 text-[11px] text-white/80">
            @if($card->statement_day)
                <span><span class="text-white/50">Corte</span> día {{ $card->statement_day }}</span>
            @endif
            @if($card->payment_day)
                <span><span class="text-white/50">Pago</span> día {{ $card->payment_day }}</span>
            @endif
            @if($usedPct !== null)
                <span class="ml-auto tabular-nums"><span class="text-white/50">Límite</span> ${{ number_format((float) $card->credit_limit, 0) }} · {{ $usedPct }}%</span>
            @endif
        </div>
        @if($usedPct !== null)
        <div class="relative mt-2 h-1.5 rounded-full bg-white/15 overflow-hidden">
            <div class="h-full rounded-full bg-white/80" style="width: {{ $usedPct }}%"></div>
        </div>
        @endif
        @endif
    </div>

    {{-- Acciones rápidas --}}
    <div class="grid grid-cols-3 gap-2" x-data>
        <button type="button" data-no-spinner="true" x-on:click="$dispatch('new-tx')"
            class="group flex flex-col items-center justify-center gap-1.5 min-h-[76px] px-2 py-3 rounded-2xl bg-white dark:bg-[#2a2a2a] border border-[#ababab]/20 dark:border-white/10 shadow-sm hover:border-[#76a72b]/60 hover:shadow transition-[border-color,box-shadow,transform] duration-150 ease-snappy active:scale-[0.97]">
            <span class="w-9 h-9 rounded-full bg-[#76a72b]/10 text-[#76a72b] flex items-center justify-center group-hover:bg-[#76a72b] group-hover:text-white transition-colors">
                <svg class="w-4.5 h-4.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/></svg>
            </span>
            <span class="text-[11px] sm:text-xs font-semibold text-[#373737] dark:text-white text-center leading-tight">Nuevo movimiento</span>
        </button>

        <a href="{{ route('accounts.adjust.show', $account) }}"
           class="group flex flex-col items-center justify-center gap-1.5 min-h-[76px] px-2 py-3 rounded-2xl bg-white dark:bg-[#2a2a2a] border border-[#ababab]/20 dark:border-white/10 shadow-sm hover:border-amber-400/60 hover:shadow transition-[border-color,box-shadow,transform] duration-150 ease-snappy active:scale-[0.97]">
            <span class="w-9 h-9 rounded-full bg-amber-500/10 text-amber-500 flex items-center justify-center group-hover:bg-amber-500 group-hover:text-white transition-colors">
                <svg class="w-4.5 h-4.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4"/></svg>
            </span>
            <span class="text-[11px] sm:text-xs font-semibold text-[#373737] dark:text-white text-center leading-tight">Ajustar saldo</span>
        </a>

        {{-- Importar capturas: abre el modal para juntar varias imágenes --}}
        <button type="button" data-no-spinner="true" x-on:click="$dispatch('open-import')"
            class="group flex flex-col items-center justify-center gap-1.5 min-h-[76px] px-2 py-3 rounded-2xl bg-white dark:bg-[#2a2a2a] border border-[#ababab]/20 dark:border-white/10 shadow-sm hover:border-blue-400/60 hover:shadow transition-[border-color,box-shadow,transform] duration-150 ease-snappy active:scale-[0.97]">
            <span class="w-9 h-9 rounded-full bg-blue-500/10 text-blue-500 flex items-center justify-center group-hover:bg-blue-500 group-hover:text-white transition-colors">
                <svg class="w-4.5 h-4.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
            </span>
            <span class="text-[11px] sm:text-xs font-semibold text-[#373737] dark:text-white text-center leading-tight">Importar captura</span>
        </button>
    </div>
</div>

{{-- ── Modal: importar capturas del estado de cuenta ───────────── --}}
<div x-data="importCaptures('{{ route('accounts.import.upload', $account) }}')"
     x-on:open-import.window="open = true"
     x-on:keydown.escape.window="if (open && !busy) close()"
     x-on:paste.window="if (open && !busy) onPaste($event)"
     x-show="open" x-cloak x-transition:leave="transition duration-150"
     class="fixed inset-0 z-[60] flex items-end sm:items-center justify-center" role="dialog" aria-modal="true">
    <div class="absolute inset-0 bg-black/50" x-on:click="if (!busy) close()" x-show="open" x-transition:enter="transition-opacity ease-snappy duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" x-transition:leave="transition-opacity ease-snappy duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"></div>

    <div class="relative w-full sm:max-w-lg bg-white dark:bg-[#2a2a2a] rounded-t-2xl sm:rounded-2xl shadow-2xl max-h-[92vh] flex flex-col"
         x-show="open" x-transition:enter="transition ease-snappy duration-200" x-transition:enter-start="translate-y-full sm:translate-y-0 sm:scale-[0.96] sm:opacity-0" x-transition:enter-end="translate-y-0 sm:scale-100 sm:opacity-100" x-transition:leave="transition ease-snappy duration-150" x-transition:leave-start="translate-y-0 sm:scale-100 sm:opacity-100" x-transition:leave-end="translate-y-full sm:translate-y-0 sm:scale-[0.98] sm:opacity-0">

        <div class="flex items-center justify-between px-5 py-4 border-b border-[#ababab]/15">
            <div>
                <h2 class="text-base font-bold text-[#373737] dark:text-white">Importar capturas</h2>
                <p class="text-xs text-[#878787]">{{ $account->displayLabel() }}</p>
            </div>
            <button type="button" data-no-spinner="true" x-on:click="close()" x-bind:disabled="busy"
                class="w-11 h-11 -mr-2 flex items-center justify-center rounded-full text-[#ababab] hover:text-[#373737] hover:bg-[#efeded] dark:hover:bg-white/10 transition-colors disabled:opacity-40">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>

        <div class="p-5 overflow-y-auto space-y-4">
            <p class="text-sm text-[#878787]">
                Agrega todas las capturas del periodo, en una o varias tandas. Si se traslapan no pasa nada: los renglones repetidos se quitan solos.
            </p>

            {{-- Zona para agregar: toque, arrastrar o pegar --}}
            <label x-show="items.length < max && !busy"
                   x-on:dragover.prevent="dragging = true" x-on:dragleave.prevent="dragging = false"
                   x-on:drop.prevent="dragging = false; add($event.dataTransfer.files)"
                   x-bind:class="dragging ? 'border-blue-500 bg-blue-500/5' : 'border-[#ababab]/40'"
                   class="flex flex-col items-center justify-center gap-2 min-h-[120px] rounded-2xl border-2 border-dashed cursor-pointer hover:border-blue-400 transition-colors text-center px-4">
                <input type="file" accept="image/*" multiple class="sr-only" x-on:change="add($event.target.files); $event.target.value = ''">
                <span class="w-10 h-10 rounded-full bg-blue-500/10 text-blue-500 flex items-center justify-center">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/></svg>
                </span>
                <span class="text-sm font-semibold text-[#373737] dark:text-white" x-text="items.length ? 'Agregar más capturas' : 'Elegir capturas'"></span>
                <span class="hidden sm:block text-xs text-[#ababab]">También puedes arrastrarlas o pegarlas con ⌘V</span>
            </label>

            {{-- Miniaturas en el orden en que se agregaron --}}
            <div class="grid grid-cols-3 gap-2" x-show="items.length">
                <template x-for="(item, i) in items" x-bind:key="item.url">
                    <div class="relative aspect-[9/16] max-w-full rounded-xl overflow-hidden bg-[#efeded] dark:bg-white/5 border border-[#ababab]/20">
                        <img x-bind:src="item.url" alt="" class="w-full h-full object-cover object-top">
                        <span class="absolute bottom-1 left-1 text-[10px] font-bold text-white bg-black/60 rounded-full px-1.5 py-0.5" x-text="i + 1"></span>
                        <button type="button" data-no-spinner="true" x-on:click="remove(i)" x-show="!busy" title="Quitar"
                            class="absolute top-1 right-1 w-8 h-8 flex items-center justify-center rounded-full bg-black/60 text-white hover:bg-red-500 transition-colors">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                    </div>
                </template>
            </div>

            <p x-show="error" x-text="error" class="text-xs text-red-500"></p>
        </div>

        <div class="px-5 py-4 border-t border-[#ababab]/15 flex items-center gap-3">
            <span class="text-xs text-[#878787] tabular-nums flex-shrink-0" x-text="`${items.length} de ${max}`"></span>
            <button type="button" data-no-spinner="true" x-on:click="submit()" x-bind:disabled="!items.length || busy"
                class="flex-1 min-h-[44px] rounded-xl bg-[#76a72b] hover:bg-[#659220] text-white text-sm font-semibold transition-colors disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center gap-2">
                <svg x-show="busy" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                <span x-text="busy ? 'Analizando… puede tardar un minuto' : (items.length === 1 ? 'Analizar 1 captura' : `Analizar ${items.length} capturas`)"></span>
            </button>
        </div>
    </div>
</div>

@push('scripts')
<script>
/**
 * Modal de importación: junta varias capturas (toque, arrastrar o pegar),
 * las reduce en el navegador (JPEG, máx. 1200 px de ancho) y las envía juntas.
 */
function importCaptures(url) {
    return {
        open: false, busy: false, dragging: false, error: '', max: 6, items: [],

        add(files) {
            this.error = '';
            for (const file of Array.from(files || [])) {
                if (!file.type.startsWith('image/')) continue;
                if (this.items.length >= this.max) { this.error = `Máximo ${this.max} capturas por análisis.`; break; }
                this.items.push({ file, url: URL.createObjectURL(file) });
            }
        },
        remove(i) {
            URL.revokeObjectURL(this.items[i].url);
            this.items.splice(i, 1);
            this.error = '';
        },
        onPaste(e) {
            const files = Array.from(e.clipboardData?.files || []);
            if (files.length) { e.preventDefault(); this.add(files); }
        },
        close() {
            this.items.forEach(it => URL.revokeObjectURL(it.url));
            this.items = []; this.error = ''; this.open = false;
        },
        compress(file) {
            return new Promise(resolve => {
                const img = new Image();
                img.onload = () => {
                    const scale  = Math.min(1, 1200 / img.width);
                    const canvas = document.createElement('canvas');
                    canvas.width  = Math.round(img.width * scale);
                    canvas.height = Math.round(img.height * scale);
                    const ctx = canvas.getContext('2d');
                    ctx.fillStyle = '#fff';
                    ctx.fillRect(0, 0, canvas.width, canvas.height);
                    ctx.drawImage(img, 0, 0, canvas.width, canvas.height);
                    canvas.toBlob(blob => resolve(blob || file), 'image/jpeg', 0.85);
                };
                img.onerror = () => resolve(file);
                img.src = URL.createObjectURL(file);
            });
        },
        async submit() {
            if (!this.items.length || this.busy) return;
            this.busy = true; this.error = '';
            try {
                const data = new FormData();
                data.append('_token', document.querySelector('meta[name="csrf-token"]').content);
                for (const [i, item] of this.items.entries()) {
                    data.append('images[]', await this.compress(item.file), `captura-${i + 1}.jpg`);
                }
                const res = await fetch(url, { method: 'POST', body: data, credentials: 'same-origin', headers: { 'Accept': 'text/html' } });
                if (!res.ok) throw new Error(res.status);
                window.location.href = res.url;
            } catch (e) {
                this.busy = false;
                this.error = 'No se pudieron enviar las capturas. Revisa tu conexión e inténtalo de nuevo.';
            }
        },
    };
}
</script>
@endpush

{{-- ── Modal de edición de movimiento ───────────────────────────── --}}
<div x-data="{
        open: false,
        loading: false,
        html: '',
        title: 'Editar movimiento',
        back: '{{ urlencode(route('accounts.show', $account, false)) }}',
        async load(url, title) {
            this.title = title;
            this.open = true;
            this.loading = true;
            this.html = '';
            const res = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            this.html = res.ok
                ? await res.text()
                : '<p class=\'text-sm text-red-500\'>No se pudo cargar el formulario.</p>';
            this.loading = false;
        },
        edit(id) {
            return this.load('/transactions/' + id + '/edit-modal?redirect_to=' + this.back, 'Editar movimiento');
        },
        create() {
            return this.load('/transactions/create-modal?account_id={{ $account->id }}&redirect_to=' + this.back + '{{ $prefill }}', 'Nuevo movimiento');
        },
        // El formulario se vacía al terminar la salida, no a media animación
        close() { this.open = false; setTimeout(() => { if (!this.open) this.html = ''; }, 160); }
     }"
     x-on:edit-tx.window="edit($event.detail)"
     x-on:new-tx.window="create()"
     x-on:close-tx-modal="close()"
     x-on:keydown.escape.window="close()"
     x-init="@if(request()->filled('edit')) edit({{ (int) request('edit') }}) @elseif(request()->boolean('new')) create() @endif">

    <div x-show="open" x-cloak x-transition:leave="transition duration-150" class="fixed inset-0 z-[60] flex items-end sm:items-center justify-center">
        <div class="absolute inset-0 bg-black/50" x-on:click="close()" x-show="open" x-transition:enter="transition-opacity ease-snappy duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" x-transition:leave="transition-opacity ease-snappy duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"></div>

        <div class="relative w-full sm:max-w-md bg-white dark:bg-[#2a2a2a] rounded-t-2xl sm:rounded-2xl shadow-2xl max-h-[92vh] sm:max-h-[85vh] flex flex-col"
             x-show="open" x-transition:enter="transition ease-snappy duration-200" x-transition:enter-start="translate-y-full sm:translate-y-0 sm:scale-[0.96] sm:opacity-0" x-transition:enter-end="translate-y-0 sm:scale-100 sm:opacity-100" x-transition:leave="transition ease-snappy duration-150" x-transition:leave-start="translate-y-0 sm:scale-100 sm:opacity-100" x-transition:leave-end="translate-y-full sm:translate-y-0 sm:scale-[0.98] sm:opacity-0">

            <div class="flex items-center justify-between px-5 py-4 border-b border-[#ababab]/15">
                <h2 class="text-base font-bold text-[#373737] dark:text-white" x-text="title"></h2>
                <button type="button" data-no-spinner="true" x-on:click="close()"
                    class="w-9 h-9 flex items-center justify-center rounded-full text-[#ababab] hover:text-[#373737] hover:bg-[#efeded] dark:hover:bg-white/10 transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>

            <div class="p-5 overflow-y-auto">
                {{-- Esqueleto con la forma del formulario: el modal no brinca de alto al cargar --}}
                <div x-show="loading" class="space-y-4 animate-pulse" aria-hidden="true">
                    <div class="grid grid-cols-4 gap-2">
                        <template x-for="i in 4"><div class="h-9 rounded-xl bg-[#efeded] dark:bg-white/10"></div></template>
                    </div>
                    <template x-for="h in ['h-[58px]', 'h-12', 'h-12', 'h-12']">
                        <div><div class="h-3.5 w-20 rounded bg-[#efeded] dark:bg-white/10 mb-2"></div><div class="rounded-xl bg-[#efeded] dark:bg-white/10" x-bind:class="h"></div></div>
                    </template>
                    <div class="flex gap-3 pt-1"><div class="flex-1 h-11 rounded-xl bg-[#efeded] dark:bg-white/10"></div><div class="flex-1 h-11 rounded-xl bg-[#efeded] dark:bg-white/10"></div></div>
                </div>
                <div x-html="html"></div>
            </div>
        </div>
    </div>
</div>

{{-- ── Movimientos agrupados ────────────────────────────────────── --}}
@if($grouped->isEmpty())
<x-card class="text-center py-12" x-data>
    <p class="text-[#878787] font-medium text-sm">Sin movimientos en esta cuenta</p>
    <x-btn type="button" data-no-spinner="true" x-on:click="$dispatch('new-tx')" class="mt-3 text-sm">
        Registrar primero
    </x-btn>
</x-card>
@else

@foreach($grouped as $monthKey => $txs)
@php
    $monthCarbon = \Illuminate\Support\Carbon::createFromFormat('Y-m', $monthKey);
    $isCurrentMonth = $monthKey === $now->format('Y-m');
    $isLastMonth    = $monthKey === $now->copy()->subMonth()->format('Y-m');
    $monthLabel = $isCurrentMonth
        ? 'Este mes'
        : ($isLastMonth ? 'Mes anterior' : $monthCarbon->translatedFormat('F Y'));

    $inSum  = $txs->whereIn('type', ['income','interest'])->sum(fn($t) => (float)$t->amount);
    $outSum = $txs->whereIn('type', ['expense','fee'])->sum(fn($t) => (float)$t->amount);
@endphp

<div class="flex items-center justify-between mb-2 mt-5 first:mt-0">
    <h2 class="text-xs font-bold text-[#878787] uppercase tracking-wider">{{ $monthLabel }}</h2>
    <div class="flex items-center gap-3 text-xs font-semibold">
        <span class="text-[#ababab] font-medium tabular-nums">
            {{ $txs->count() }} {{ $txs->count() === 1 ? 'movimiento' : 'movimientos' }}
        </span>
        @if($inSum > 0)<span class="text-[#76a72b] tabular-nums">+${{ number_format($inSum, 2) }}</span>@endif
        @if($outSum > 0)<span class="text-red-500 tabular-nums">-${{ number_format($outSum, 2) }}</span>@endif
    </div>
</div>

{{-- Los movimientos se agrupan por día: arrastrar solo reordena dentro
     del mismo día, que es como se concilia contra el estado de cuenta --}}
<div class="bg-white dark:bg-[#2a2a2a] rounded-2xl overflow-hidden border border-[#ababab]/15 shadow-sm mb-1 [&>*:last-child>*:last-child]:border-b-0">
    @foreach($txs->groupBy(fn ($t) => $t->date->toDateString()) as $dayKey => $dayTxs)
    @php
        // Zebra por bloque de fecha: ayuda a leer dónde empieza y termina un día
        $isOddDay  = $loop->index % 2 === 1;
        $rowBg     = $isOddDay ? 'bg-[#f7f6f6] dark:bg-white/[0.03]' : 'bg-white dark:bg-[#2a2a2a]';
        $rowHover  = $isOddDay ? 'hover:bg-[#f0efef] dark:hover:bg-white/[0.06]' : 'hover:bg-[#f9f9f9] dark:hover:bg-white/5';
        $canDrag   = $dayTxs->count() > 1;
    @endphp
    <div data-day-group data-date="{{ $dayKey }}">
    @foreach($dayTxs as $tx)
    @php
        $isIncoming = $tx->type === 'transfer' && $tx->counterparty_account_id === $account->id;
        $cfg = $isIncoming
            ? ['sign' => '+', 'color' => '#878787', 'label' => 'Transferencia recibida']
            : ($typeConfig[$tx->type] ?? $typeConfig['expense']);
        // El ícono se queda gris (es transferencia), pero el monto que entra va en verde
        $amountColor = $isIncoming ? '#76a72b' : $cfg['color'];
        if ($tx->category) {
            $iconBg    = $tx->category->color;
            $iconLabel = mb_strtoupper(mb_substr($tx->category->name, 0, 1));
        } else {
            $iconBg    = $cfg['color'];
            $iconLabel = $tx->type === 'transfer' ? '⇄' : $cfg['sign'];
        }
    @endphp
    <div data-tx-row data-id="{{ $tx->id }}"
         class="flex items-center gap-3 px-4 py-3 border-b border-[#ababab]/10 transition-colors group {{ $rowBg }} {{ $rowHover }}">

        {{-- La agarradera va en todas las filas para que queden alineadas;
             si el día tiene un solo movimiento se ve apagada e inerte --}}
        @if($canDrag)
        <button type="button" data-drag-handle data-no-spinner="true" title="Arrastrar para acomodar dentro del día"
            class="w-6 -ml-1 flex-shrink-0 flex items-center justify-center text-[#ababab] hover:text-[#878787] cursor-grab active:cursor-grabbing touch-none">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8h16M4 16h16"/>
            </svg>
        </button>
        @else
        <span aria-hidden="true" title="Único movimiento de ese día"
            class="w-6 -ml-1 flex-shrink-0 flex items-center justify-center text-[#ababab]/25">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8h16M4 16h16"/>
            </svg>
        </span>
        @endif


        <div class="w-10 h-10 rounded-[10px] flex items-center justify-center flex-shrink-0 text-white font-bold text-sm select-none"
             style="background-color: {{ $iconBg }}">
            {{ $iconLabel }}
        </div>

        <div class="flex-1 min-w-0">
            <p class="text-sm font-semibold text-[#373737] dark:text-white truncate">
                {{ $tx->description ?: $cfg['label'] }}
            </p>
            <div class="flex items-center gap-1.5 mt-0.5 flex-wrap">
                <span class="text-[11px] text-[#ababab]">{{ $tx->date->translatedFormat('d M') }}</span>
                @if($tx->category)
                <span class="text-[#ababab]/60 text-[10px]">·</span>
                <span class="text-[11px] text-[#ababab]">{{ $tx->category->name }}</span>
                @endif
                @if($isIncoming)
                <span class="text-[#ababab]/60 text-[10px]">·</span>
                <span class="text-[11px] text-[#ababab]">← {{ $tx->account->name }}</span>
                @elseif($tx->counterpartyAccount)
                <span class="text-[#ababab]/60 text-[10px]">·</span>
                <span class="text-[11px] text-[#ababab]">→ {{ $tx->counterpartyAccount->name }}</span>
                @endif
            </div>
        </div>

        {{-- Monto + saldo corrido --}}
        <div class="text-right flex-shrink-0 min-w-[80px]">
            <p class="text-sm font-bold tabular-nums" style="color: {{ $amountColor }}">
                {{ $cfg['sign'] }}${{ number_format((float)$tx->amount, 2) }}
            </p>
            @if(isset($runningBalances[$tx->id]))
            @php $rb = (float) $runningBalances[$tx->id]; @endphp
            <p class="text-[10px] tabular-nums mt-0.5 {{ $rb < 0 ? 'text-red-400' : 'text-[#ababab]' }}">
                ${{ number_format($rb, 2) }}
            </p>
            @endif
        </div>

        {{-- Con mouse aparecen al pasar por la fila; en táctil (iPad incluido)
             siguen siempre visibles, ver .row-actions en app.css --}}
        <div class="row-actions flex items-center gap-0.5 flex-shrink-0" x-data>
            <button type="button" data-no-spinner="true"
               x-on:click="$dispatch('edit-tx', {{ $tx->id }})"
               class="w-7 h-7 flex items-center justify-center text-[#ababab] hover:text-[#76a72b] hover:bg-[#76a72b]/10 rounded-lg transition-colors" title="Editar">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                </svg>
            </button>
            <form method="POST" action="{{ route('transactions.duplicate', $tx) }}">
                @csrf
                <input type="hidden" name="redirect_to" value="{{ route('accounts.show', $account, false) }}">
                <button type="submit"
                    class="w-7 h-7 flex items-center justify-center text-[#ababab] hover:text-blue-500 hover:bg-blue-50 dark:hover:bg-blue-500/10 rounded-lg transition-colors" title="Duplicar">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                    </svg>
                </button>
            </form>
            <form method="POST" action="{{ route('transactions.destroy', $tx) }}"
                  data-confirm="¿Eliminar este movimiento?" data-confirm-label="Eliminar">
                @csrf @method('DELETE')
                <input type="hidden" name="redirect_to" value="{{ route('accounts.show', $account, false) }}">
                <button type="submit"
                    class="w-7 h-7 flex items-center justify-center text-[#ababab] hover:text-red-500 hover:bg-red-50 dark:hover:bg-red-500/10 rounded-lg transition-colors" title="Eliminar">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                    </svg>
                </button>
            </form>
        </div>
    </div>
    @endforeach
    </div>
    @endforeach
</div>
@endforeach

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    try {
        const y = sessionStorage.getItem('fp-scroll');
        if (y !== null) { sessionStorage.removeItem('fp-scroll'); window.scrollTo(0, Number(y)); }
    } catch (e) {}

    if (!window.Sortable) return;

    const token = document.querySelector('meta[name="csrf-token"]')?.content;

    document.querySelectorAll('[data-day-group]').forEach(function (group) {
        if (group.querySelectorAll('[data-tx-row]').length < 2) return;

        window.Sortable.create(group, {
            handle: '[data-drag-handle]',
            draggable: '[data-tx-row]',
            animation: 150,
            ghostClass: 'opacity-40',
            // El grupo acota el arrastre al mismo día: no hay destino fuera de él
            onEnd: function () {
                const ids = Array.from(group.querySelectorAll('[data-tx-row]'))
                    .map(function (row) { return Number(row.dataset.id); });

                fetch('{{ route('transactions.reorder') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': token,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify({ ids: ids }),
                }).then(function (res) {
                    if (!res.ok) {
                        console.error('[fp] no se pudo guardar el orden', res.status);
                        return;
                    }
                    // El saldo corrido depende del orden: recargar para recalcularlo,
                    // volviendo a la misma altura de la página
                    try { sessionStorage.setItem('fp-scroll', String(window.scrollY)); } catch (e) {}
                    window.location.reload();
                });
            },
        });
    });
});
</script>
@endpush

@endif
</x-app-layout>
