<!DOCTYPE html>
<html lang="es" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name') }} · Finanzas</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full bg-[#efeded] antialiased" style="font-family:'Roboto',system-ui,sans-serif">

    <div class="min-h-full flex">

        {{-- Panel izquierdo — solo desktop --}}
        <div class="hidden lg:flex lg:flex-col lg:w-1/2 xl:w-2/5 bg-[#373737] relative overflow-hidden">

            {{-- Patrón de fondo sutil --}}
            <div class="absolute inset-0 opacity-5"
                 style="background-image: radial-gradient(circle at 1px 1px, #fff 1px, transparent 0); background-size: 32px 32px;">
            </div>

            {{-- Contenido centrado --}}
            <div class="relative flex flex-col items-start justify-center flex-1 px-12">
                {{-- Logo --}}
                <img src="{{ asset('images/logo.png') }}" alt="hans hatch" class="h-10 mb-12 brightness-0 invert opacity-90">

                <h1 class="text-4xl font-bold text-white leading-tight mb-3">
                    Tus finanzas,<br>bajo control.
                </h1>
                <p class="text-white/50 text-base leading-relaxed max-w-xs">
                    Registra ingresos, egresos e inversiones. Visualiza tu patrimonio y toma mejores decisiones.
                </p>

                {{-- Decoración inferior --}}
                <div class="absolute bottom-0 left-0 right-0 h-1 bg-[#76a72b]"></div>
            </div>
        </div>

        {{-- Panel derecho — formulario --}}
        <div class="flex-1 flex flex-col items-center justify-center px-4 py-12 sm:px-8">

            {{-- Logo mobile --}}
            <div class="lg:hidden mb-8">
                <img src="{{ asset('images/logo.png') }}" alt="hans hatch" class="h-8">
            </div>

            <div class="w-full max-w-sm">
                {{ $slot }}
            </div>

            <p class="mt-8 text-xs text-[#ababab]">
                FP · Finanzas Personales
            </p>
        </div>
    </div>

</body>
</html>
