<!DOCTYPE html>
<html lang="es" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name') }} · Finanzas</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        .bg-dots {
            background-color: #373737;
            background-image: radial-gradient(circle, rgba(255,255,255,0.07) 1px, transparent 1px);
            background-size: 24px 24px;
        }
    </style>
</head>
<body class="h-full bg-dots antialiased flex items-center justify-center px-4 py-12" style="font-family:'Roboto',system-ui,sans-serif">

    <div class="w-full max-w-md">

        {{-- Logo centrado arriba de la card --}}
        <div class="flex justify-center mb-8">
            <img src="{{ asset('images/logo.png') }}" alt="hans hatch"
                 class="h-9 brightness-0 invert opacity-80">
        </div>

        {{-- Card --}}
        <div class="bg-white rounded-3xl shadow-2xl shadow-black/40 px-8 py-10">

            {{-- Línea verde superior --}}
            <div class="h-1 bg-[#76a72b] rounded-full mb-8 -mt-10 mx-auto w-16"></div>

            {{ $slot }}
        </div>

        <p class="text-center text-xs text-white/25 mt-6">
            Finanzas Personales · Hans Hatch
        </p>
    </div>

</body>
</html>
