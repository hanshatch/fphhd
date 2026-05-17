<x-guest-layout>

    <h2 class="text-xl font-bold text-[#373737] mb-1">Bienvenido</h2>
    <p class="text-sm text-[#ababab] mb-7">Ingresa para continuar a tus finanzas</p>

    @if (session('status'))
        <div class="mb-5 p-3 bg-[#76a72b]/10 border border-[#76a72b]/20 rounded-xl text-sm text-[#4a7018]">
            {{ session('status') }}
        </div>
    @endif

    <form method="POST" action="{{ route('login') }}" class="space-y-5">
        @csrf

        <div>
            <label for="email" class="block text-xs font-semibold text-[#878787] uppercase tracking-wider mb-2">
                Correo
            </label>
            <input id="email" type="email" name="email" value="{{ old('email') }}"
                required autofocus autocomplete="username"
                class="w-full rounded-xl border border-[#efeded] bg-[#efeded]/60 px-4 py-3 text-[#373737] placeholder-[#ababab] focus:outline-none focus:ring-2 focus:ring-[#76a72b] focus:bg-white transition text-sm"
                placeholder="tu@correo.com">
            @error('email')
                <p class="mt-1.5 text-xs text-red-500">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="password" class="block text-xs font-semibold text-[#878787] uppercase tracking-wider mb-2">
                Contraseña
            </label>
            <input id="password" type="password" name="password"
                required autocomplete="current-password"
                class="w-full rounded-xl border border-[#efeded] bg-[#efeded]/60 px-4 py-3 text-[#373737] placeholder-[#ababab] focus:outline-none focus:ring-2 focus:ring-[#76a72b] focus:bg-white transition text-sm"
                placeholder="••••••••••••">
            @error('password')
                <p class="mt-1.5 text-xs text-red-500">{{ $message }}</p>
            @enderror
        </div>

        <div class="flex items-center justify-between pt-1">
            <label class="flex items-center gap-2 cursor-pointer">
                <input type="checkbox" name="remember"
                    class="w-4 h-4 rounded border-[#ababab] text-[#76a72b] focus:ring-[#76a72b]">
                <span class="text-xs text-[#878787]">Recordarme</span>
            </label>
        </div>

        <button type="submit"
            class="w-full py-3 bg-[#76a72b] hover:bg-[#659220] text-white font-semibold rounded-xl transition-all active:scale-[0.98] shadow-sm shadow-[#76a72b]/30 mt-2">
            Entrar
        </button>
    </form>

</x-guest-layout>
