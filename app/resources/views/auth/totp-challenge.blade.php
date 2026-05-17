<x-guest-layout>

    {{-- Ícono --}}
    <div style="text-align:center; margin-bottom:32px;">
        <div style="width:56px; height:56px; background:rgba(118,167,43,0.15); border-radius:50%; display:inline-flex; align-items:center; justify-content:center; margin-bottom:20px;">
            <svg style="width:26px; height:26px; color:#76a72b;" fill="none" stroke="#76a72b" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
            </svg>
        </div>
        <h2 style="color:#fff; font-size:20px; font-weight:700; margin:0 0 8px;">
            Verificación 2FA
        </h2>
        <p style="color:rgba(255,255,255,0.4); font-size:13px; margin:0;">
            Ingresa el código de 6 dígitos de tu app autenticadora.
        </p>
    </div>

    <form method="POST" action="{{ route('totp.challenge.verify') }}" style="display:flex; flex-direction:column; gap:20px;">
        @csrf

        <div>
            <label for="code" style="display:block; color:rgba(255,255,255,0.3); font-size:11px; letter-spacing:0.1em; text-transform:uppercase; margin-bottom:8px;">
                Código
            </label>
            <input id="code" name="code" type="text"
                inputmode="numeric" pattern="[0-9]*" maxlength="6"
                autocomplete="one-time-code" autofocus
                placeholder="000 000"
                class="fp-input"
                style="text-align:center; font-size:28px; letter-spacing:0.3em; font-weight:700;">
            @error('code')
                <p style="color:#f87171; font-size:12px; margin-top:6px;">{{ $message }}</p>
            @enderror
        </div>

        <button type="submit"
            style="width:100%; padding:14px; background:#76a72b; color:#fff; border:none; border-radius:10px; font-size:15px; font-weight:600; cursor:pointer; font-family:'Roboto',sans-serif; letter-spacing:0.01em;"
            onmouseover="this.style.background='#659220'"
            onmouseout="this.style.background='#76a72b'">
            Verificar
        </button>
    </form>

    <div style="text-align:center; margin-top:24px;">
        <form method="POST" action="{{ route('logout') }}" style="display:inline;">
            @csrf
            <button type="submit"
                style="background:none; border:none; color:rgba(255,255,255,0.25); font-size:13px; cursor:pointer; text-decoration:underline; font-family:'Roboto',sans-serif;"
                onmouseover="this.style.color='rgba(255,255,255,0.5)'"
                onmouseout="this.style.color='rgba(255,255,255,0.25)'">
                Cerrar sesión
            </button>
        </form>
    </div>

</x-guest-layout>
