<x-guest-layout>

    {{-- Logo --}}
    <div style="text-align:center; margin-bottom:48px;">
        <img src="{{ asset('images/logo.png') }}" alt="hans hatch"
             style="height:32px; filter:brightness(0) invert(1); opacity:0.75; display:inline-block;">
    </div>

    {{-- Encabezado --}}
    <div style="margin-bottom:36px;">
        <p style="color:rgba(255,255,255,0.3); font-size:11px; letter-spacing:0.12em; text-transform:uppercase; margin-bottom:8px;">
            Finanzas Personales
        </p>
        <h1 style="color:#fff; font-size:24px; font-weight:700; line-height:1.2; margin:0;">
            Bienvenido, Hans
        </h1>
    </div>

    @if (session('status'))
        <div style="margin-bottom:20px; padding:12px 14px; background:rgba(118,167,43,0.12); border-left:3px solid #76a72b; border-radius:4px; color:#a3c96a; font-size:13px;">
            {{ session('status') }}
        </div>
    @endif

    <form method="POST" action="{{ route('login') }}" style="display:flex; flex-direction:column; gap:28px;">
        @csrf

        {{-- Email --}}
        <div>
            <label for="email" style="display:block; color:rgba(255,255,255,0.3); font-size:11px; letter-spacing:0.1em; text-transform:uppercase; margin-bottom:8px;">
                Correo
            </label>
            <input id="email" type="email" name="email" value="{{ old('email') }}"
                required autofocus autocomplete="username"
                placeholder="hans@hatch.mx"
                class="fp-input">
            @error('email')
                <p style="color:#f87171; font-size:12px; margin-top:6px;">{{ $message }}</p>
            @enderror
        </div>

        {{-- Contraseña --}}
        <div>
            <label for="password" style="display:block; color:rgba(255,255,255,0.3); font-size:11px; letter-spacing:0.1em; text-transform:uppercase; margin-bottom:8px;">
                Contraseña
            </label>
            <input id="password" type="password" name="password"
                required autocomplete="current-password"
                placeholder="••••••••••••"
                class="fp-input">
            @error('password')
                <p style="color:#f87171; font-size:12px; margin-top:6px;">{{ $message }}</p>
            @enderror
        </div>

        {{-- Recordarme + botón --}}
        <div style="display:flex; flex-direction:column; gap:16px; padding-top:4px;">
            <label style="display:flex; align-items:center; gap:10px; cursor:pointer;">
                <input type="checkbox" name="remember"
                    style="width:16px; height:16px; accent-color:#76a72b; cursor:pointer;">
                <span style="color:rgba(255,255,255,0.35); font-size:13px;">Mantener sesión</span>
            </label>

            <button type="submit"
                style="width:100%; padding:14px; background:#76a72b; color:#fff; border:none; border-radius:10px; font-size:15px; font-weight:600; cursor:pointer; letter-spacing:0.01em; transition:background 0.15s; font-family:'Roboto',sans-serif;"
                onmouseover="this.style.background='#659220'"
                onmouseout="this.style.background='#76a72b'"
                onmousedown="this.style.transform='scale(0.98)'"
                onmouseup="this.style.transform='scale(1)'">
                Entrar
            </button>
        </div>
    </form>

    {{-- Footer --}}
    <p style="text-align:center; color:rgba(255,255,255,0.15); font-size:11px; margin-top:48px; letter-spacing:0.05em;">
        FP · {{ date('Y') }}
    </p>

</x-guest-layout>
