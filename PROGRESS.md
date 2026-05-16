# PROGRESS

## Fases completadas

- [x] Fase 0 — Bootstrap del proyecto (2026-05-15)
- [ ] Fase 1 — Modelo de datos y migraciones
- [ ] Fase 2 — Autenticación y seguridad
- [ ] Fase 3 — CRUD core (cuentas, categorías, fuentes, transacciones)
- [ ] Fase 4 — Frontend base + auth
- [ ] Fase 5 — Captura rápida + listado de movimientos
- [ ] Fase 6 — Dashboard + saldos por cuenta
- [ ] Fase 7 — Reportes (fuentes, patrimonio, inversiones)
- [ ] Fase 8 — Tarjetas de crédito + alertas
- [ ] Fase 9 — Recurrentes, presupuestos y proyecciones
- [ ] Fase 10 — Metas, indicadores financieros y simulador
- [ ] Fase 11 — Deployment a producción

## En curso

Fase 1 — Modelo de datos y migraciones

## Bloqueos / decisiones pendientes

(ninguno por ahora)

## Notas técnicas relevantes

- Se usa Traefik v3 como reverse proxy compartido con n8n (red Docker `traefik-public`).
- Montos siempre en `NUMERIC(14,2)` en Postgres y `Decimal` en Python. Nunca `float`.
- Frontend usa Vite + React 18 + TypeScript strict + Tailwind + shadcn/ui (tema indigo).
- Backend usa uv como gestor de paquetes Python.
