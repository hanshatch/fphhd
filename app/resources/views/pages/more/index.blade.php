<x-app-layout title="Más">
    <x-page-header title="Más" />

    @php
    $sections = [
        'Planeación' => [
            ['route' => 'scheduled.index',    'icon' => 'calendar',    'label' => 'Flujo',        'desc' => 'Próximos cargos e ingresos'],
            ['route' => 'recurring.index',    'icon' => 'repeat',      'label' => 'Recurrentes',  'desc' => 'Suscripciones y compras a MSI'],
            ['route' => 'income-plans.index', 'icon' => 'trending-up', 'label' => 'Ingresos',     'desc' => 'Planeación de ingresos variables'],
        ],
        'Análisis' => [
            ['route' => 'reports.index', 'icon' => 'chart', 'label' => 'Reportes', 'desc' => 'Anual, categorías, fuentes y rendimientos'],
        ],
        'Configuración' => [
            ['route' => 'categories.index', 'icon' => 'tag',       'label' => 'Categorías', 'desc' => 'Catálogo de categorías de gasto e ingreso'],
            ['route' => 'sources.index',    'icon' => 'briefcase', 'label' => 'Fuentes',    'desc' => 'Orígenes de ingreso operativo'],
            ['route' => 'profile.edit',     'icon' => 'user',      'label' => 'Perfil',     'desc' => 'Contraseña y seguridad'],
        ],
    ];
    @endphp

    <div class="space-y-6 max-w-lg mx-auto">
        @foreach($sections as $label => $items)
        <div>
            <p class="text-[10px] font-bold text-[#878787] uppercase tracking-widest mb-2 px-1">{{ $label }}</p>
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
        @endforeach

        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" data-no-spinner="true"
                class="w-full flex items-center justify-center gap-2 p-3.5 text-sm font-semibold text-red-500 bg-white dark:bg-[#2a2a2a] border border-[#ababab]/20 rounded-2xl hover:bg-red-50 dark:hover:bg-red-500/10 transition-colors">
                @include('layouts._icon', ['name' => 'logout', 'class' => 'w-4 h-4'])
                Cerrar sesión
            </button>
        </form>
    </div>
</x-app-layout>
