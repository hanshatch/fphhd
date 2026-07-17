# PROGRESS

## Fases completadas

- [x] Fase 0 — Bootstrap del proyecto (2026-05-16)
- [x] Fase 1 — Modelo de datos y migraciones (2026-05-16)
- [x] Fase 2 — Autenticación + 2FA (2026-05-16)
- [x] Fase 3 — CRUD cuentas, categorías, fuentes, transacciones (2026-05-16)
- [x] Fase 4 — Dashboard + saldos (2026-05-16)
- [x] Fase 5 — Captura rápida de movimientos (formulario estilo MoneyWiz, duplicar movimiento, ajuste de saldo) (2026-05-19)
- [x] Fase 6 — Reportes (3 vistas con Chart.js) (2026-05-19)
- [x] Fase 7 — Tarjetas de crédito + alertas TDC en dashboard (2026-05-20)
- [x] Fase 8 — Recurrentes (cargos + MSI), presupuestos por categoría, calendario de flujo de caja (2026-05-19)
- [~] Fase 9 — Indicadores financieros en dashboard (2026-05-20). **Pendiente: metas y simulador.**
- [x] Fase 10 — Deploy a producción (fp.hanshatch.com responde; public/build en repo) (2026-05-20)

## Módulos adicionales completados

- [x] Cargos recurrentes + MSI (2026-05-17)
- [x] Planeación de ingresos variables (income-plans) con frecuencia "Único" (2026-05-18)
- [x] Vista de cuentas agrupada por tipo estilo MoneyWiz + saldo corrido (2026-05-19)
- [x] Módulo de rendimientos sofipos (2026-07-16): pestaña "Rendimientos" en Reportes con
  interés mensual por cuenta (gráfica apilada), APR efectivo (interés ÷ saldo promedio,
  anualizado) vs APR nominal, y alerta de captura pendiente en dashboard cuando una cuenta
  savings/investment no tiene transacción `interest` desde el inicio del mes anterior.
  `YieldService` + `YieldReportTest`. Captura manual: el rendimiento real se registra como
  transacción tipo interés al recibir el abono de cada sofipo (Klar, Nu, Revolut, MercadoPago).

## Corrección integral (2026-07-16) — ver 02-plan-correccion.md

- [x] Fase A — Seguridad: bypass TOTP cerrado, secret cifrado, throttle login/TOTP, .env.example endurecido
- [x] Fase B — Finanzas: fix crítico de saldos (whereIn acumulado), interés fuera de ingreso operativo, transferencias íntegras, ajuste TDC
- [x] Fase C — parse_money/format_currency/bcsum; montos con coma aceptados
- [x] Fase D — ReportService/ScheduledService/BudgetService; saldos en 2 queries (antes 4×cuenta)
- [x] Fase E — Política de contraseñas 12+, audit log completo, frecuencia 'once', quincenas
- Suite de tests: 31 → 50 (FinanceRulesTest, TotpTest, PagesSmokeTest)

- [x] Bot de Telegram para captura de gastos (2026-07-17): webhook `POST /telegram/webhook`
  validado con secret token + chat_id único (config en `services.telegram`, vars TELEGRAM_* en .env).
  Flujo: "250 tacos" → parsea monto con `parse_money`, adivina categoría de gasto por nombre en la
  descripción, pregunta cuenta (y categoría si no adivinó) con botones inline, crea `Transaction`
  expense con fecha de hoy y registra en `audit_logs`. Pendiente en cache 30 min.
  `TelegramService` (cliente Bot API) + `TelegramExpenseService` + comando `php artisan telegram:webhook`
  (--info / --remove). Webhook activo en prod apuntando a fp.hanshatch.com; bot @FP_hatch_bot.
  Nota deploy: tras cambiar rutas/config en prod correr `php artisan config:cache && php artisan route:cache`
  (el 404 inicial fue por route cache viejo). Tests: `TelegramWebhookTest` (6).

- [x] Bot de Telegram — fechas y LLM (2026-07-17): el mensaje acepta fecha ("ayer", "antier",
  "15/07", "15/07/2026"; validada: real y no futura, default hoy) y la confirmación siempre la muestra.
  Fallback opcional con DeepSeek (`DeepSeekService`, config `services.deepseek`, var DEEPSEEK_API_KEY)
  para mensajes en lenguaje natural ("gasté 250 en tacos ayer"): extrae monto/descripción/fecha/categoría
  con JSON mode, montos como string (sin float), fecha sanitizada (no futura, máx 2 años atrás).
  Sin API key el fallback queda inactivo y el bot solo acepta el formato corto. Tests: 6 nuevos (12 total).

## En curso / siguiente

- Fase 9 restante: metas de ahorro y simulador.

## Deploy a producción (2026-07-16)

- Desplegado commit 1724491 en Hostinger (`~/domains/hanshatch.com/public_html/_fphhd`,
  SSH `ssh -p 65002 u863784331@191.96.54.156`, PHP 8.4).
- `.env` de prod corregido: APP_ENV=production, APP_DEBUG=false (¡estaba en local/true!),
  SESSION_ENCRYPT=true, SESSION_SECURE_COOKIE=true.
- Migración audit_logs corrida; config/route/view cacheados.
- Al primer login en prod el 2FA pedirá re-enrolarse (secret legado → auto-reset).

## Bloqueos / decisiones pendientes

- Decidir: exponer tipo `fee` en el form de movimientos o eliminarlo (hoy inaccesible por UI).
- Decidir: ¿netWorth debe incluir cuentas inactivas con saldo ≠ 0? (hoy las excluye).
- Producción: usuario de DB dedicado con privilegios mínimos (no root).

## Decisiones de producto

- **Presupuestos eliminado (2026-07-17)**: Hans no usa el módulo ni piensa usarlo. Se eliminó
  completo (modelo, servicio, controlador, vistas, rutas, indicador del dashboard, nav) y la
  tabla `budgets` se tiró con migración (estaba vacía en local y prod).

- **Importación eliminada**: La función de importar estados de cuenta se desarrolló y se eliminó.
  Razón: el Excel del banco no trae suficiente detalle y al cabo de un mes no se recuerdan los movimientos.
  Decisión: captura manual en tiempo real al momento de cada pago/movimiento.

## Notas técnicas relevantes

- Stack cambiado de Python/FastAPI/React a Laravel 13 + Blade + Tailwind v4.
  Razón: Hans domina PHP, tiene hosting con SSH, stack más simple para proyecto personal.
- Dev: Valet en https://fp.test, MySQL local `fp_local`. Credenciales de MySQL local en `~/.my.cnf` (root); el `.env` debe coincidir con esa contraseña.
- MySQL local actualizado a 9.6 (Homebrew, 2026-07). PHP CLI 8.5, Valet/FPM 8.4.
- Tailwind v4 ya incluido en Laravel 13 (via @tailwindcss/vite plugin).
- Breeze instalado con template Blade + dark mode.
- (2026-07-15) El proyecto vive en `~/Documents`, que iCloud Drive sincroniza con "Optimizar almacenamiento".
  iCloud evictó archivos de `vendor/` y `node_modules/` (lectura fallaba con errno=89) y colgaba `php artisan`.
  Se resolvió con `rm -rf vendor node_modules && composer install && npm ci`.
  **Recomendación**: excluir la carpeta de desarrollo de iCloud o desactivar "Optimizar almacenamiento del Mac".
