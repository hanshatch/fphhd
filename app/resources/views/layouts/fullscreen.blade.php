<!DOCTYPE html>
<html lang="es" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title }} · Finanzas</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full overflow-hidden" style="background:#111">

    {{ $slot }}

    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>

    <script>
    (function () {
        const SPINNER_SVG = `<svg class="inline-block animate-spin w-4 h-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" aria-hidden="true">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 14 6.373 14 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"/>
        </svg>`;

        document.addEventListener('submit', function (e) {
            const btn = e.submitter || e.target.querySelector('[type="submit"]');
            if (!btn || btn.dataset.noSpinner === 'true') return;
            btn.disabled = true;
            btn.dataset.originalHtml = btn.innerHTML;
            btn.innerHTML = `${SPINNER_SVG}<span class="ml-1.5">Procesando…</span>`;
            setTimeout(() => {
                if (btn.dataset.originalHtml) {
                    btn.disabled = false;
                    btn.innerHTML = btn.dataset.originalHtml;
                    delete btn.dataset.originalHtml;
                }
            }, 15000);
        });
    }());
    </script>
</body>
</html>
