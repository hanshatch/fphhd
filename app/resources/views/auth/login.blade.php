<x-guest-layout>

    <h2 class="text-2xl font-bold text-[#373737] mb-1">Iniciar sesión</h2>
    <p class="text-sm text-[#878787] mb-6">Ingresa tus credenciales para continuar</p>

    @if (session('status'))
        <div class="mb-4 p-3 bg-[#76a72b]/10 border border-[#76a72b]/20 rounded-xl text-sm text-[#4a7018]">
            {{ session('status') }}
        </div>
    @endif

    <form method="POST" action="{{ route('login') }}" class="space-y-4">
        @csrf

        {{-- Email --}}
        <div>
            <label for="email" class="block text-sm font-medium text-[#373737] mb-1.5">
                Correo electrónico
            </label>
            <input id="email" type="email" name="email" value="{{ old('email') }}"
                required autofocus autocomplete="username"
                class="w-full rounded-xl border border-[#ababab]/40 bg-white px-4 py-3 text-[#373737] placeholder-[#ababab] focus:outline-none focus:ring-2 focus:ring-[#76a72b] focus:border-transparent transition"
                placeholder="hans@hatch.mx">
            @error('email')
                <p class="mt-1.5 text-xs text-red-500">{{ $message }}</p>
            @enderror
        </div>

        {{-- Password --}}
        <div>
            <label for="password" class="block text-sm font-medium text-[#373737] mb-1.5">
                Contraseña
            </label>
            <input id="password" type="password" name="password"
                required autocomplete="current-password"
                class="w-full rounded-xl border border-[#ababab]/40 bg-white px-4 py-3 text-[#373737] placeholder-[#ababab] focus:outline-none focus:ring-2 focus:ring-[#76a72b] focus:border-transparent transition"
                placeholder="••••••••••••">
            @error('password')
                <p class="mt-1.5 text-xs text-red-500">{{ $message }}</p>
            @enderror
        </div>

        {{-- Recuérdame --}}
        <div class="flex items-center gap-2">
            <input id="remember_me" type="checkbox" name="remember"
                class="w-4 h-4 rounded border-[#ababab] text-[#76a72b] focus:ring-[#76a72b] cursor-pointer">
            <label for="remember_me" class="text-sm text-[#878787] cursor-pointer">
                Mantener sesión iniciada
            </label>
        </div>

        {{-- Submit --}}
        <button type="submit"
            class="w-full py-3 px-4 bg-[#76a72b] hover:bg-[#659220] text-white font-semibold rounded-xl transition-all active:scale-95 shadow-sm">
            Entrar
        </button>
    </form>

</x-guest-layout>
