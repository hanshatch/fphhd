<x-app-layout :title="$account->exists ? 'Editar cuenta' : 'Nueva cuenta'">
    <div class="max-w-lg mx-auto">
        <x-page-header :title="$account->exists ? 'Editar cuenta' : 'Nueva cuenta'" :back="route('accounts.index')" />

        <x-card class="p-6">
            <form method="POST"
                  action="{{ $account->exists ? route('accounts.update', $account) : route('accounts.store') }}"
                  enctype="multipart/form-data"
                  class="space-y-5"
                  x-data="{ type: '{{ old('type', $account->type ?? 'debit') }}' }">
                @csrf
                @if($account->exists) @method('PATCH') @endif

                {{-- Nombre --}}
                <div>
                    <label class="block text-sm font-semibold text-[#373737] dark:text-white mb-1.5">Nombre <span class="text-[#76a72b]">*</span></label>
                    <input type="text" name="name" value="{{ old('name', $account->name) }}" required autofocus
                        class="w-full rounded-xl border border-[#ababab]/40 bg-[#efeded]/50 dark:bg-white/5 px-4 py-3 text-[#373737] dark:text-white placeholder-[#ababab] focus:outline-none focus:ring-2 focus:ring-[#76a72b] transition"
                        placeholder="ej. Banamex débito / Amex Gold">
                    @error('name')<p class="mt-1.5 text-xs text-red-500">{{ $message }}</p>@enderror
                </div>

                {{-- Tipo + Institución --}}
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-semibold text-[#373737] dark:text-white mb-1.5">Tipo <span class="text-[#76a72b]">*</span></label>
                        <select name="type" required x-model="type"
                            class="w-full rounded-xl border border-[#ababab]/40 bg-[#efeded]/50 dark:bg-white/5 px-4 py-3 text-[#373737] dark:text-white focus:outline-none focus:ring-2 focus:ring-[#76a72b] transition">
                            @foreach(['debit' => 'Débito', 'credit' => 'TDC', 'savings' => 'Caja de ahorro', 'investment' => 'Inversión', 'cash' => 'Efectivo'] as $val => $label)
                                <option value="{{ $val }}" {{ old('type', $account->type) === $val ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-[#373737] dark:text-white mb-1.5">Institución <span class="text-[#76a72b]">*</span></label>
                        <select name="institution" required
                            class="w-full rounded-xl border border-[#ababab]/40 bg-[#efeded]/50 dark:bg-white/5 px-4 py-3 text-[#373737] dark:text-white focus:outline-none focus:ring-2 focus:ring-[#76a72b] transition">
                            @foreach(['banamex' => 'Banamex', 'mercadopago' => 'MercadoPago', 'nu' => 'Nu', 'revolut' => 'Revolut', 'amex' => 'American Express', 'other' => 'Otra'] as $val => $label)
                                <option value="{{ $val }}" {{ old('institution', $account->institution) === $val ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                {{-- Saldo inicial (para TDC = deuda actual) --}}
                <div>
                    <label class="block text-sm font-semibold text-[#373737] dark:text-white mb-1.5">
                        <span x-text="type === 'credit' ? 'Deuda actual' : 'Saldo inicial'"></span>
                        <span class="text-[#76a72b]">*</span>
                    </label>
                    <div class="relative">
                        <span class="absolute left-4 top-1/2 -translate-y-1/2 text-[#878787] font-semibold">$</span>
                        <input type="number" name="initial_balance" step="0.01" min="0" inputmode="decimal" required
                            value="{{ old('initial_balance', $account->initial_balance ?? '0.00') }}"
                            class="w-full rounded-xl border border-[#ababab]/40 bg-[#efeded]/50 dark:bg-white/5 pl-8 pr-16 py-3 text-[#373737] dark:text-white focus:outline-none focus:ring-2 focus:ring-[#76a72b] transition">
                        <span class="absolute right-4 top-1/2 -translate-y-1/2 text-[#ababab] text-xs font-semibold uppercase tracking-wider">MXN</span>
                    </div>
                    @error('initial_balance')<p class="mt-1.5 text-xs text-red-500">{{ $message }}</p>@enderror
                </div>

                {{-- ── Campos exclusivos TDC ──────────────────────────────── --}}
                <div x-show="type === 'credit'" x-cloak
                     x-transition:enter="transition ease-out duration-200"
                     x-transition:enter-start="opacity-0 -translate-y-2"
                     x-transition:enter-end="opacity-100 translate-y-0"
                     class="space-y-4 p-4 rounded-xl bg-red-50/50 dark:bg-red-500/5 border border-red-100 dark:border-red-500/20">

                    <p class="text-xs font-bold text-red-500 uppercase tracking-wider">Datos de la tarjeta de crédito</p>

                    {{-- Día de corte + Día de pago --}}
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-semibold text-[#373737] dark:text-white mb-1.5">
                                Día de corte
                                <span class="text-[#76a72b]">*</span>
                            </label>
                            <input type="number" name="statement_day" min="1" max="31" inputmode="numeric"
                                value="{{ old('statement_day', $creditCard->statement_day ?? '') }}"
                                placeholder="ej. 15"
                                class="w-full rounded-xl border border-[#ababab]/40 bg-white dark:bg-white/5 px-4 py-3 text-[#373737] dark:text-white placeholder-[#ababab] focus:outline-none focus:ring-2 focus:ring-[#76a72b] transition">
                            <p class="mt-1 text-[10px] text-[#ababab]">Día del mes en que cierra el periodo</p>
                            @error('statement_day')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-[#373737] dark:text-white mb-1.5">
                                Día de pago
                                <span class="text-[#76a72b]">*</span>
                            </label>
                            <input type="number" name="payment_day" min="1" max="31" inputmode="numeric"
                                value="{{ old('payment_day', $creditCard->payment_day ?? '') }}"
                                placeholder="ej. 5"
                                class="w-full rounded-xl border border-[#ababab]/40 bg-white dark:bg-white/5 px-4 py-3 text-[#373737] dark:text-white placeholder-[#ababab] focus:outline-none focus:ring-2 focus:ring-[#76a72b] transition">
                            <p class="mt-1 text-[10px] text-[#ababab]">Fecha límite para pagar sin intereses</p>
                            @error('payment_day')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
                        </div>
                    </div>

                    {{-- Límite de crédito --}}
                    <div>
                        <label class="block text-sm font-semibold text-[#373737] dark:text-white mb-1.5">Límite de crédito</label>
                        <div class="relative">
                            <span class="absolute left-4 top-1/2 -translate-y-1/2 text-[#878787] font-semibold">$</span>
                            <input type="number" name="credit_limit" step="0.01" min="0" inputmode="decimal"
                                value="{{ old('credit_limit', $creditCard->credit_limit ?? '') }}"
                                placeholder="ej. 50000.00"
                                class="w-full rounded-xl border border-[#ababab]/40 bg-white dark:bg-white/5 pl-8 pr-16 py-3 text-[#373737] dark:text-white placeholder-[#ababab] focus:outline-none focus:ring-2 focus:ring-[#76a72b] transition">
                            <span class="absolute right-4 top-1/2 -translate-y-1/2 text-[#ababab] text-xs font-semibold uppercase tracking-wider">MXN</span>
                        </div>
                    </div>
                </div>

                {{-- APR (cajas de ahorro) --}}
                <div x-show="type !== 'credit'" x-cloak>
                    <label class="block text-sm font-semibold text-[#373737] dark:text-white mb-1.5">APR nominal anual (%)</label>
                    <input type="number" name="invest_apr" step="0.01" min="0" max="100" inputmode="decimal"
                        value="{{ old('invest_apr', $account->invest_apr) }}"
                        placeholder="Solo cajas de ahorro · ej. 13.00"
                        class="w-full rounded-xl border border-[#ababab]/40 bg-[#efeded]/50 dark:bg-white/5 px-4 py-3 text-[#373737] dark:text-white placeholder-[#ababab] focus:outline-none focus:ring-2 focus:ring-[#76a72b] transition">
                </div>

                {{-- Logo + Color ──────────────────────────────────── --}}
                <div x-data="{
                        preview: '{{ $account->logoUrl() }}',
                        hasLogo: {{ $account->logo_path ? 'true' : 'false' }},
                        extracting: false,
                        extracted: false,

                        handleFile(e) {
                            const f = e.target.files[0];
                            if (!f) return;
                            const url = URL.createObjectURL(f);
                            this.preview = url;
                            this.hasLogo = true;
                            this.extractColorFromUrl(url);
                        },

                        extractColorFromUrl(url) {
                            this.extracting = true;
                            this.extracted = false;
                            const img = new Image();
                            img.crossOrigin = 'anonymous';
                            img.onload = () => {
                                const color = this.getDominantColor(img);
                                if (color) {
                                    this.$refs.colorPicker.value = color;
                                    this.$refs.colorPicker.dispatchEvent(new Event('input'));
                                    this.extracted = true;
                                }
                                this.extracting = false;
                            };
                            img.onerror = () => { this.extracting = false; };
                            img.src = url;
                        },

                        getDominantColor(img) {
                            const canvas = document.createElement('canvas');
                            const SIZE = 80;
                            canvas.width = canvas.height = SIZE;
                            const ctx = canvas.getContext('2d');
                            ctx.drawImage(img, 0, 0, SIZE, SIZE);

                            const data = ctx.getImageData(0, 0, SIZE, SIZE).data;
                            const buckets = {};

                            for (let i = 0; i < data.length; i += 4) {
                                const r = data[i], g = data[i+1], b = data[i+2], a = data[i+3];

                                // Ignorar transparentes y semi-transparentes
                                if (a < 180) continue;

                                // Ignorar blancos, grises y negros
                                const brightness = (r + g + b) / 3;
                                const saturation = Math.max(r,g,b) - Math.min(r,g,b);
                                if (brightness > 235 || brightness < 20 || saturation < 30) continue;

                                // Cuantizar en cubos de 24px para agrupar colores similares
                                const qr = Math.round(r / 24) * 24;
                                const qg = Math.round(g / 24) * 24;
                                const qb = Math.round(b / 24) * 24;
                                const key = `${qr},${qg},${qb}`;
                                // Peso extra para colores más saturados (más vibrantes)
                                buckets[key] = (buckets[key] || 0) + 1 + (saturation / 100);
                            }

                            const sorted = Object.entries(buckets).sort((a,b) => b[1]-a[1]);
                            if (!sorted.length) return null;

                            const [r,g,b] = sorted[0][0].split(',').map(Number);
                            return '#' + [r,g,b].map(v => v.toString(16).padStart(2,'0')).join('');
                        },

                        removeLogo() {
                            this.preview = null;
                            this.hasLogo = false;
                            this.extracted = false;
                            this.$refs.logoInput.value = '';
                        }
                    }">
                    <label class="block text-sm font-semibold text-[#373737] dark:text-white mb-1.5">Logotipo e identificador de color</label>

                    <div class="flex items-start gap-4">

                        {{-- Preview / zona de clic --}}
                        <div class="flex-shrink-0">
                            <div x-on:click="$refs.logoInput.click()"
                                 class="w-16 h-16 rounded-2xl border-2 border-dashed border-[#ababab]/40 hover:border-[#76a72b] transition-colors flex items-center justify-center overflow-hidden bg-white dark:bg-white/5 cursor-pointer relative">
                                <template x-if="preview">
                                    <img :src="preview" class="w-full h-full object-contain p-1.5">
                                </template>
                                <template x-if="!preview">
                                    <div class="text-center">
                                        <svg class="w-6 h-6 text-[#ababab] mx-auto mb-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                        <span class="text-[9px] text-[#ababab]">Logo</span>
                                    </div>
                                </template>
                                {{-- Overlay extracting --}}
                                <template x-if="extracting">
                                    <div class="absolute inset-0 bg-white/80 dark:bg-black/60 flex items-center justify-center rounded-2xl">
                                        <svg class="animate-spin w-5 h-5 text-[#76a72b]" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 14 6.373 14 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"/></svg>
                                    </div>
                                </template>
                            </div>
                            <input type="file" name="logo" accept="image/*" class="hidden"
                                   x-ref="logoInput" x-on:change="handleFile($event)">
                        </div>

                        {{-- Controles --}}
                        <div class="flex-1 space-y-2.5">

                            {{-- Color picker con badge de estado --}}
                            <div class="flex items-center gap-2.5">
                                <input type="color" name="color"
                                    value="{{ old('color', $account->color ?? '#76a72b') }}"
                                    x-ref="colorPicker"
                                    class="h-9 w-14 rounded-lg border border-[#ababab]/40 cursor-pointer p-1 bg-white flex-shrink-0 transition-all"
                                    :style="extracted ? 'box-shadow:0 0 0 3px #76a72b44' : ''">
                                <div>
                                    <p class="text-xs text-[#373737] dark:text-white font-medium" x-text="extracted ? '✓ Color extraído del logo' : 'Color identificador'"></p>
                                    <p class="text-[10px] text-[#ababab]" x-text="extracted ? 'Puedes ajustarlo manualmente' : 'Se auto-completa al subir un logo'"></p>
                                </div>
                            </div>

                            {{-- Botones --}}
                            <div class="flex gap-2 flex-wrap">
                                <button type="button" data-no-spinner="true"
                                    x-on:click="$refs.logoInput.click()"
                                    class="h-8 px-3 text-xs font-semibold border border-[#ababab]/40 rounded-lg hover:border-[#76a72b] hover:text-[#76a72b] text-[#878787] transition-colors">
                                    <span x-text="hasLogo ? 'Cambiar logo' : 'Subir logo'"></span>
                                </button>
                                <template x-if="hasLogo">
                                    <button type="button" data-no-spinner="true"
                                        x-on:click="removeLogo()"
                                        class="h-8 px-3 text-xs font-semibold border border-red-200 rounded-lg text-red-500 hover:bg-red-50 transition-colors">
                                        Quitar logo
                                    </button>
                                </template>
                            </div>
                            <p class="text-[10px] text-[#ababab]">PNG, JPG, SVG · máx. 2 MB · Mejor con fondo transparente</p>

                            <input type="hidden" name="remove_logo" x-bind:value="hasLogo ? '0' : '1'">
                        </div>
                    </div>
                </div>

                @if($account->exists)
                <div class="flex items-center gap-3 p-3 rounded-xl bg-[#efeded]/50 dark:bg-white/5">
                    <input type="checkbox" name="is_active" id="is_active" value="1" {{ $account->is_active ? 'checked' : '' }}
                        class="w-4 h-4 text-[#76a72b] rounded border-[#ababab] focus:ring-[#76a72b]">
                    <label for="is_active" class="text-sm text-[#373737] dark:text-white font-medium">Cuenta activa</label>
                </div>
                @endif

                <div>
                    <label class="block text-sm font-semibold text-[#373737] dark:text-white mb-1.5">Notas</label>
                    <textarea name="notes" rows="2" maxlength="500" placeholder="Opcional"
                        class="w-full rounded-xl border border-[#ababab]/40 bg-[#efeded]/50 dark:bg-white/5 px-4 py-3 text-[#373737] dark:text-white placeholder-[#ababab] focus:outline-none focus:ring-2 focus:ring-[#76a72b] transition resize-none">{{ old('notes', $account->notes) }}</textarea>
                </div>

                <div class="flex gap-3 pt-2">
                    <x-btn variant="secondary" href="{{ route('accounts.index') }}" class="flex-1">Cancelar</x-btn>
                    <x-btn type="submit" class="flex-1">{{ $account->exists ? 'Guardar cambios' : 'Crear cuenta' }}</x-btn>
                </div>
            </form>
        </x-card>
    </div>
</x-app-layout>
