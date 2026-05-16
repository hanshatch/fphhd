<x-guest-layout>
    <div class="mb-6 text-center">
        <h2 class="text-xl font-semibold text-gray-900 dark:text-white">
            Configurar autenticación de dos factores
        </h2>
        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
            Escanea el código QR con Google Authenticator, Authy o cualquier app compatible.
        </p>
    </div>

    {{-- QR Code --}}
    <div class="flex justify-center mb-6">
        <div class="bg-white p-4 rounded-xl shadow-inner">
            {!! \BaconQrCode\Renderer\ImageRenderer::class && class_exists(\BaconQrCode\Writer::class)
                ? (new \BaconQrCode\Writer(
                    new \BaconQrCode\Renderer\ImageRenderer(
                        new \BaconQrCode\Renderer\RendererStyle\RendererStyle(200),
                        new \BaconQrCode\Renderer\Image\SvgImageBackEnd()
                    )
                ))->writeString($qrCodeUrl)
                : '<p class="text-sm text-gray-500">QR no disponible</p>'
            !!}
        </div>
    </div>

    {{-- Clave manual --}}
    <div class="mb-6 p-3 bg-gray-50 dark:bg-gray-700 rounded-lg text-center">
        <p class="text-xs text-gray-500 dark:text-gray-400 mb-1">Clave manual:</p>
        <code class="text-sm font-mono font-bold tracking-widest text-gray-800 dark:text-gray-200 select-all">
            {{ $secret }}
        </code>
    </div>

    {{-- Formulario de verificación --}}
    <form method="POST" action="{{ route('totp.setup.confirm') }}">
        @csrf

        <div>
            <x-input-label for="code" value="Código de verificación (6 dígitos)" />
            <x-text-input
                id="code"
                name="code"
                type="text"
                inputmode="numeric"
                pattern="[0-9]*"
                maxlength="6"
                autocomplete="one-time-code"
                class="mt-1 block w-full text-center text-2xl tracking-widest"
                placeholder="000000"
                autofocus
            />
            <x-input-error :messages="$errors->get('code')" class="mt-2" />
        </div>

        <div class="mt-6">
            <x-primary-button class="w-full justify-center">
                Activar 2FA
            </x-primary-button>
        </div>
    </form>
</x-guest-layout>
