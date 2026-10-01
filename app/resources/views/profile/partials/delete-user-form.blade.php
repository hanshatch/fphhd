{{-- Zona de riesgo: borra la cuenta con todos sus datos. La contraseña se pide
     en línea y el modal global de confirmación hace la segunda verificación. --}}
<section>
    <header>
        <h2 class="text-base font-bold text-red-600 dark:text-red-400">Eliminar cuenta</h2>
        <p class="mt-1 text-sm text-[#878787]">Se borran la cuenta y todos sus movimientos, cuentas y reportes. No se puede deshacer.</p>
    </header>

    <form method="post" action="{{ route('profile.destroy') }}" class="mt-5 space-y-4"
          data-confirm-title="Eliminar cuenta"
          data-confirm="Se borrarán todos tus datos de FP de forma permanente. ¿Continuar?"
          data-confirm-label="Eliminar todo">
        @csrf
        @method('delete')

        <div>
            <label for="delete_password" class="block text-sm font-semibold text-[#373737] dark:text-white mb-1.5">Escribe tu contraseña para confirmar</label>
            <input id="delete_password" name="password" type="password" required autocomplete="current-password"
                class="w-full rounded-xl border border-[#ababab]/40 bg-[#efeded]/50 dark:bg-white/5 px-4 py-3 text-[#373737] dark:text-white placeholder-[#ababab] focus:outline-none focus:ring-2 focus:ring-[#76a72b] transition">
            @error('password', 'userDeletion')<p class="mt-1.5 text-xs text-red-500">{{ $message }}</p>@enderror
        </div>

        <x-btn type="submit" variant="danger">Eliminar cuenta</x-btn>
    </form>
</section>
