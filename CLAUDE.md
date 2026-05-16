# CLAUDE.md — Instrucciones permanentes para Claude Code

> Este archivo lo lee Claude Code automáticamente en cada sesión.
> No lo borres, no lo muevas. Si necesitas ajustarlo, hazlo con commit explícito.

---

## 1. Identidad del proyecto

**Nombre:** FP (Finanzas Personales)
**Dueño / único usuario:** Hans Hatch (`hans@hatch.mx`)
**Dominio producción:** `fp.hanshatch.com`
**Repositorio:** `fp-hanshatch`
**Idioma de interfaz:** español (México)
**Moneda:** MXN (sin multi-divisa por ahora)
**Tipo de app:** webapp single-user, mobile-first, accesible desde celular y desktop

---

## 2. Perfil del usuario (contexto financiero)

Hans tiene flujo de caja complejo:

- **Ingresos múltiples:**
  - Agencia de marketing (su negocio principal).
  - Clases en varias universidades.
  - Capacitaciones puntuales.
- **Cuentas bancarias:**
  - Banamex (operativa principal).
  - MercadoPago.
  - Nu (caja de ahorro hasta ~13% APR).
  - Revolut (caja de ahorro).
- **Tarjetas de crédito:** rota dinero entre cuentas para liquidar al corte y evitar intereses.
- **Inversiones:** usa las cajas de ahorro de Nu/Revolut/MercadoPago como vehículo corto plazo y necesita comparar rendimientos entre ellas.

Esto define decisiones clave de diseño: transferencias internas NO son gasto, intereses ganados se reportan separados del ingreso operativo, las TDC necesitan vista propia con corte/pago.

---

## 3. Principios no negociables

### 3.1 Dinero
- **NUNCA** usar `float` / `Number` para montos. Siempre:
  - Python: `decimal.Decimal` con contexto explícito (28 dígitos, `ROUND_HALF_EVEN`).
  - PostgreSQL: `NUMERIC(14,2)` (hasta ~999,999,999,999.99).
  - TypeScript: string en API, parsear con librería precisa (`decimal.js`) si se hacen cálculos en cliente. La regla por defecto: cálculos en backend, cliente solo formatea.
- Formateo en UI: `Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' })`.
- Inputs: aceptar `1,234.56`, `1234.56`, `.50`. Parsear robusto.

### 3.2 Transferencias internas
- Tipo `TRANSFER` en `transactions` con `account_id` (origen) y `counterparty_account_id` (destino).
- **NO** se cuentan como ingreso ni egreso en ningún reporte.
- Sí afectan saldo de las cuentas involucradas.

### 3.3 Intereses
- Tipo `INTEREST` separado de `INCOME`.
- En reportes de "ingresos por fuente" NO aparecen.
- En "rendimientos por cuenta" sí, con cálculo de APR efectivo.

### 3.4 Mobile-first
- Diseñar primero para viewport 375px (iPhone SE/13 mini). Luego escalar a tablet/desktop.
- Botones táctiles mínimo 44x44 px.
- Inputs numéricos con `inputMode="decimal"`.
- Capturar una transacción debe tomar < 10 segundos en mobile.
- FAB (botón flotante "+") siempre visible para captura rápida.

### 3.5 Seguridad
- Argon2id para passwords (passlib).
- JWT en cookie `HttpOnly; Secure; SameSite=Strict`. Nada de tokens en localStorage.
- TOTP obligatorio tras primer login (pyotp).
- 5 intentos fallidos → bloqueo 15 min + audit log.
- Headers de seguridad en Traefik: HSTS, CSP estricta, X-Frame-Options DENY, Referrer-Policy strict-origin.
- Toda acción sensible (login, password change, TOTP enable/disable, data export) se registra en `audit_log`.

### 3.6 Integridad de datos
- Migraciones SIEMPRE versionadas con Alembic. **Nunca** SQL ad-hoc en producción.
- Toda lógica financiera (cálculo de balance, APR efectivo, proyecciones, indicadores) tiene tests unitarios obligatorios.
- Backups diarios cifrados con GPG, retención local 7 días, remota (Google Drive vía rclone) 30 días.
- Restore probado al menos una vez por trimestre.

### 3.7 Convivencia con n8n
- El VPS ya corre n8n en producción.
- **NO romper n8n bajo ninguna circunstancia.**
- Reverse proxy compartido (Traefik) con red Docker `traefik-public`.
- Antes de cada deploy a producción, snapshot del VPS o al menos backup de Postgres.

---

## 4. Stack técnico

### Backend
- Python 3.12
- FastAPI + uvicorn
- SQLAlchemy 2.x (estilo declarativo con `Mapped`/`mapped_column`)
- Alembic
- Pydantic v2 + pydantic-settings
- passlib[argon2] para passwords
- python-jose[cryptography] para JWT
- pyotp para TOTP
- APScheduler para tareas programadas (recurrentes, alertas, backups internos)
- structlog para logs estructurados
- pytest + pytest-asyncio + pytest-cov

### Frontend
- Vite + React 18 + TypeScript (strict mode)
- TailwindCSS
- shadcn/ui (componentes accesibles, copiados al repo)
- TanStack Query (server state)
- Zustand (UI state mínima)
- React Hook Form + Zod
- Recharts (gráficas)
- date-fns con locale `es`
- axios con interceptores y `withCredentials: true`
- Vitest + @testing-library/react

### Infraestructura
- Docker + Docker Compose v2
- Traefik v3 (reverse proxy + Let's Encrypt automático)
- PostgreSQL 16
- Ansible (idempotente) para provisionar el VPS
- GitHub Actions para CI

---

## 5. Convenciones de código

### Python
- Formateo: **black** (line length 100).
- Linter: **ruff** (config en `pyproject.toml`).
- Tipado: **mypy** strict en `app/`.
- Naming: `snake_case` para vars/funciones, `PascalCase` para clases, `UPPER_SNAKE` para constantes.
- Imports ordenados con ruff (isort).
- Docstrings en funciones públicas con formato Google.
- Sin `print()` — usar `logger`.

### TypeScript / React
- Formateo: **prettier** (semi: true, single quote, trailing comma).
- Linter: **eslint** con `@typescript-eslint/recommended-type-checked`.
- Componentes en `PascalCase.tsx`, hooks en `useFoo.ts`, helpers en `camelCase.ts`.
- Preferir composición sobre props gigantes.
- Sin `any`. Si es inevitable, comentar por qué.
- Estado de servidor SIEMPRE en TanStack Query, no en Zustand.

### SQL / Migraciones
- Nombres de tabla en plural y `snake_case` (`transactions`, `credit_cards`).
- Columnas en `snake_case`.
- Llaves primarias `id` UUID (gen_random_uuid()).
- Timestamps `created_at`, `updated_at` (con default `now()`).
- Cada migración con descripción clara, revisar SQL generado por autogenerate antes de aplicar.

### Git
- Conventional Commits obligatorio: `feat:`, `fix:`, `refactor:`, `test:`, `docs:`, `chore:`, `style:`.
- Scope opcional: `feat(transactions): add bulk endpoint`.
- Mensajes en español o inglés (consistencia por commit).
- 1 commit por fase completada como mínimo. Subdivide si la fase tiene cambios independientes.

---

## 6. Estructura de carpetas

```
fp-hanshatch/
├── backend/
│   ├── app/
│   │   ├── main.py            # entry FastAPI
│   │   ├── core/              # config, security, db, deps
│   │   ├── models/            # SQLAlchemy
│   │   ├── schemas/           # Pydantic
│   │   ├── api/               # routers (1 archivo por recurso)
│   │   ├── services/          # lógica de negocio (sin acoplar a HTTP)
│   │   └── workers/           # APScheduler jobs
│   ├── alembic/
│   ├── tests/
│   ├── pyproject.toml
│   ├── Dockerfile
│   └── .env.example
├── frontend/
│   ├── src/
│   │   ├── components/        # UI reutilizable
│   │   ├── pages/             # 1 archivo por ruta
│   │   ├── hooks/             # custom hooks
│   │   ├── lib/               # api client, helpers, formatters
│   │   ├── types/             # types compartidos
│   │   └── App.tsx
│   ├── package.json
│   └── ...
├── deploy/
│   ├── docker-compose.dev.yml
│   ├── docker-compose.prod.yml
│   ├── traefik/
│   ├── ansible/
│   └── scripts/
├── docs/
│   ├── ARCHITECTURE.md
│   ├── DATABASE.md
│   ├── DEPLOYMENT.md
│   └── RUNBOOK.md
├── CLAUDE.md                  # ← este archivo
├── PROGRESS.md
├── README.md
└── .gitignore
```

---

## 7. Glosario financiero del dominio

Usa estos términos consistentemente en código, comentarios, UI y documentación.

| Término | Significado |
|---|---|
| **Cuenta (Account)** | Cualquier vehículo de dinero: cuenta bancaria débito, tarjeta de crédito, caja de ahorro, efectivo, cuenta de inversión. |
| **TDC** | Tarjeta de crédito. Cuenta tipo `CREDIT`. |
| **Caja de ahorro** | Cuenta tipo `SAVINGS` o `INVESTMENT` que genera intereses (Nu, Revolut, MercadoPago). |
| **Fuente (Source)** | Origen del ingreso operativo: agencia, universidad, capacitación, otro. NO aplica a intereses. |
| **Categoría** | Clasificación de ingreso o egreso. Jerárquica (categoría > subcategoría). |
| **Transacción** | Cualquier movimiento: ingreso, egreso, transferencia interna, interés ganado, comisión. |
| **Transferencia interna** | Movimiento entre dos cuentas propias. No afecta totales de ingreso/egreso. |
| **Corte (Statement)** | Fecha mensual en que la TDC cierra el periodo y emite estado de cuenta. |
| **Pago (Payment date)** | Fecha límite para liquidar la TDC sin intereses. |
| **APR nominal** | Tasa anual estimada que la cuenta promete (campo `invest_apr`). |
| **APR efectivo** | Tasa real calculada con intereses observados sobre saldo promedio del periodo. |
| **Patrimonio neto (Net Worth)** | Activos (saldos positivos) - Pasivos (saldos de TDC). |
| **Tasa de ahorro** | (Ingresos - Egresos) / Ingresos del periodo. |
| **Fondo de emergencia** | Meses de egresos promedio cubiertos por saldos líquidos. Meta: 3-6 meses. |
| **Razón de endeudamiento** | Pasivos / Activos. Meta: < 30%. |
| **Recurrente** | Regla de transacción programada (rrule RFC 5545) que genera transacciones automáticamente. |
| **Presupuesto (Budget)** | Límite de gasto definido por categoría y periodo. |
| **Meta (Goal)** | Objetivo de ahorro con monto y fecha objetivo opcional. |

---

## 8. Reglas de UX

- **Idioma:** todo en español de México. Evitar anglicismos cuando hay traducción común ("dashboard" → "panel" en headers, "balance" → "saldo").
- **Números:** siempre con formato `$1,234.56 MXN`. Negativos en rojo, positivos en verde, neutros (transferencias) en gris.
- **Fechas:** formato corto `15 may 2026` en listas, formato largo `viernes, 15 de mayo de 2026` en encabezados de detalle.
- **Empty states:** siempre con CTA claro y dibujo/ícono. Nunca solo "No hay datos".
- **Confirmaciones destructivas:** modal con texto exacto del recurso a eliminar.
- **Toasts:** 3 segundos default, 5 segundos para acciones con "Deshacer".
- **Accesibilidad:** WCAG AA mínimo. Todos los inputs con `<label>`, contraste 4.5:1, navegación por teclado.

---

## 9. Reglas de testing

- Antes de marcar una fase como completada, **todos los tests deben pasar**.
- Cobertura mínima en `backend/app/services/`: 80%.
- Tests obligatorios para:
  - Cálculo de saldo de cuenta en todos los tipos.
  - Cálculo de APR efectivo.
  - Materialización de reglas recurrentes (especialmente fechas borde: día 31 en febrero).
  - Generación de fechas de corte/pago de TDC.
  - Proyección de flujo de caja.
  - Indicadores financieros con casos conocidos.
  - Reglas de autenticación (bloqueo, TOTP, rate limit).
- Tests E2E (Playwright) en Fase 12 cubriendo flujos críticos.

---

## 10. Flujo de trabajo en cada sesión

Cuando inicies sesión con Claude Code:

1. Lee `PROGRESS.md` para saber dónde quedó el proyecto.
2. Confirma con el usuario qué fase trabajará hoy.
3. Antes de empezar:
   - `git status` limpio o ramas claras.
   - `git pull` si aplica.
4. Trabaja en commits pequeños y descriptivos.
5. Antes de terminar:
   - Corre tests (`pytest`, `vitest`).
   - Corre linters (`ruff`, `eslint`).
   - Actualiza `PROGRESS.md` con avance.
   - Commit final claro.
6. Reporta al usuario qué quedó hecho y qué sigue.

---

## 11. Lo que NO debes hacer

- ❌ No instalar dependencias extra sin justificarlo.
- ❌ No introducir tracking, analytics ni servicios externos sin permiso (es app personal de un solo usuario).
- ❌ No conectar APIs bancarias (Belvo, Plaid) — captura manual por decisión explícita.
- ❌ No agregar multi-usuario, multi-tenancy ni roles. Single-user.
- ❌ No usar OCR, IA o ML sin que el usuario lo pida explícitamente.
- ❌ No subir secretos al repo (.env, keys, tokens). `.env.example` sí, `.env` no.
- ❌ No tocar la configuración de n8n en el VPS.
- ❌ No abrir puertos en UFW más allá de 22, 80, 443 sin razón documentada.
- ❌ No usar `float`/`Number` para dinero.
- ❌ No commit con tests rojos.

---

## 12. Contacto y autoridad

- **Owner del proyecto:** Hans Hatch.
- **Decisiones de producto:** siempre confirmar con Hans antes de cambios de alcance.
- **Decisiones técnicas dentro del stack acordado:** Claude Code puede tomar criterio si están alineadas con este documento. Si hay ambigüedad, **preguntar**.

---

**Fin del documento.**
