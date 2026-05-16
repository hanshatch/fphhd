<!DOCTYPE html>
<html lang="es" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'FP' }} — Finanzas Personales</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full bg-gray-50 dark:bg-gray-950 font-sans antialiased">

    <div class="flex h-full">

        {{-- Sidebar desktop --}}
        <aside class="hidden lg:flex lg:flex-col lg:w-64 lg:fixed lg:inset-y-0 bg-white dark:bg-gray-900 border-r border-gray-200 dark:border-gray-800">
            <div class="flex items-center h-16 px-6 border-b border-gray-200 dark:border-gray-800">
                <span class="text-lg font-bold text-indigo-600 dark:text-indigo-400">FP</span>
                <span class="ml-2 text-sm text-gray-500 dark:text-gray-400">Finanzas</span>
            </div>
            <nav class="flex-1 px-3 py-4 space-y-1 overflow-y-auto">
                <x-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')">
                    <x-icon name="home" class="mr-3 h-5 w-5" /> Panel
                </x-nav-link>
                <x-nav-link :href="route('transactions.index')" :active="request()->routeIs('transactions.*')">
                    <x-icon name="arrow-left-right" class="mr-3 h-5 w-5" /> Movimientos
                </x-nav-link>
                <x-nav-link :href="route('accounts.index')" :active="request()->routeIs('accounts.*')">
                    <x-icon name="landmark" class="mr-3 h-5 w-5" /> Cuentas
                </x-nav-link>
                <x-nav-link :href="route('categories.index')" :active="request()->routeIs('categories.*')">
                    <x-icon name="tag" class="mr-3 h-5 w-5" /> Categorías
                </x-nav-link>
                <x-nav-link :href="route('sources.index')" :active="request()->routeIs('sources.*')">
                    <x-icon name="briefcase" class="mr-3 h-5 w-5" /> Fuentes
                </x-nav-link>
            </nav>
            <div class="p-3 border-t border-gray-200 dark:border-gray-800">
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="w-full flex items-center px-3 py-2 text-sm text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800 rounded-lg">
                        <x-icon name="log-out" class="mr-3 h-4 w-4" /> Cerrar sesión
                    </button>
                </form>
            </div>
        </aside>

        {{-- Contenido principal --}}
        <div class="flex-1 lg:pl-64 flex flex-col min-h-full">

            {{-- Header móvil --}}
            <header class="lg:hidden sticky top-0 z-20 bg-white dark:bg-gray-900 border-b border-gray-200 dark:border-gray-800">
                <div class="flex items-center justify-between h-14 px-4">
                    <span class="text-base font-bold text-indigo-600 dark:text-indigo-400">
                        {{ $title ?? 'FP' }}
                    </span>
                </div>
            </header>

            {{-- Notificaciones flash --}}
            @if(session('status'))
                <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 4000)"
                     class="mx-4 mt-4 p-3 bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-lg text-sm text-green-700 dark:text-green-400">
                    {{ session('status') }}
                </div>
            @endif

            {{-- Contenido --}}
            <main class="flex-1 px-4 py-4 lg:px-8 lg:py-6 pb-24 lg:pb-6">
                {{ $slot }}
            </main>
        </div>
    </div>

    {{-- FAB: nuevo movimiento --}}
    <a href="{{ route('transactions.create') }}"
       class="lg:hidden fixed bottom-20 right-4 z-30 w-14 h-14 bg-indigo-600 hover:bg-indigo-700 text-white rounded-full shadow-lg flex items-center justify-center transition-colors">
        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4" />
        </svg>
    </a>

    {{-- Bottom navigation móvil --}}
    <nav class="lg:hidden fixed bottom-0 inset-x-0 z-20 bg-white dark:bg-gray-900 border-t border-gray-200 dark:border-gray-800">
        <div class="grid grid-cols-4 h-16">
            <a href="{{ route('dashboard') }}"
               class="flex flex-col items-center justify-center gap-1 text-xs {{ request()->routeIs('dashboard') ? 'text-indigo-600 dark:text-indigo-400' : 'text-gray-500 dark:text-gray-400' }}">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
                Panel
            </a>
            <a href="{{ route('transactions.index') }}"
               class="flex flex-col items-center justify-center gap-1 text-xs {{ request()->routeIs('transactions.*') ? 'text-indigo-600 dark:text-indigo-400' : 'text-gray-500 dark:text-gray-400' }}">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/></svg>
                Movimientos
            </a>
            <a href="{{ route('accounts.index') }}"
               class="flex flex-col items-center justify-center gap-1 text-xs {{ request()->routeIs('accounts.*') ? 'text-indigo-600 dark:text-indigo-400' : 'text-gray-500 dark:text-gray-400' }}">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg>
                Cuentas
            </a>
            <a href="{{ route('sources.index') }}"
               class="flex flex-col items-center justify-center gap-1 text-xs {{ request()->routeIs('sources.*') || request()->routeIs('categories.*') ? 'text-indigo-600 dark:text-indigo-400' : 'text-gray-500 dark:text-gray-400' }}">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"/></svg>
                Más
            </a>
        </div>
    </nav>

    {{-- Alpine.js para interactividad ligera --}}
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>

</body>
</html>
