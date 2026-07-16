<x-app-layout title="Configuración">
    <x-page-header title="Configuración" />

    @php
    $items = [
        ['route' => 'categories.index', 'icon' => 'tag',       'label' => 'Categorías', 'desc' => 'Catálogo de categorías de gasto e ingreso'],
        ['route' => 'sources.index',    'icon' => 'briefcase', 'label' => 'Fuentes',    'desc' => 'Orígenes de ingreso operativo'],
        ['route' => 'profile.edit',     'icon' => 'user',      'label' => 'Perfil',     'desc' => 'Contraseña y seguridad (2FA)'],
    ];
    @endphp

    <div class="max-w-lg mx-auto">
        <x-card class="divide-y divide-[#efeded] dark:divide-white/10 overflow-hidden">
            @foreach($items as $item)
            <a href="{{ route($item['route']) }}" class="flex items-center gap-3 p-4 hover:bg-[#fafafa] dark:hover:bg-white/5 transition-colors">
                <div class="w-9 h-9 rounded-lg bg-[#76a72b]/10 text-[#76a72b] flex items-center justify-center flex-shrink-0">
                    @include('layouts._icon', ['name' => $item['icon'], 'class' => 'w-4.5 h-4.5'])
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-semibold text-[#373737] dark:text-white">{{ $item['label'] }}</p>
                    <p class="text-xs text-[#878787] truncate">{{ $item['desc'] }}</p>
                </div>
                <svg class="w-4 h-4 text-[#ababab] flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            </a>
            @endforeach
        </x-card>
    </div>
</x-app-layout>
