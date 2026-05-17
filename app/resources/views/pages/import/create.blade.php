<x-app-layout title="Importar estado de cuenta">
    <div class="max-w-lg mx-auto">
        <x-page-header title="Importar estado de cuenta" :back="route('transactions.index')" />

        <x-card class="p-6">
            <form method="POST" action="{{ route('import.upload') }}" enctype="multipart/form-data" class="space-y-5">
                @csrf

                {{-- Cuenta --}}
                <div>
                    <label class="block text-sm font-semibold text-[#373737] dark:text-white mb-1.5">
                        Cuenta bancaria <span class="text-[#76a72b]">*</span>
                    </label>
                    <select name="account_id" required
                        class="w-full rounded-xl border border-[#ababab]/40 bg-[#efeded]/50 dark:bg-white/5 px-4 py-3 text-[#373737] dark:text-white focus:outline-none focus:ring-2 focus:ring-[#76a72b] transition">
                        <option value="">Selecciona la cuenta del estado</option>
                        @foreach($accounts as $account)
                            <option value="{{ $account->id }}">{{ $account->name }} ({{ $account->institution }})</option>
                        @endforeach
                    </select>
                    @error('account_id')<p class="mt-1.5 text-xs text-red-500">{{ $message }}</p>@enderror
                </div>

                {{-- Archivo --}}
                <div x-data="{ name: null }" class="space-y-2">
                    <label class="block text-sm font-semibold text-[#373737] dark:text-white">
                        Archivo del estado de cuenta <span class="text-[#76a72b]">*</span>
                    </label>
                    <label class="block cursor-pointer">
                        <input type="file" name="file" accept=".csv,.txt,.xlsx,.xls,.ods" required
                            class="sr-only"
                            x-on:change="name = $event.target.files[0]?.name">
                        <div :class="name ? 'border-[#76a72b] bg-[#76a72b]/5' : 'border-[#ababab]/40 hover:border-[#76a72b]/50'"
                             class="border-2 border-dashed rounded-xl p-8 text-center transition-colors">
                            <svg class="mx-auto w-10 h-10 mb-3" :class="name ? 'text-[#76a72b]' : 'text-[#ababab]'" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                            </svg>
                            <p x-text="name ?? 'Haz clic o arrastra tu archivo aquí'" class="text-sm font-medium"
                               :class="name ? 'text-[#76a72b]' : 'text-[#878787]'"></p>
                            <p class="text-xs text-[#ababab] mt-1">CSV, TXT, XLSX — máx. 5 MB</p>
                        </div>
                    </label>
                    @error('file')<p class="text-xs text-red-500">{{ $message }}</p>@enderror
                </div>

                {{-- Info de formato --}}
                <div class="p-4 bg-[#efeded]/60 dark:bg-white/5 rounded-xl space-y-2">
                    <p class="text-xs font-bold text-[#878787] uppercase tracking-wider">Formato soportado</p>
                    <p class="text-xs text-[#878787]">
                        Banamex — columnas: <span class="font-mono bg-white px-1 rounded text-[#373737]">Fecha, Descripción, Depósitos, Retiros, Saldo</span>
                    </p>
                    <p class="text-xs text-[#ababab]">
                        Después de subir podrás revisar, editar el tipo y asignar categoría a cada movimiento antes de importar.
                    </p>
                </div>

                <x-btn type="submit" class="w-full">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                    Cargar y revisar
                </x-btn>
            </form>
        </x-card>
    </div>
</x-app-layout>
