<section>
    <header>
        <h2 class="text-base font-bold text-[#373737] dark:text-white">Contraseña</h2>
        <p class="mt-1 text-sm text-[#878787]">Usa una contraseña larga que no repitas en otros sitios.</p>
    </header>

    <form method="post" action="{{ route('password.update') }}" class="mt-5 space-y-5">
        @csrf
        @method('put')

        <div>
            <label for="update_password_current_password" class="block text-sm font-semibold text-[#373737] dark:text-white mb-1.5">Contraseña actual</label>
            <input id="update_password_current_password" name="current_password" type="password" autocomplete="current-password"
                class="w-full rounded-xl border border-[#ababab]/40 bg-[#efeded]/50 dark:bg-white/5 px-4 py-3 text-[#373737] dark:text-white placeholder-[#ababab] focus:outline-none focus:ring-2 focus:ring-[#76a72b] transition">
            @error('current_password', 'updatePassword')<p class="mt-1.5 text-xs text-negative">{{ $message }}</p>@enderror
        </div>

        <div>
            <label for="update_password_password" class="block text-sm font-semibold text-[#373737] dark:text-white mb-1.5">Nueva contraseña</label>
            <input id="update_password_password" name="password" type="password" autocomplete="new-password"
                class="w-full rounded-xl border border-[#ababab]/40 bg-[#efeded]/50 dark:bg-white/5 px-4 py-3 text-[#373737] dark:text-white placeholder-[#ababab] focus:outline-none focus:ring-2 focus:ring-[#76a72b] transition">
            @error('password', 'updatePassword')<p class="mt-1.5 text-xs text-negative">{{ $message }}</p>@enderror
        </div>

        <div>
            <label for="update_password_password_confirmation" class="block text-sm font-semibold text-[#373737] dark:text-white mb-1.5">Confirmar nueva contraseña</label>
            <input id="update_password_password_confirmation" name="password_confirmation" type="password" autocomplete="new-password"
                class="w-full rounded-xl border border-[#ababab]/40 bg-[#efeded]/50 dark:bg-white/5 px-4 py-3 text-[#373737] dark:text-white placeholder-[#ababab] focus:outline-none focus:ring-2 focus:ring-[#76a72b] transition">
            @error('password_confirmation', 'updatePassword')<p class="mt-1.5 text-xs text-negative">{{ $message }}</p>@enderror
        </div>

        <div class="flex items-center gap-4">
            <x-btn type="submit">Actualizar contraseña</x-btn>
            @if (session('status') === 'password-updated')
                <p x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 2500)"
                   x-transition:leave="transition-opacity ease-snappy duration-150" x-transition:leave-end="opacity-0"
                   class="text-sm font-semibold text-positive">Guardado</p>
            @endif
        </div>
    </form>
</section>
