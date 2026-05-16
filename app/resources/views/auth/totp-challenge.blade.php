<x-guest-layout>
    <div class="mb-6 text-center">
        <div class="flex justify-center mb-3">
            <div class="w-14 h-14 bg-indigo-100 dark:bg-indigo-900 rounded-full flex items-center justify-center">
                <svg class="w-7 h-7 text-indigo-600 dark:text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                </svg>
            </div>
        </div>
        <h2 class="text-xl font-semibold text-gray-900 dark:text-white">
            Verificación de dos factores
        </h2>
        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
            Ingresa el código de 6 dígitos de tu app autenticadora.
        </p>
    </div>

    <form method="POST" action="{{ route('totp.challenge.verify') }}">
        @csrf

        <div>
            <x-input-label for="code" value="Código" />
            <x-text-input
                id="code"
                name="code"
                type="text"
                inputmode="numeric"
                pattern="[0-9]*"
                maxlength="6"
                autocomplete="one-time-code"
                class="mt-1 block w-full text-center text-3xl tracking-widest"
                placeholder="000000"
                autofocus
            />
            <x-input-error :messages="$errors->get('code')" class="mt-2" />
        </div>

        <div class="mt-6">
            <x-primary-button class="w-full justify-center">
                Verificar
            </x-primary-button>
        </div>

        <div class="mt-4 text-center">
            <form method="POST" action="{{ route('logout') }}" class="inline">
                @csrf
                <button type="submit" class="text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300 underline">
                    Cerrar sesión
                </button>
            </form>
        </div>
    </form>
</x-guest-layout>
