<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name') }} · Finanzas Personales</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        body { font-family: 'Roboto', system-ui, sans-serif; }
        .fp-input {
            width: 100%;
            background: transparent;
            border: none;
            border-bottom: 1px solid rgba(255,255,255,0.12);
            padding: 12px 0;
            color: #fff;
            font-size: 15px;
            outline: none;
            transition: border-color 0.2s;
        }
        .fp-input::placeholder { color: rgba(255,255,255,0.2); }
        .fp-input:focus { border-bottom-color: #76a72b; }
        .fp-input:-webkit-autofill {
            -webkit-box-shadow: 0 0 0px 1000px #232323 inset;
            -webkit-text-fill-color: #fff;
        }
    </style>
</head>
<body style="min-height:100vh; background:#1e1e1e; display:flex; align-items:center; justify-content:center; padding:24px;">
    <div style="width:100%; max-width:340px;">
        {{ $slot }}
    </div>
</body>
</html>
