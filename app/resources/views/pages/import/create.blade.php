<x-app-layout title="Importar estado de cuenta">
    <div class="max-w-lg mx-auto">
        <x-page-header title="Importar estado de cuenta" :back="route('transactions.index')" />

        <x-card class="p-6">
            <form method="POST" action="{{ route('import.upload') }}" enctype="multipart/form-data"
                  class="space-y-5" x-ref="form">
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

                {{-- Dropzone con drag & drop real --}}
                <div
                    x-data="{
                        name: null,
                        dragging: false,
                        error: null,
                        allowed: ['csv','txt','xlsx','xls','ods'],
                        handleDrop(e) {
                            this.dragging = false;
                            const file = e.dataTransfer.files[0];
                            if (!file) return;
                            const ext = file.name.split('.').pop().toLowerCase();
                            if (!this.allowed.includes(ext)) {
                                this.error = 'Formato no permitido. Usa CSV, TXT o XLSX.';
                                return;
                            }
                            this.error = null;
                            this.name = file.name;
                            // Asignar el archivo al input real
                            const dt = new DataTransfer();
                            dt.items.add(file);
                            this.$refs.fileInput.files = dt.files;
                        },
                        handleChange(e) {
                            const file = e.target.files[0];
                            this.name = file?.name ?? null;
                            this.error = null;
                        }
                    }"
                    class="space-y-2"
                >
                    <label class="block text-sm font-semibold text-[#373737] dark:text-white">
                        Archivo del estado de cuenta <span class="text-[#76a72b]">*</span>
                    </label>

                    <div
                        x-on:dragenter.prevent="dragging = true"
                        x-on:dragover.prevent="dragging = true"
                        x-on:dragleave.prevent="dragging = false"
                        x-on:drop.prevent="handleDrop($event)"
                        x-on:click="$refs.fileInput.click()"
                        :class="{
                            'border-[#76a72b] bg-[#76a72b]/5': name || dragging,
                            'border-[#76a72b]/60 bg-[#76a72b]/10 scale-[1.01]': dragging,
                            'border-[#ababab]/40 hover:border-[#76a72b]/50 hover:bg-[#efeded]/50': !name && !dragging
                        }"
                        class="border-2 border-dashed rounded-xl p-10 text-center cursor-pointer transition-all duration-150 select-none"
                    >
                        <input
                            type="file"
                            name="file"
                            accept=".csv,.txt,.xlsx,.xls,.ods"
                            required
                            class="sr-only"
                            x-ref="fileInput"
                            x-on:change="handleChange($event)"
                        >

                        <svg class="mx-auto w-10 h-10 mb-3 transition-colors"
                             :class="name || dragging ? 'text-[#76a72b]' : 'text-[#ababab]'"
                             fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                  d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
                        </svg>

                        <p class="text-sm font-semibold transition-colors"
                           :class="name ? 'text-[#76a72b]' : (dragging ? 'text-[#76a72b]' : 'text-[#878787]')"
                           x-text="dragging ? 'Suelta el archivo aquí' : (name ?? 'Haz clic o arrastra tu archivo')">
                        </p>
                        <p class="text-xs text-[#ababab] mt-1.5">CSV · TXT · XLSX — máx. 5 MB</p>
                    </div>

                    <p x-show="error" x-text="error" class="text-xs text-red-500"></p>
                    @error('file')<p class="text-xs text-red-500">{{ $message }}</p>@enderror
                </div>

                {{-- Info de formato --}}
                <div class="p-4 bg-[#efeded]/60 dark:bg-white/5 rounded-xl space-y-1.5">
                    <p class="text-xs font-bold text-[#878787] uppercase tracking-wider">Formato soportado</p>
                    <p class="text-xs text-[#878787]">
                        Banamex — columnas:
                        <span class="font-mono bg-white dark:bg-white/10 px-1.5 py-0.5 rounded text-[#373737] dark:text-white text-[11px]">
                            Fecha, Descripción, Depósitos, Retiros, Saldo
                        </span>
                    </p>
                    <p class="text-xs text-[#ababab]">
                        Podrás revisar, editar tipo y asignar categoría a cada movimiento antes de importar.
                    </p>
                </div>

                <x-btn type="submit" class="w-full">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
                    </svg>
                    Cargar y revisar
                </x-btn>
            </form>
        </x-card>
    </div>
</x-app-layout>
