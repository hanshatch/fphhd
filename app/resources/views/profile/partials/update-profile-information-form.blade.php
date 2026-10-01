<section>
    <header>
        <h2 class="text-base font-bold text-[#373737] dark:text-white">Datos de la cuenta</h2>
        <p class="mt-1 text-sm text-[#878787]">Nombre y correo con el que inicias sesión.</p>
    </header>

    <form method="post" action="{{ route('profile.update') }}" class="mt-5 space-y-5">
        @csrf
        @method('patch')

        <div>
            <label for="name" class="block text-sm font-semibold text-[#373737] dark:text-white mb-1.5">Nombre</label>
            <input id="name" name="name" type="text" value="{{ old('name', $user->name) }}" required autocomplete="name"
                class="w-full rounded-xl border border-[#ababab]/40 bg-[#efeded]/50 dark:bg-white/5 px-4 py-3 text-[#373737] dark:text-white placeholder-[#ababab] focus:outline-none focus:ring-2 focus:ring-[#76a72b] transition">
            @error('name')<p class="mt-1.5 text-xs text-red-500">{{ $message }}</p>@enderror
        </div>

        <div>
            <label for="email" class="block text-sm font-semibold text-[#373737] dark:text-white mb-1.5">Correo</label>
            <input id="email" name="email" type="email" value="{{ old('email', $user->email) }}" required autocomplete="username"
                class="w-full rounded-xl border border-[#ababab]/40 bg-[#efeded]/50 dark:bg-white/5 px-4 py-3 text-[#373737] dark:text-white placeholder-[#ababab] focus:outline-none focus:ring-2 focus:ring-[#76a72b] transition">
            @error('email')<p class="mt-1.5 text-xs text-red-500">{{ $message }}</p>@enderror
        </div>

        <div class="flex items-center gap-4">
            <x-btn type="submit">Guardar</x-btn>
            @if (session('status') === 'profile-updated')
                <p x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 2500)"
                   x-transition:leave="transition-opacity ease-snappy duration-150" x-transition:leave-end="opacity-0"
                   class="text-sm font-semibold text-[#76a72b]">Guardado</p>
            @endif
        </div>
    </form>
</section>
