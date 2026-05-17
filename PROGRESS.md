# PROGRESS

## Fases completadas

- [x] Fase 0 — Bootstrap del proyecto (2026-05-16)
- [x] Fase 1 — Modelo de datos y migraciones (2026-05-16)
- [x] Fase 2 — Autenticación + 2FA (2026-05-16)
- [x] Fase 3 — CRUD cuentas, categorías, fuentes, transacciones (2026-05-16)
- [x] Fase 4 — Dashboard + saldos (2026-05-16)
- [ ] Fase 5 — Captura rápida de movimientos
- [ ] Fase 6 — Reportes (fuentes de ingreso, egresos, patrimonio)
- [ ] Fase 7 — Tarjetas de crédito + alertas
- [ ] Fase 8 — Recurrentes, presupuestos y proyecciones
- [ ] Fase 9 — Indicadores financieros, metas y simulador
- [ ] Fase 10 — Deploy a producción

## En curso

Fase 5 — Captura rápida de movimientos (optimización mobile)

## Bloqueos / decisiones pendientes

- Pendiente configurar credenciales de DB en producción (hosting SSH)

## Decisiones de producto

- **Importación eliminada**: La función de importar estados de cuenta se desarrolló y se eliminó.
  Razón: el Excel del banco no trae suficiente detalle y al cabo de un mes no se recuerdan los movimientos.
  Decisión: captura manual en tiempo real al momento de cada pago/movimiento.

## Notas técnicas relevantes

- Stack cambiado de Python/FastAPI/React a Laravel 13 + Blade + Tailwind v4.
  Razón: Hans domina PHP, tiene hosting con SSH, stack más simple para proyecto personal.
- Dev: Valet en https://fp.test, MySQL local `fp_local`, password `hanshatch`
- Tailwind v4 ya incluido en Laravel 13 (via @tailwindcss/vite plugin).
- Breeze instalado con template Blade + dark mode.
- Migraciones base de Laravel ya corridas (users, cache, jobs).
