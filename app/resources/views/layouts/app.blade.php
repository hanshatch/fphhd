<!DOCTYPE html>
<html lang="es" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'FP' }} · Finanzas</title>
    {{-- Roboto con <link>: un @import dentro del CSS compilado queda después de
         las reglas de Tailwind y el navegador lo descarta (nunca cargaba) --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    {{-- Oculta elementos con x-cloak hasta que Alpine inicializa (evita el
         flash de modales/listas expandidas al cargar la página) --}}
    <style>[x-cloak] { display: none !important; }</style>
</head>
<body class="h-full bg-[#efeded] dark:bg-[#1a1a1a] antialiased">

<a href="#contenido" class="skip-link">Saltar al contenido</a>

<div class="flex h-full">

    {{-- ── Sidebar desktop ──────────────────────────────────────── --}}
    <aside class="hidden lg:flex lg:flex-col lg:w-60 lg:fixed lg:inset-y-0 bg-[#373737] dark:bg-[#222222]">

        {{-- Logo --}}
        <div class="flex items-center h-16 px-5 border-b border-white/10">
            <span class="text-white text-xl font-light tracking-tight">hans</span>
            <span class="text-[#76a72b] text-xl font-bold tracking-tight">hatch</span>
            <span class="ml-2 text-white/30 text-xs uppercase tracking-widest">fp</span>
        </div>

        {{-- Acción principal: en escritorio no hay FAB, así que vive arriba
             de la navegación. Atajo de teclado: N --}}
        <div class="px-3 pt-4">
            <a href="{{ route('transactions.create') }}" title="Nuevo movimiento (N)"
               class="flex items-center gap-2 px-3 py-2.5 rounded-lg bg-[#76a72b] hover:bg-[#659220] text-white text-sm font-semibold shadow-sm transition-[background-color,transform] duration-150 ease-snappy active:scale-[0.97]">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/></svg>
                <span class="flex-1">Nuevo movimiento</span>
                <kbd class="text-[11px] font-semibold font-sans px-1.5 py-0.5 rounded bg-white/20 text-white/90">N</kbd>
            </a>
        </div>

        {{-- Nav --}}
        <nav class="flex-1 px-3 py-4 space-y-0.5 overflow-y-auto" aria-label="Principal">
            @php
            $navGroups = [
                '' => [
                    ['route' => 'dashboard',          'match' => 'dashboard',       'icon' => 'home',        'label' => 'Panel'],
                    ['route' => 'transactions.index', 'match' => 'transactions.*',  'icon' => 'arrows',      'label' => 'Movimientos'],
                    ['route' => 'accounts.index',     'match' => 'accounts.*',      'icon' => 'card',        'label' => 'Cuentas'],
                    ['route' => 'reports.index',      'match' => 'reports.*',       'icon' => 'chart',       'label' => 'Reportes'],
                ],
                'Planeación' => [
                    ['route' => 'scheduled.index',    'match' => 'scheduled.*',     'icon' => 'calendar',    'label' => 'Flujo'],
                    ['route' => 'recurring.index',    'match' => 'recurring.*',     'icon' => 'repeat',      'label' => 'Recurrentes'],
                    ['route' => 'income-plans.index', 'match' => 'income-plans.*',  'icon' => 'trending-up', 'label' => 'Ingresos'],
                    ['route' => 'goals.index',        'match' => 'goals.*',         'icon' => 'flag',        'label' => 'Metas'],
                ],
            ];
            @endphp

            @foreach($navGroups as $groupLabel => $items)
                @if($groupLabel !== '')
                <p class="px-3 pt-5 pb-1.5 text-[11px] font-bold text-white/30 uppercase tracking-widest">{{ $groupLabel }}</p>
                @endif
                @foreach($items as $item)
                @php $active = request()->routeIs($item['match']); @endphp
                <a href="{{ route($item['route']) }}" @if($active) aria-current="page" @endif
                   class="{{ $active ? 'bg-white/15 text-white shadow-[inset_3px_0_0_#76a72b]' : 'text-white/70 hover:text-white hover:bg-white/10' }} group flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors duration-150">
                    @include('layouts._icon', ['name' => $item['icon'], 'class' => 'w-5 h-5 flex-shrink-0'])
                    {{ $item['label'] }}
                </a>
                @endforeach
            @endforeach
        </nav>

        {{-- Footer sidebar --}}
        <div class="p-3 border-t border-white/10 space-y-0.5">
            @php $settingsActive = request()->routeIs('settings') || request()->routeIs('categories.*') || request()->routeIs('sources.*') || request()->routeIs('profile.*'); @endphp
            <a href="{{ route('settings') }}" @if($settingsActive) aria-current="page" @endif
               class="{{ $settingsActive ? 'bg-white/15 text-white shadow-[inset_3px_0_0_#76a72b]' : 'text-white/70 hover:text-white hover:bg-white/10' }} flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors duration-150">
                @include('layouts._icon', ['name' => 'settings', 'class' => 'w-5 h-5 flex-shrink-0'])
                Configuración
            </a>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit"
                    class="w-full flex items-center gap-3 px-3 py-2.5 text-white/70 hover:text-white hover:bg-white/10 rounded-lg text-sm transition-colors duration-150">
                    @include('layouts._icon', ['name' => 'logout', 'class' => 'w-4 h-4'])
                    Cerrar sesión
                </button>
            </form>
        </div>
    </aside>

    {{-- ── Área principal ───────────────────────────────────────── --}}
    <div class="flex-1 lg:pl-60 flex flex-col min-h-full">

        {{-- Header móvil --}}
        <header class="lg:hidden sticky top-0 z-20 bg-[#373737] h-14 flex items-center justify-between px-4">
            <span class="text-white text-lg font-light">hans<span class="text-[#76a72b] font-bold">hatch</span> <span class="text-white/30 text-xs uppercase tracking-widest">fp</span></span>
            <a href="{{ route('transactions.create') }}"
               class="flex items-center gap-1.5 bg-[#76a72b] hover:bg-[#659220] text-white text-xs font-semibold px-3 py-1.5 rounded-lg transition-colors">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/></svg>
                Nuevo
            </a>
        </header>

        {{-- Contenido: ancho máximo para que en monitores grandes no se estire --}}
        <main id="contenido" tabindex="-1" class="flex-1 p-4 lg:p-8 pb-24 lg:pb-8 focus:outline-none">
          <div class="max-w-6xl mx-auto w-full">

            {{-- Flash: alineado con el contenido; se va solo o con la ✕ --}}
            @if(session('status'))
            <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 4000)"
                 x-transition:leave="transition-opacity ease-snappy duration-150" x-transition:leave-end="opacity-0"
                 role="status" aria-live="polite"
                 class="mb-4 flex items-center gap-3 p-3 bg-[#76a72b]/10 border border-[#76a72b]/30 rounded-xl text-sm text-[#4a7018] dark:text-[#76a72b]">
                <svg class="w-4 h-4 text-[#76a72b] flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                <span class="flex-1">{{ session('status') }}</span>
                <button type="button" data-no-spinner="true" x-on:click="show = false" title="Cerrar"
                    class="w-7 h-7 -my-1 -mr-1 flex items-center justify-center rounded-lg text-[#76a72b]/70 hover:text-[#76a72b] hover:bg-[#76a72b]/10 transition-colors flex-shrink-0">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
            @endif

            @if($errors->any())
            <div class="mb-4 p-3 bg-red-50 dark:bg-red-500/10 border border-red-200 dark:border-red-500/30 rounded-xl text-sm text-red-700 dark:text-red-400">
                <ul class="list-disc list-inside space-y-0.5">
                    @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                </ul>
            </div>
            @endif

            {{ $slot }}
          </div>
        </main>
    </div>
</div>

{{-- FAB móvil --}}
<a href="{{ route('transactions.create') }}" aria-label="Nuevo movimiento"
   class="lg:hidden fixed bottom-20 right-4 z-30 w-14 h-14 bg-[#76a72b] hover:bg-[#659220] text-white rounded-full shadow-xl flex items-center justify-center transition-[background-color,transform] duration-150 ease-snappy active:scale-[0.94]">
    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/></svg>
</a>

{{-- Bottom nav móvil --}}
<nav class="lg:hidden fixed bottom-0 inset-x-0 z-20 bg-[#373737] border-t border-white/10 pb-[env(safe-area-inset-bottom)]" aria-label="Principal">
    <div class="grid grid-cols-5 h-16">
        @foreach([
            ['route' => 'dashboard',          'match' => 'dashboard',      'icon' => 'home',      'label' => 'Panel'],
            ['route' => 'transactions.index', 'match' => 'transactions.*', 'icon' => 'arrows',    'label' => 'Movimientos'],
            ['route' => 'transactions.create','match' => 'x',             'icon' => 'plus',       'label' => ''],
            ['route' => 'accounts.index',     'match' => 'accounts.*',    'icon' => 'card',       'label' => 'Cuentas'],
            ['route' => 'more',               'match' => 'more',          'icon' => 'dots',       'label' => 'Más'],
        ] as $item)
        @php $active = request()->routeIs($item['match']); @endphp
        @if($item['icon'] === 'plus')
        <div class="flex items-center justify-center">
            {{-- espacio para el FAB --}}
        </div>
        @else
        <a href="{{ route($item['route']) }}"
           @if($active) aria-current="page" @endif
           class="{{ $active ? 'text-[#8cc63f]' : 'text-white/60 hover:text-white/80' }} flex flex-col items-center justify-center gap-1 text-[11px] font-medium transition-colors">
            @include('layouts._icon', ['name' => $item['icon'], 'class' => 'w-5 h-5'])
            {{ $item['label'] }}
        </a>
        @endif
        @endforeach
    </div>
</nav>

<x-confirm-modal />

<script>
/**
 * DESIGN SYSTEM — Confirmación destructiva con modal (no window.confirm)
 * Cualquier <form data-confirm="mensaje"> pasa por el modal antes de enviarse.
 * Opcionales: data-confirm-label (texto del botón), data-confirm-title.
 * Se registra en fase de captura para adelantarse al spinner global.
 */
document.addEventListener('submit', function (e) {
    const form = e.target;
    if (!(form instanceof HTMLFormElement) || !form.dataset.confirm) return;
    if (form.dataset.confirmed === '1') { delete form.dataset.confirmed; return; }

    e.preventDefault();
    e.stopImmediatePropagation();

    const submitter = e.submitter;

    window.dispatchEvent(new CustomEvent('confirm-modal', { detail: {
        title:    form.dataset.confirmTitle,
        message:  form.dataset.confirm,
        label:    form.dataset.confirmLabel,
        onAccept: () => {
            form.dataset.confirmed = '1';
            form.requestSubmit(submitter && submitter.form === form ? submitter : undefined);
        },
    }}));
}, true);

/**
 * DESIGN SYSTEM — Spinner global en botones de acción
 * Se activa automáticamente en cualquier submit de form.
 * Para excluir un botón: <button data-no-spinner="true">
 */
(function () {
    const SPINNER_SVG = `<svg class="inline-block animate-spin w-4 h-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" aria-hidden="true">
        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
    </svg>`;

    // Botones de solo ícono (sin texto visible): el texto «Procesando…» no
    // cabe y empuja la fila; ahí basta el spinner en el mismo lugar del ícono.
    function spinnerHtml(btn) {
        return btn.textContent.trim() === ''
            ? SPINNER_SVG
            : `${SPINNER_SVG}<span class="ml-1.5">Procesando…</span>`;
    }

    document.addEventListener('submit', function (e) {
        // e.submitter = botón exacto que activó el submit
        const btn = e.submitter || e.target.querySelector('[type="submit"]');
        if (!btn || btn.dataset.noSpinner === 'true') return;

        // Un botón deshabilitado deja de enviar su name/value (ej. save_and_new):
        // conservarlo en un input oculto antes de deshabilitarlo.
        if (btn.name && !btn.disabled && btn.form) {
            const hidden = document.createElement('input');
            hidden.type  = 'hidden';
            hidden.name  = btn.name;
            hidden.value = btn.value;
            hidden.dataset.spinnerSubmitter = '1';
            btn.form.appendChild(hidden);
        }

        btn.disabled = true;
        btn.dataset.originalHtml = btn.innerHTML;
        btn.innerHTML = spinnerHtml(btn);

        // Safeguard: re-habilitar tras 15s si la página no navega
        setTimeout(() => {
            if (btn.dataset.originalHtml) {
                btn.disabled = false;
                btn.innerHTML = btn.dataset.originalHtml;
                delete btn.dataset.originalHtml;
                btn.form?.querySelectorAll('[data-spinner-submitter]').forEach(el => el.remove());
            }
        }, 15000);
    });

    // Botones de confirmación tipo delete (no son submit de form)
    document.addEventListener('click', function (e) {
        const btn = e.target.closest('[data-action-btn]');
        if (!btn || btn.dataset.noSpinner === 'true') return;
        if (btn.disabled) return;

        btn.disabled = true;
        btn.dataset.originalHtml = btn.innerHTML;
        btn.innerHTML = spinnerHtml(btn);
    });
    // Inputs de dinero [data-money]: al salir del campo, formatear con
    // comas de miles y 2 decimales. El backend re-parsea con parse_money.
    document.addEventListener('focusout', function (e) {
        const el = e.target;
        if (!el.matches || !el.matches('input[data-money]')) return;

        const raw = el.value.replace(/[$,\s]/g, '');
        if (raw === '' || isNaN(raw)) return;

        el.value = Number(raw).toLocaleString('en-US', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2,
        });
    });
}());
</script>

<script>
/**
 * Atajo N (escritorio): nuevo movimiento. En la vista de una cuenta abre el
 * modal con esa cuenta; en cualquier otra pantalla, el formulario completo.
 * No se dispara mientras escribes ni con un modal abierto.
 */
document.addEventListener('keydown', function (e) {
    if (e.key !== 'n' && e.key !== 'N') return;
    if (e.metaKey || e.ctrlKey || e.altKey || e.repeat) return;
    const t = e.target;
    if (t.isContentEditable || ['INPUT', 'TEXTAREA', 'SELECT'].includes(t.tagName)) return;
    if (document.querySelector('[role="dialog"]:not([style*="display: none"])')) return;
    e.preventDefault();
    if (document.querySelector('[data-new-tx-modal]')) {
        window.dispatchEvent(new CustomEvent('new-tx'));
    } else {
        window.location.href = @js(route('transactions.create'));
    }
});
</script>

@stack('scripts')
</body>
</html>
