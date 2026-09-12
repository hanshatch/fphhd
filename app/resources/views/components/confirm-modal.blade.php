{{--
    Modal global de confirmación (reemplaza a window.confirm).

    Uso en cualquier <form>:
        <form method="POST" data-confirm="¿Eliminar «X»?" data-confirm-label="Eliminar">

    El script global del layout intercepta el submit, abre este modal y solo
    envía el formulario si Hans acepta. data-confirm-label es opcional
    (por defecto «Aceptar»). data-confirm-title también es opcional.
--}}
<div x-data="{
        open: false,
        title: 'Confirmar',
        message: '',
        label: 'Aceptar',
        onAccept: null,
        show(detail) {
            this.title    = detail.title   || 'Confirmar';
            this.message  = detail.message || '';
            this.label    = detail.label   || 'Aceptar';
            this.onAccept = detail.onAccept || null;
            this.open     = true;
            this.$nextTick(() => this.$refs.accept?.focus());
        },
        accept() {
            const fn = this.onAccept;
            this.close();
            if (typeof fn === 'function') fn();
        },
        close() { this.open = false; this.onAccept = null; }
     }"
     x-on:confirm-modal.window="show($event.detail)"
     x-on:keydown.escape.window="if (open) close()"
     x-show="open" x-cloak
     class="fixed inset-0 z-[70] flex items-end sm:items-center justify-center"
     role="dialog" aria-modal="true">

    <div class="absolute inset-0 bg-black/50" x-on:click="close()"
         x-transition:enter="transition ease-out duration-150"
         x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"></div>

    <div class="relative w-full sm:max-w-sm bg-white dark:bg-[#2a2a2a] rounded-t-2xl sm:rounded-2xl shadow-2xl p-5"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="translate-y-full sm:translate-y-4 sm:opacity-0"
         x-transition:enter-end="translate-y-0 opacity-100">

        <div class="flex items-start gap-3">
            <div class="flex-shrink-0 w-10 h-10 rounded-full bg-red-50 dark:bg-red-500/10 flex items-center justify-center text-red-500">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/></svg>
            </div>
            <div class="flex-1 min-w-0">
                <h2 class="text-base font-bold text-[#373737] dark:text-white" x-text="title"></h2>
                <p class="mt-1 text-sm text-[#878787] dark:text-white/70" x-text="message"></p>
            </div>
        </div>

        <div class="flex gap-2 mt-5">
            <button type="button" data-no-spinner="true" x-on:click="close()"
                class="flex-1 min-h-[44px] rounded-xl border border-[#ababab]/40 text-sm font-semibold text-[#878787] hover:bg-[#efeded] dark:hover:bg-white/5 transition-colors">
                Cancelar
            </button>
            <button type="button" data-no-spinner="true" x-ref="accept" x-on:click="accept()"
                class="flex-1 min-h-[44px] rounded-xl bg-red-500 hover:bg-red-600 text-sm font-semibold text-white transition-colors"
                x-text="label"></button>
        </div>
    </div>
</div>
