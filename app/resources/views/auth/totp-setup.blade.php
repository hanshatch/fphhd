<x-guest-layout>

    <div style="text-align:center; margin-bottom:28px;">
        <p style="color:rgba(255,255,255,0.3); font-size:11px; letter-spacing:0.1em; text-transform:uppercase; margin-bottom:8px;">
            Configuración única
        </p>
        <h2 style="color:#fff; font-size:20px; font-weight:700; margin:0 0 8px;">
            Activa la verificación en 2 pasos
        </h2>
        <p style="color:rgba(255,255,255,0.4); font-size:13px; margin:0; line-height:1.5;">
            Escanea el QR con Google Authenticator, Authy o cualquier app compatible.
        </p>
    </div>

    {{-- QR Code --}}
    <div style="display:flex; justify-content:center; margin-bottom:20px;">
        <div style="background:#fff; padding:14px; border-radius:16px; display:inline-block;">
            {!! class_exists(\BaconQrCode\Writer::class)
                ? (new \BaconQrCode\Writer(
                    new \BaconQrCode\Renderer\ImageRenderer(
                        new \BaconQrCode\Renderer\RendererStyle\RendererStyle(180),
                        new \BaconQrCode\Renderer\Image\SvgImageBackEnd()
                    )
                ))->writeString($qrCodeUrl)
                : '<p style="color:#878787;font-size:13px;padding:20px;">QR no disponible</p>'
            !!}
        </div>
    </div>

    {{-- Clave manual --}}
    <div style="background:rgba(255,255,255,0.05); border:1px solid rgba(255,255,255,0.08); border-radius:10px; padding:12px; text-align:center; margin-bottom:24px;">
        <p style="color:rgba(255,255,255,0.3); font-size:11px; text-transform:uppercase; letter-spacing:0.08em; margin:0 0 6px;">
            Clave manual
        </p>
        <code style="color:#76a72b; font-family:'Roboto Mono',monospace; font-size:14px; letter-spacing:0.2em; font-weight:700; user-select:all;">
            {{ $secret }}
        </code>
    </div>

    <form method="POST" action="{{ route('totp.setup.confirm') }}" style="display:flex; flex-direction:column; gap:20px;">
        @csrf

        <div>
            <label for="code" style="display:block; color:rgba(255,255,255,0.3); font-size:11px; letter-spacing:0.1em; text-transform:uppercase; margin-bottom:8px;">
                Código de verificación
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
            style="width:100%; padding:14px; background:#76a72b; color:#fff; border:none; border-radius:10px; font-size:15px; font-weight:600; cursor:pointer; font-family:'Roboto',sans-serif;"
            onmouseover="this.style.background='#659220'"
            onmouseout="this.style.background='#76a72b'">
            Activar 2FA
        </button>
    </form>

</x-guest-layout>
