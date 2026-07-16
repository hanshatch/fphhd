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

## Corrección integral (2026-07-16) — ver 02-plan-correccion.md

- [x] Fase A — Seguridad: bypass TOTP cerrado, secret cifrado, throttle login/TOTP, .env.example endurecido
- [x] Fase B — Finanzas: fix crítico de saldos (whereIn acumulado), interés fuera de ingreso operativo, transferencias íntegras, ajuste TDC
- [x] Fase C — parse_money/format_currency/bcsum; montos con coma aceptados
- [x] Fase D — ReportService/ScheduledService/BudgetService; saldos en 2 queries (antes 4×cuenta)
- [x] Fase E — Política de contraseñas 12+, audit log completo, frecuencia 'once', quincenas
- Suite de tests: 31 → 50 (FinanceRulesTest, TotpTest, PagesSmokeTest)

## En curso / siguiente

- Fase 9 restante: metas de ahorro y simulador.
- Desplegar a producción las correcciones (incluye `php artisan migrate` por audit_logs
  y re-enrolar 2FA: el secret ahora se guarda cifrado, los secrets viejos en claro no descifran).
- En producción verificar: APP_DEBUG=false, APP_ENV=production, SESSION_SECURE_COOKIE=true.

## Bloqueos / decisiones pendientes

- Decidir: exponer tipo `fee` en el form de movimientos o eliminarlo (hoy inaccesible por UI).
- Decidir: ¿netWorth debe incluir cuentas inactivas con saldo ≠ 0? (hoy las excluye).
- Producción: usuario de DB dedicado con privilegios mínimos (no root).

## Decisiones de producto

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
