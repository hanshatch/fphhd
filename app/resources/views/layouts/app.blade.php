<!DOCTYPE html>
<html lang="es" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'FP' }} · Finanzas</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full bg-[#efeded] dark:bg-[#1a1a1a] antialiased">

<div class="flex h-full">

    {{-- ── Sidebar desktop ──────────────────────────────────────── --}}
    <aside class="hidden lg:flex lg:flex-col lg:w-60 lg:fixed lg:inset-y-0 bg-[#373737] dark:bg-[#222222]">

        {{-- Logo --}}
        <div class="flex items-center h-16 px-5 border-b border-white/10">
            <span class="text-white text-xl font-light tracking-tight">hans</span>
            <span class="text-[#76a72b] text-xl font-bold tracking-tight">hatch</span>
            <span class="ml-2 text-white/30 text-xs uppercase tracking-widest">fp</span>
        </div>

        {{-- Nav --}}
        <nav class="flex-1 px-3 py-4 space-y-0.5 overflow-y-auto">
            @php
            $navItems = [
                ['route' => 'dashboard',          'match' => 'dashboard',       'icon' => 'home',        'label' => 'Panel'],
                ['route' => 'transactions.index', 'match' => 'transactions.*',  'icon' => 'arrows',      'label' => 'Movimientos'],
                ['route' => 'accounts.index',     'match' => 'accounts.*',      'icon' => 'card',        'label' => 'Cuentas'],
                ['route' => 'categories.index',   'match' => 'categories.*',    'icon' => 'tag',         'label' => 'Categorías'],
                ['route' => 'sources.index',      'match' => 'sources.*',       'icon' => 'briefcase',   'label' => 'Fuentes'],
            ];
            @endphp

            @foreach($navItems as $item)
            @php $active = request()->routeIs($item['match']); @endphp
            <a href="{{ route($item['route']) }}"
               class="{{ $active ? 'bg-[#76a72b] text-white' : 'text-white/60 hover:text-white hover:bg-white/10' }} group flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-all duration-150">
                @include('layouts._icon', ['name' => $item['icon'], 'class' => 'w-5 h-5 flex-shrink-0'])
                {{ $item['label'] }}
            </a>
            @endforeach
        </nav>

        {{-- Footer sidebar --}}
        <div class="p-3 border-t border-white/10">
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit"
                    class="w-full flex items-center gap-3 px-3 py-2.5 text-white/50 hover:text-white hover:bg-white/10 rounded-lg text-sm transition-all duration-150">
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

        {{-- Flash --}}
        @if(session('status'))
        <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 4000)"
             x-transition:leave="transition ease-in duration-300" x-transition:leave-end="opacity-0 -translate-y-2"
             class="mx-4 mt-4 flex items-center gap-3 p-3 bg-[#76a72b]/10 border border-[#76a72b]/30 rounded-xl text-sm text-[#4a7018] dark:text-[#76a72b]">
            <svg class="w-4 h-4 text-[#76a72b] flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
            {{ session('status') }}
        </div>
        @endif

        @if($errors->any())
        <div class="mx-4 mt-4 p-3 bg-red-50 border border-red-200 rounded-xl text-sm text-red-700">
            <ul class="list-disc list-inside space-y-0.5">
                @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
            </ul>
        </div>
        @endif

        {{-- Contenido --}}
        <main class="flex-1 p-4 lg:p-8 pb-24 lg:pb-8">
            {{ $slot }}
        </main>
    </div>
</div>

{{-- FAB móvil --}}
<a href="{{ route('transactions.create') }}"
   class="lg:hidden fixed bottom-20 right-4 z-30 w-14 h-14 bg-[#76a72b] hover:bg-[#659220] text-white rounded-full shadow-xl flex items-center justify-center transition-all duration-200 hover:scale-110 active:scale-95">
    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/></svg>
</a>

{{-- Bottom nav móvil --}}
<nav class="lg:hidden fixed bottom-0 inset-x-0 z-20 bg-[#373737] border-t border-white/10">
    <div class="grid grid-cols-5 h-16">
        @foreach([
            ['route' => 'dashboard',          'match' => 'dashboard',      'icon' => 'home',      'label' => 'Panel'],
            ['route' => 'transactions.index', 'match' => 'transactions.*', 'icon' => 'arrows',    'label' => 'Movimientos'],
            ['route' => 'transactions.create','match' => 'x',             'icon' => 'plus',       'label' => ''],
            ['route' => 'accounts.index',     'match' => 'accounts.*',    'icon' => 'card',       'label' => 'Cuentas'],
            ['route' => 'sources.index',      'match' => 'sources.*',     'icon' => 'briefcase',  'label' => 'Más'],
        ] as $item)
        @php $active = request()->routeIs($item['match']); @endphp
        @if($item['icon'] === 'plus')
        <div class="flex items-center justify-center">
            {{-- espacio para el FAB --}}
        </div>
        @else
        <a href="{{ route($item['route']) }}"
           class="{{ $active ? 'text-[#76a72b]' : 'text-white/40 hover:text-white/70' }} flex flex-col items-center justify-center gap-1 text-[10px] font-medium transition-colors">
            @include('layouts._icon', ['name' => $item['icon'], 'class' => 'w-5 h-5'])
            {{ $item['label'] }}
        </a>
        @endif
        @endforeach
    </div>
</nav>

<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>

<script>
/**
 * DESIGN SYSTEM — Spinner global en botones de acción
 * Se activa automáticamente en cualquier submit de form.
 * Para excluir un botón: <button data-no-spinner="true">
 */
(function () {
    const SPINNER_SVG = `<svg class="inline-block animate-spin w-4 h-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" aria-hidden="true">
        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 14 6.373 14 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"/>
    </svg>`;

    document.addEventListener('submit', function (e) {
        // e.submitter = botón exacto que activó el submit
        const btn = e.submitter || e.target.querySelector('[type="submit"]');
        if (!btn || btn.dataset.noSpinner === 'true') return;

        btn.disabled = true;
        btn.dataset.originalHtml = btn.innerHTML;
        btn.innerHTML = `${SPINNER_SVG}<span class="ml-1.5">Procesando…</span>`;

        // Safeguard: re-habilitar tras 15s si la página no navega
        setTimeout(() => {
            if (btn.dataset.originalHtml) {
                btn.disabled = false;
                btn.innerHTML = btn.dataset.originalHtml;
                delete btn.dataset.originalHtml;
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
        btn.innerHTML = `${SPINNER_SVG}<span class="ml-1.5">Procesando…</span>`;
    });
}());
</script>
</body>
</html>
