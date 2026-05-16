# Plan de Trabajo — FP (Finanzas Personales) con Claude Code

> **Proyecto:** Webapp personal de finanzas para Hans Hatch
> **Dominio:** fp.hanshatch.com
> **Repositorio sugerido:** `fp-hanshatch`
> **Fecha de inicio:** 2026-05-15
> **Versión del plan:** 1.0

---

## Cómo usar este documento con Claude Code

1. Crea una carpeta vacía en tu máquina local: `mkdir fp-hanshatch && cd fp-hanshatch`.
2. Inicia Claude Code dentro de esa carpeta: `claude`.
3. Pégale el bloque "**Contexto del proyecto**" al inicio de la sesión.
4. Avanza fase por fase, copiando el prompt de cada fase como tarea. **No saltes fases.**
5. Al terminar cada fase, pídele a Claude Code que cree un commit descriptivo y actualice `PROGRESS.md` con lo completado.
6. Si abres una nueva sesión de Claude Code, vuelve a pegar el "Contexto del proyecto" + el `PROGRESS.md` actual.

---

## Contexto del proyecto (pegar al inicio de cada sesión)

```
Soy Hans Hatch. Estoy construyendo una webapp personal de finanzas llamada FP
(Finanzas Personales) en el dominio fp.hanshatch.com.

PERFIL DE USUARIO:
- Dueño de agencia de marketing + docente universitario + capacitador.
- Múltiples fuentes de ingreso: agencia, varias universidades, capacitaciones.
- Cuentas bancarias: Banamex, MercadoPago, Nu, Revolut.
- Usa cajas de ahorro de Nu/Revolut/MercadoPago como inversión corto plazo (hasta 13% APR).
- Rota dinero entre cuentas para pagar TDC y evitar intereses.

REQUISITOS FUNCIONALES:
- Single-user (solo yo). Login con usuario + contraseña + 2FA (TOTP).
- Captura manual de movimientos vía formulario web (sin API bancaria, sin OCR).
- Categorías jerárquicas (categoría + subcategoría).
- Inversiones: saldo por cuenta, rendimientos/intereses, transferencias internas
  trazables (NO contadas como ingreso/egreso), comparativo de rendimientos.
- Solo MXN (sin multi-divisa).
- Reportes: flujo de caja proyectado, ingresos por fuente, alertas de TDC
  (corte/pago), patrimonio neto, presupuestos por categoría.
- Módulo asesoría: tasa de ahorro, endeudamiento, fondo de emergencia, metas,
  simulador de escenarios.

STACK TÉCNICO:
- Backend: Python 3.12 + FastAPI + SQLAlchemy 2.x + Alembic + Pydantic v2.
- DB: PostgreSQL 16 (montos en NUMERIC(14,2), NUNCA FLOAT para dinero).
- Frontend: React + Vite + TypeScript + TailwindCSS + shadcn/ui + Recharts.
- Auth: JWT en cookie HttpOnly + Argon2id (passwords) + pyotp (TOTP).
- Infra: Docker Compose + Traefik v3 (reverse proxy compartido con n8n existente).
- HTTPS: Let's Encrypt vía Traefik.
- Backups: pg_dump + GPG + rclone → Google Drive (diario).

INFRAESTRUCTURA:
- VPS Hostinger: 31.220.51.151 (Phoenix, US), Ubuntu 24.04 LTS, KVM 2 (2 vCPU, 8 GB RAM, 100 GB disco).
- DNS fp.hanshatch.com ya apunta a la IP.
- n8n ya está corriendo en el VPS (mantenerlo intacto).
- Docker ya instalado.
- Acceso SSH por llave (no contraseña).

PRINCIPIOS NO NEGOCIABLES:
1. NUNCA usar FLOAT/float para dinero. Siempre Decimal (Python) y NUMERIC (Postgres).
2. Mobile-first SIEMPRE. Diseñar primero para iPhone, luego adaptar.
3. Transferencias internas NO inflan ingresos/egresos en reportes.
4. Intereses ganados se reportan separados del ingreso operativo.
5. Todo en español (UI, mensajes de error, commits y comentarios pueden ser en español o inglés según convención del lenguaje).
6. Tests unitarios para toda lógica financiera (cálculos, proyecciones, indicadores).
7. Migraciones de DB SIEMPRE versionadas con Alembic. Nunca SQL ad-hoc en producción.
8. No romper n8n bajo ninguna circunstancia.

ENTORNOS:
- dev: local en mi máquina con docker-compose.dev.yml.
- prod: VPS Hostinger con docker-compose.prod.yml + Traefik.
```

---

## Estructura del repositorio

```
fp-hanshatch/
├── backend/
│   ├── app/
│   │   ├── main.py
│   │   ├── core/         # config, security, db, deps
│   │   ├── models/       # SQLAlchemy
│   │   ├── schemas/      # Pydantic
│   │   ├── api/          # routers FastAPI
│   │   ├── services/     # lógica de negocio
│   │   └── workers/      # tareas scheduled (APScheduler)
│   ├── alembic/
│   ├── tests/
│   ├── pyproject.toml
│   ├── Dockerfile
│   └── .env.example
├── frontend/
│   ├── src/
│   │   ├── components/
│   │   ├── pages/
│   │   ├── hooks/
│   │   ├── lib/
│   │   └── App.tsx
│   ├── package.json
│   ├── vite.config.ts
│   ├── tailwind.config.ts
│   ├── Dockerfile
│   └── .env.example
├── deploy/
│   ├── docker-compose.dev.yml
│   ├── docker-compose.prod.yml
│   ├── traefik/
│   │   ├── traefik.yml
│   │   └── dynamic.yml
│   ├── ansible/
│   │   ├── playbook.yml
│   │   ├── inventory.yml
│   │   └── roles/
│   └── scripts/
│       ├── backup.sh
│       ├── restore.sh
│       └── deploy.sh
├── docs/
│   ├── ARCHITECTURE.md
│   ├── DATABASE.md
│   └── DEPLOYMENT.md
├── .gitignore
├── README.md
├── PROGRESS.md
└── CLAUDE.md             # Instrucciones permanentes para Claude Code
```

---

# FASE 0 — Bootstrap del proyecto

**Objetivo:** Tener repo inicial, estructura de carpetas, CLAUDE.md, .gitignore, README, PROGRESS.md y herramientas de calidad de código.

## Prompt para Claude Code:

```
Vamos a iniciar el proyecto FP. Crea la estructura completa de carpetas según
lo definido en el plan, e incluye:

1. README.md con descripción del proyecto, stack y cómo arrancar en dev.
2. CLAUDE.md con todos los principios no negociables del plan + convenciones
   de código (snake_case Python, camelCase TS, etc.).
3. PROGRESS.md vacío con sección "Fases completadas" y "En curso".
4. .gitignore completo (Python, Node, IDEs, .env, etc.).
5. backend/pyproject.toml con dependencias base: fastapi, uvicorn[standard],
   sqlalchemy[asyncio], asyncpg, alembic, pydantic, pydantic-settings,
   passlib[argon2], pyotp, python-jose[cryptography], python-multipart,
   apscheduler, httpx. Dev: pytest, pytest-asyncio, pytest-cov, ruff, mypy,
   black, faker.
6. frontend/package.json con: react, react-dom, react-router-dom, axios,
   @tanstack/react-query, zustand, recharts, react-hook-form, zod,
   @hookform/resolvers, date-fns, lucide-react. Dev: vite, typescript,
   tailwindcss, autoprefixer, postcss, eslint, prettier, vitest,
   @testing-library/react.
7. frontend/ inicializado con Vite (template react-ts), Tailwind configurado
   y shadcn/ui instalado con tema base (color primario violeta indigo, modo
   claro y oscuro).
8. backend/.env.example y frontend/.env.example con todas las variables
   necesarias (sin valores reales).
9. Inicia repo git, primer commit "chore: bootstrap project structure".

Al terminar, actualiza PROGRESS.md marcando Fase 0 como completada con la
fecha. NO avances a la siguiente fase.
```

**Criterios de aceptación:**
- `cd backend && uv sync` (o pip install) funciona sin errores.
- `cd frontend && npm install && npm run dev` levanta Vite en localhost.
- `git log` muestra el commit inicial.
- PROGRESS.md actualizado.

---

# FASE 1 — Modelo de datos y migraciones

**Objetivo:** Esquema completo de PostgreSQL con migraciones Alembic.

## Prompt para Claude Code:

```
Implementa el modelo de datos completo en backend/app/models/ usando
SQLAlchemy 2.x (estilo declarativo moderno con Mapped/mapped_column).

TABLAS A CREAR:

1. users
   - id (UUID PK), email (unique), password_hash, totp_secret (nullable),
     totp_enabled (bool, default false), is_active, created_at, last_login_at,
     failed_login_attempts (int, default 0), locked_until (nullable timestamp).

2. accounts
   - id (UUID PK), name, type (enum: DEBIT, CREDIT, SAVINGS, INVESTMENT, CASH),
     institution (enum: BANAMEX, MERCADOPAGO, NU, REVOLUT, OTHER),
     currency (default 'MXN'), initial_balance (NUMERIC(14,2)),
     is_active, color (hex), icon, invest_apr (NUMERIC(5,2) nullable),
     notes, created_at.

3. categories
   - id (UUID PK), parent_id (FK self, nullable), name,
     kind (enum: INCOME, EXPENSE), color, icon, is_archived,
     UNIQUE(parent_id, name, kind).

4. sources
   - id (UUID PK), name, kind (enum: AGENCY, UNIVERSITY, TRAINING, OTHER),
     notes, is_archived.

5. transactions
   - id (UUID PK), date (date), type (enum: INCOME, EXPENSE, TRANSFER,
     INTEREST, FEE), amount (NUMERIC(14,2), CHECK > 0), account_id (FK),
     category_id (FK nullable), source_id (FK nullable),
     counterparty_account_id (FK accounts, nullable, solo para TRANSFER),
     description, tags (ARRAY text), created_at, updated_at.
   - CONSTRAINT: si type=TRANSFER entonces counterparty_account_id NOT NULL
     y category_id NULL.
   - CONSTRAINT: si type=INCOME entonces source_id NOT NULL.

6. credit_cards
   - id (UUID PK), account_id (FK unique), statement_day (1-31),
     payment_day (1-31), credit_limit (NUMERIC(14,2)), apr (NUMERIC(5,2)),
     min_payment_pct (NUMERIC(5,2)).

7. recurring_rules
   - id (UUID PK), name, type (enum INCOME|EXPENSE), amount, account_id,
     category_id, source_id, rrule (text, RFC 5545), next_date,
     end_date (nullable), is_active, created_at.

8. budgets
   - id (UUID PK), category_id (FK), period (enum MONTHLY|WEEKLY|YEARLY),
     amount, start_date, end_date (nullable), is_active.

9. goals
   - id (UUID PK), name, description, target_amount, current_amount
     (default 0), target_date (nullable), account_id (FK nullable),
     status (enum ACTIVE|COMPLETED|PAUSED|CANCELLED), priority (1-5),
     created_at.

10. investment_yields
    - id (UUID PK), account_id (FK), period_start, period_end,
      amount_earned, apr_effective (NUMERIC(5,2)), notes, created_at.

11. audit_log
    - id (UUID PK), user_id (FK nullable), action (enum: LOGIN, LOGIN_FAIL,
      LOGOUT, PASSWORD_CHANGE, TOTP_ENABLE, TOTP_DISABLE, DATA_EXPORT,
      ACCOUNT_LOCK, ACCOUNT_UNLOCK), ip (inet), user_agent, metadata
      (jsonb), created_at.

ÍNDICES A CREAR:
- transactions(date DESC, account_id)
- transactions(date DESC, type)
- transactions(source_id) WHERE source_id IS NOT NULL
- categories(parent_id)
- recurring_rules(next_date) WHERE is_active = true
- audit_log(user_id, created_at DESC)

CONFIGURACIÓN:
- Configura Alembic en backend/alembic/ apuntando a DATABASE_URL del .env.
- Genera la migración inicial: alembic revision --autogenerate -m "initial schema"
- Revisa la migración generada y ajústala si Alembic se equivocó (constraints
  CHECK, ENUM types).
- Agrega seeds opcionales en backend/app/db/seeds.py para categorías comunes
  (Comida, Transporte, Servicios, Honorarios Agencia, Honorarios Docencia,
  etc.) e instituciones.

TESTS:
- backend/tests/test_models.py con tests que verifiquen:
  - No se puede crear una transacción TRANSFER sin counterparty.
  - No se puede crear una transacción con amount <= 0.
  - Categorías permiten jerarquía padre-hijo.
  - Una cuenta tipo CREDIT debe tener credit_cards asociada.

Al terminar, commit "feat(db): initial schema with migrations" y actualiza
PROGRESS.md. NO avances a la siguiente fase.
```

**Criterios de aceptación:**
- `alembic upgrade head` corre sin errores en una BD limpia.
- `pytest backend/tests/test_models.py` pasa.
- El diagrama de relaciones está documentado en `docs/DATABASE.md`.

---

# FASE 2 — Autenticación y seguridad

**Objetivo:** Login con usuario/contraseña + 2FA + sesiones JWT en cookie segura.

## Prompt para Claude Code:

```
Implementa el módulo de autenticación completo.

ENDPOINTS (todos en backend/app/api/auth.py):

1. POST /api/auth/register
   - Solo permitido si NO existe ningún usuario en la BD (porque es single-user).
   - Valida fuerza de password: min 12 chars, al menos 1 mayúscula, 1 número, 1 símbolo.
   - Hashea con Argon2id (passlib).
   - Crea el usuario y devuelve URL/QR para enrolar TOTP.

2. POST /api/auth/login
   - Recibe email + password.
   - Si fallan 5 veces, bloquea cuenta por 15 min (audit log).
   - Si TOTP está habilitado, devuelve {"requires_totp": true, "challenge_token": "..."}.
   - Si no, devuelve cookie JWT (HttpOnly, Secure, SameSite=Strict, max-age 30 min).

3. POST /api/auth/totp/verify
   - Recibe challenge_token + código TOTP de 6 dígitos.
   - Si válido, emite cookie JWT.
   - Tolera ventana de ±1 step (30s).

4. POST /api/auth/totp/enable
   - Requiere sesión activa.
   - Genera totp_secret, devuelve QR (imagen base64) y secreto en texto.
   - El usuario debe verificar con un código antes de marcarlo enabled=true.

5. POST /api/auth/totp/confirm
   - Recibe código TOTP, verifica, marca totp_enabled=true.
   - Genera 10 códigos de recuperación (mostrar UNA SOLA VEZ).

6. POST /api/auth/logout
   - Elimina cookie.

7. GET /api/auth/me
   - Devuelve datos del usuario autenticado.

8. POST /api/auth/refresh
   - Renueva la cookie si está válida y a < 5 min de expirar.

UTILIDADES (backend/app/core/security.py):
- hash_password / verify_password (Argon2id).
- create_access_token / decode_access_token (JWT con HS256, secret de .env).
- get_current_user (dependency FastAPI que valida cookie).
- require_user (alias para endpoints protegidos).

RATE LIMITING:
- Usa slowapi en /api/auth/login y /api/auth/totp/verify (5 req/min por IP).

AUDIT LOG:
- Cada login (exitoso o fallido), logout, cambio de password, enable/disable
  TOTP debe registrarse en audit_log con IP y user_agent.

CONFIGURACIÓN:
- Variables de entorno: SECRET_KEY (generar con secrets.token_urlsafe(32)),
  ACCESS_TOKEN_EXPIRE_MINUTES, COOKIE_SECURE (true en prod, false en dev),
  COOKIE_DOMAIN.

TESTS (backend/tests/test_auth.py):
- Registro solo funciona la primera vez.
- Login con credenciales inválidas no revela si email existe.
- Bloqueo tras 5 intentos fallidos.
- Flujo completo TOTP (enable → confirm → login → verify).
- Cookie JWT no es accesible desde JS (HttpOnly).
- /api/auth/me requiere autenticación.

Al terminar, commit "feat(auth): login with password + TOTP 2FA" y actualiza
PROGRESS.md. NO avances a la siguiente fase.
```

**Criterios de aceptación:**
- Postman/curl pueden completar el flujo: register → enable TOTP → confirm → logout → login → verify TOTP → /me.
- Todos los tests pasan.
- Audit log se llena correctamente.

---

# FASE 3 — CRUD de cuentas, categorías, fuentes y transacciones

**Objetivo:** APIs REST completas para las entidades core.

## Prompt para Claude Code:

```
Implementa los routers CRUD en backend/app/api/ con sus respectivos schemas
Pydantic en backend/app/schemas/.

ENDPOINTS:

backend/app/api/accounts.py
- GET    /api/accounts            (lista con saldo calculado)
- GET    /api/accounts/{id}       (detalle + últimas 20 transacciones)
- POST   /api/accounts            (crear)
- PATCH  /api/accounts/{id}       (editar)
- DELETE /api/accounts/{id}       (soft delete: is_active=false, no físico si tiene transacciones)
- GET    /api/accounts/{id}/balance?as_of=YYYY-MM-DD  (saldo a fecha)

backend/app/api/categories.py
- GET    /api/categories?kind=INCOME|EXPENSE&include_archived=false
- GET    /api/categories/tree     (estructura jerárquica)
- POST   /api/categories
- PATCH  /api/categories/{id}
- DELETE /api/categories/{id}     (soft delete: is_archived=true)

backend/app/api/sources.py
- GET    /api/sources
- POST   /api/sources
- PATCH  /api/sources/{id}
- DELETE /api/sources/{id}

backend/app/api/transactions.py
- GET    /api/transactions        (con filtros: date_from, date_to, account_id,
                                   category_id, source_id, type, tags, search,
                                   paginado cursor-based)
- GET    /api/transactions/{id}
- POST   /api/transactions        (crear, valida constraints del modelo)
- POST   /api/transactions/transfer (helper: crea TRANSFER con 1 sola request)
- PATCH  /api/transactions/{id}
- DELETE /api/transactions/{id}   (delete físico, pero registra en audit_log)
- POST   /api/transactions/bulk   (crear varias en una sola request, transaccional)

SERVICIOS (backend/app/services/):
- accounts_service.compute_balance(account_id, as_of=None):
  - DEBIT/SAVINGS/INVESTMENT/CASH: initial_balance + INCOME + INTEREST + transfers_in - EXPENSE - FEE - transfers_out
  - CREDIT: initial_balance + EXPENSE + FEE + transfers_in_as_payment - payments
  - DEBE usar SELECT ... FOR UPDATE si va a actualizar, sino una sola query agregada.
  - Tests obligatorios con casos edge (cuenta sin movimientos, solo transferencias, etc.).

REGLAS DE NEGOCIO:
- No se puede eliminar una categoría que tiene transacciones (solo archivar).
- No se puede eliminar una fuente que tiene transacciones (solo archivar).
- No se puede transferir a la misma cuenta (account_id != counterparty_account_id).
- No se puede crear transacción con fecha futura > 1 año.

VALIDACIONES PYDANTIC:
- amount: condecimal(max_digits=14, decimal_places=2, gt=0).
- date: no más de 1 año en el futuro.
- description: max 500 chars.
- tags: max 10 tags, cada uno max 30 chars.

TESTS:
- tests/test_accounts.py: CRUD + cálculo de saldo en varios escenarios.
- tests/test_transactions.py: validaciones, transferencias, bulk.
- tests/test_categories.py: jerarquía, archivado.

Todos los endpoints requieren require_user (autenticación).

Al terminar, commit "feat(api): CRUD for accounts, categories, sources, transactions"
y actualiza PROGRESS.md. NO avances a la siguiente fase.
```

**Criterios de aceptación:**
- Docs en `/api/docs` (Swagger) muestran todos los endpoints.
- Tests pasan con cobertura > 80% en services/.
- Cálculo de balance es exacto al centavo.

---

# FASE 4 — Frontend base + autenticación

**Objetivo:** Login, layout principal, navegación, cliente API.

## Prompt para Claude Code:

```
Construye el frontend base en frontend/.

CONFIGURACIÓN:
- React Router v6 con rutas: /, /login, /dashboard, /transactions, /accounts,
  /reports, /budgets, /goals, /settings.
- Axios instance en src/lib/api.ts con interceptores:
  - withCredentials: true (para cookies).
  - 401 → redirige a /login.
- TanStack Query configurado con QueryClient y devtools en dev.
- Zustand store en src/lib/auth.ts para estado de usuario.

COMPONENTES (src/components/):
- Layout principal (AppShell) con:
  - Bottom navigation tabs en mobile (Inicio, Movimientos, +Crear, Reportes, Más).
  - Sidebar en desktop con los mismos items.
  - Botón flotante "+ Nuevo movimiento" en mobile (FAB).
  - Header con saludo + dark mode toggle + avatar/menú.
- ProtectedRoute (wrapper que verifica auth y redirige).
- LoadingScreen, ErrorBoundary.

PÁGINAS (src/pages/):
- LoginPage:
  - Form con email + password (react-hook-form + zod).
  - Si server responde requires_totp, muestra input de 6 dígitos.
  - Errores claros en español ("Credenciales inválidas", "Cuenta bloqueada", etc.).
  - Mobile-first: inputs grandes, botones full-width.

- RegisterPage:
  - Solo accesible si /api/auth/register devuelve OK.
  - Form similar al login + confirmación de password.
  - Tras registro exitoso, redirige a enrolamiento de TOTP.

- TotpEnrollPage:
  - Muestra QR + secreto.
  - Input para verificar código.
  - Tras confirmación, muestra los 10 códigos de recuperación con botón "Copiar"
    y advertencia de guardarlos.

- DashboardPage (placeholder por ahora):
  - Saludo personalizado.
  - "Próximamente" cards de saldos.

ESTILO:
- TailwindCSS con tema claro y oscuro.
- Color primario: indigo-600 (modo claro), indigo-400 (modo oscuro).
- Tipografía: Inter (Google Fonts).
- Numeración monetaria: Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' }).
- Fechas: date-fns con locale es.

HELPERS (src/lib/):
- formatCurrency(amount: number | string): string.
- formatDate(date: Date | string, formatStr?: string): string.
- parseCurrencyInput(input: string): string (acepta "1,234.56", "1234.56", devuelve string decimal).

TESTS:
- vitest + @testing-library/react.
- Tests para formatCurrency y parseCurrencyInput.
- Test de LoginPage: render, submit, error state.

Al terminar, commit "feat(frontend): auth flow + app shell" y actualiza
PROGRESS.md. NO avances a la siguiente fase.
```

**Criterios de aceptación:**
- `npm run dev` levanta la app y se puede hacer login real contra el backend en local.
- Flujo completo: register → enroll TOTP → logout → login → verify → dashboard.
- Mobile-first verificable en Chrome DevTools.

---

# FASE 5 — Captura rápida + listado de movimientos

**Objetivo:** Pantalla principal de captura optimizada para celular + tabla/lista de transacciones con filtros.

## Prompt para Claude Code:

```
Implementa las pantallas centrales de uso diario.

PÁGINA: NewTransactionPage (/transactions/new):
- Formulario optimizado para captura desde celular en menos de 10 segundos.
- Selector de tipo en la parte superior con 4 botones grandes: 💰 Ingreso,
  💸 Egreso, 🔄 Transferencia, 📈 Interés.
- Campo monto con teclado numérico (inputMode="decimal") y formato en vivo.
- Selector de cuenta (combobox con búsqueda).
- Si tipo = TRANSFER: aparece selector de cuenta destino.
- Si tipo != TRANSFER: aparece selector de categoría (autocomplete jerárquico).
- Si tipo = INCOME: aparece selector de fuente.
- Fecha (default hoy, date picker mobile-friendly).
- Descripción (opcional, max 500).
- Tags (chips, opcional).
- Botón "Guardar y cerrar" + botón "Guardar y crear otro".
- Después de guardar, toast de confirmación con monto + botón "Deshacer" (5s).

PLANTILLAS / RECIENTES:
- En la parte superior, mostrar 5 transacciones recientes del usuario como
  chips clickeables que precargan el form (tipo, cuenta, categoría/fuente,
  descripción). Solo el monto queda en blanco.

PÁGINA: TransactionsListPage (/transactions):
- Filtros en panel colapsable (desktop) o bottom sheet (mobile):
  - Rango de fechas (presets: este mes, mes anterior, últimos 30/90 días, este año, custom).
  - Cuenta (multi-select).
  - Categoría (multi-select).
  - Fuente (multi-select).
  - Tipo.
  - Búsqueda de texto en descripción.
- Lista virtualizada (TanStack Virtual) con scroll infinito.
- Cada row: ícono de cuenta, descripción + categoría/fuente como subtítulo,
  monto (verde si entra, rojo si sale, gris si transferencia), fecha.
- Click en row → modal/sheet de detalle con opciones Editar/Eliminar.
- Total al pie: ingresos, egresos, neto del rango filtrado.

EXPORTACIÓN:
- Botón "Exportar CSV" en TransactionsListPage que descarga el resultado
  del filtro actual.
- Endpoint nuevo backend: GET /api/transactions/export?format=csv (mismo
  filtros que /api/transactions).

TESTS:
- Componente AmountInput: formato correcto al teclear, parseo, casos edge
  (1234.5, 1,234.56, .50, 0).
- Componente TransactionForm: validaciones, submit, render condicional según tipo.

UX MOBILE:
- FAB (+) en todas las pantallas → abre NewTransactionPage como modal
  full-screen.
- Bottom sheet para detalles.
- Swipe izquierda en row de transacción → revela "Eliminar".

Al terminar, commit "feat(transactions): quick capture + list with filters"
y actualiza PROGRESS.md. NO avances a la siguiente fase.
```

**Criterios de aceptación:**
- Capturar una transacción nueva toma menos de 10 segundos en mobile.
- Filtros se reflejan en URL (query params) para compartir/recordar estado.
- Exportar CSV produce archivo válido importable a Excel.

---

# FASE 6 — Dashboard + saldos por cuenta

**Objetivo:** Vista principal con saldos consolidados y resumen del mes.

## Prompt para Claude Code:

```
Implementa el dashboard principal en /dashboard.

WIDGETS (cards apilables en mobile, grid en desktop):

1. Saldo total (suma de todas las cuentas DEBIT+SAVINGS+INVESTMENT+CASH menos
   saldos de CREDIT pendientes).
   - Número grande, formato MXN.
   - Subtítulo: "Activos: $X · Tarjetas: $Y".
   - Botón "Ver desglose" abre AccountsPage.

2. Flujo del mes actual:
   - 3 números: Ingresos, Egresos, Neto.
   - Mini chart de líneas con últimos 30 días (Recharts).

3. Saldos por cuenta:
   - Card por cuenta activa, con: ícono + nombre, institución, saldo, color.
   - TDC: muestra saldo usado + barra de progreso vs. límite.
   - Cuentas de inversión: muestra rendimiento del mes (intereses) en chip verde.

4. Próximos vencimientos TDC:
   - Lista de TDC con corte/pago en próximos 14 días.
   - Semáforo: verde si saldo líquido cubre el pago, amarillo si justo, rojo si falta.

5. Top categorías de gasto del mes:
   - Top 5 categorías con monto + barra horizontal.
   - Click en categoría → filtra TransactionsListPage por esa categoría.

6. Rendimientos del mes:
   - Card con total de intereses ganados en el mes.
   - Breakdown por cuenta (chips).

ENDPOINTS BACKEND NUEVOS:
- GET /api/dashboard/summary?month=YYYY-MM
  Devuelve toda la data del dashboard en una sola request (más eficiente).

CACHING:
- TanStack Query con staleTime 30s y refetchOnWindowFocus.
- Cuando se crea/edita/elimina una transacción, invalidar el query del dashboard.

EMPTY STATES:
- Si no hay cuentas: CTA "Crear tu primera cuenta".
- Si no hay transacciones: CTA "Registrar tu primer movimiento".

Al terminar, commit "feat(dashboard): main view with balances and monthly flow"
y actualiza PROGRESS.md. NO avances a la siguiente fase.
```

**Criterios de aceptación:**
- Dashboard carga en menos de 1 segundo en local.
- Todos los números cuadran al centavo con la BD.
- Empty states funcionan.

---

# FASE 7 — Reportes (fuentes de ingreso, patrimonio, comparativo de inversiones)

**Objetivo:** Vistas de análisis con gráficas.

## Prompt para Claude Code:

```
Implementa página /reports con sub-rutas:

/reports/income-sources:
- Selector de rango (default: últimos 12 meses).
- Stacked bar chart: una barra por mes, segmentos por fuente
  (Agencia, U-X, U-Y, Capacitación-Z, Otros).
- Tabla resumen: fuente | total | % del total | promedio mensual.
- Insight automático: "Tu fuente principal es X con Y% del total".

/reports/expenses:
- Pie chart de gastos por categoría (top nivel).
- Click en slice → drill-down a subcategorías.
- Tabla con totales.
- Comparativo mes vs. mes anterior con flechas ↑↓ y %.

/reports/net-worth:
- Line chart de patrimonio neto mes a mes (últimos 24 meses).
- Línea verde = activos, línea roja = pasivos, línea azul = neto.
- Snapshot del último mes con números grandes.

/reports/investments:
- Tabla comparativa de cuentas tipo SAVINGS/INVESTMENT:
  - Cuenta | Saldo actual | Intereses 30d | Intereses 90d | APR efectivo
    | APR nominal estimado (de account.invest_apr).
- Bar chart comparando APR efectivo por cuenta.
- Insight: "Tu mejor rendimiento es Nu con APR efectivo de X%".

ENDPOINTS BACKEND:
- GET /api/reports/income-sources?from=YYYY-MM-DD&to=YYYY-MM-DD&group_by=month
- GET /api/reports/expenses?from=&to=&group_by=month
- GET /api/reports/net-worth?months=24
- GET /api/reports/investments?days=30

CÁLCULO DE APR EFECTIVO:
- Para una cuenta de inversión, en un periodo dado:
  - capital_promedio = promedio diario del saldo en el periodo
  - apr_efectivo = (intereses / capital_promedio) * (365 / días_periodo)
- Implementar en backend/app/services/investments_service.py con tests.

EXPORTACIÓN:
- Botón "Exportar PDF" en cada reporte (usar reportlab o weasyprint en backend).
- Endpoint: POST /api/reports/{tipo}/export?format=pdf.

Al terminar, commit "feat(reports): income, expenses, net worth, investments"
y actualiza PROGRESS.md. NO avances a la siguiente fase.
```

**Criterios de aceptación:**
- Las 4 vistas de reportes funcionan con datos de prueba seed.
- APR efectivo se calcula correctamente en tests unitarios.
- Export a PDF genera archivo válido.

---

# FASE 8 — Tarjetas de crédito + alertas

**Objetivo:** Gestión de TDC con fechas de corte/pago y alertas.

## Prompt para Claude Code:

```
Implementa el módulo de TDC.

ENDPOINTS BACKEND:
- GET /api/credit-cards (lista con saldo actual, próximo corte, próximo pago,
  monto recomendado para pago total).
- POST /api/credit-cards (asociar TDC a una account existente).
- PATCH /api/credit-cards/{id}.
- DELETE /api/credit-cards/{id}.
- GET /api/credit-cards/{id}/statement?period=YYYY-MM (estado de cuenta del periodo).

LÓGICA DE FECHAS (backend/app/services/credit_cards_service.py):
- compute_next_statement_date(card, today): siguiente fecha de corte.
- compute_next_payment_date(card, today): siguiente fecha de pago.
- compute_amount_due(card, as_of): suma de transacciones del último periodo cerrado.
- Manejar correctamente meses con menos de 31 días (statement_day=31 en febrero).
- Tests con casos edge.

ALERTAS (backend/app/workers/alerts_worker.py):
- APScheduler con job diario a las 8:00 AM.
- Para cada TDC:
  - Si faltan 7 días para el pago: crear alerta.
  - Si faltan 2 días para el pago: alerta crítica.
  - Si saldo líquido total < amount_due: alerta de liquidez.
- Tabla nueva: alerts (id, type, severity, title, body, read_at, created_at).
- Endpoint: GET /api/alerts?unread=true, POST /api/alerts/{id}/read.

EMAIL (opcional, behind feature flag SMTP_ENABLED):
- Si SMTP configurado, enviar email para alertas críticas.
- Plantilla simple HTML.

PÁGINA FRONTEND /credit-cards:
- Card por TDC con:
  - Nombre + institución.
  - Saldo usado / límite (barra).
  - Próximo corte (fecha + días faltantes).
  - Próximo pago (fecha + días + monto a liquidar).
  - Botón "Registrar pago" → abre NewTransactionPage con TRANSFER precargada.
- Header de notificaciones (campana) con badge de alertas no leídas.
- Sidebar de notificaciones al hacer click en la campana.

Al terminar, commit "feat(credit-cards): TDC management with alerts"
y actualiza PROGRESS.md. NO avances a la siguiente fase.
```

**Criterios de aceptación:**
- Las fechas de corte/pago se calculan correctamente para todos los meses.
- Las alertas se generan automáticamente al correr el worker.
- Pago de TDC se registra como TRANSFER correctamente.

---

# FASE 9 — Recurrentes, presupuestos y proyecciones

**Objetivo:** Reglas recurrentes para proyectar flujo de caja, presupuestos por categoría.

## Prompt para Claude Code:

```
RECURRENTES:
- CRUD en /api/recurring-rules.
- El rrule sigue RFC 5545 (usar librería dateutil).
- Worker diario que materializa las recurrentes vencidas:
  - Para cada regla activa con next_date <= today:
    - Crea la transacción correspondiente.
    - Avanza next_date al siguiente match del rrule.
  - Registra en log qué se creó automáticamente.

PRESUPUESTOS:
- CRUD en /api/budgets.
- GET /api/budgets/status?month=YYYY-MM:
  - Lista con: categoría, presupuesto, gastado, restante, % usado, estado
    (OK | WARNING >80% | OVER >100%).
- Página frontend /budgets con:
  - Lista de categorías con barras de progreso.
  - Crear/editar presupuesto inline.
  - Alerta al exceder 80% (notificación + email opcional).

PROYECCIONES (backend/app/services/projections_service.py):
- project_cash_flow(start_date, end_date):
  - Para cada día en el rango:
    - Suma ingresos recurrentes que aplican ese día.
    - Suma egresos recurrentes que aplican ese día.
    - Calcula saldo acumulado.
  - Devuelve serie diaria.
- Endpoint: GET /api/projections/cash-flow?months=6
- Página frontend /projections:
  - Line chart con saldo proyectado día a día.
  - Marcar visualmente cuándo el saldo cruzaría a negativo (alerta).
  - Tabla resumen por mes: ingresos esperados, egresos esperados, neto, saldo
    proyectado a fin de mes.

TESTS:
- Materialización de recurrentes: cuando next_date es pasado, debe avanzar
  correctamente.
- Proyección con regla mensual: verificar 12 ocurrencias en 1 año.

Al terminar, commit "feat(recurring): rules, budgets and projections"
y actualiza PROGRESS.md. NO avances a la siguiente fase.
```

**Criterios de aceptación:**
- Una regla mensual ("Sueldo U-X cada día 15") genera transacciones automáticamente.
- Proyección a 6 meses cuadra con cálculos manuales.

---

# FASE 10 — Metas, indicadores financieros y simulador

**Objetivo:** Módulo de asesoría personal.

## Prompt para Claude Code:

```
INDICADORES (backend/app/services/indicators_service.py):
- savings_rate(period): (ingresos - egresos) / ingresos.
- debt_to_assets_ratio(): pasivos / activos.
- emergency_fund_months(): saldos_líquidos / promedio_egresos_mensuales_6m.
- net_worth_growth(months): % de crecimiento del patrimonio.
- top_expense_concentration(): % del gasto en top 3 categorías.

Endpoint: GET /api/indicators/dashboard
Devuelve todos los indicadores con valor actual, valor objetivo recomendado
y semáforo (good | warning | bad).

METAS (/api/goals):
- CRUD ya con tabla goals.
- Endpoint extra: POST /api/goals/{id}/contribute (registra transacción
  asociada a la meta).
- GET /api/goals/{id}/forecast: estima fecha de cumplimiento si se mantiene
  la tasa de aporte actual.
- Página frontend /goals:
  - Cards de metas con barra de progreso, fecha objetivo, monto faltante,
    aporte mensual recomendado.

SIMULADOR (backend/app/services/simulator_service.py):
- simulate_investment_move(from_account, to_account, amount, months):
  - Calcula intereses esperados en cada cuenta usando su invest_apr.
  - Devuelve diferencia neta.
- simulate_credit_card_payoff(card_id, monthly_payment):
  - Proyecta meses para liquidar con interés compuesto.
  - Compara: pagar mínimo vs. monto sugerido vs. pago total.

Página frontend /simulator:
- Tab "Mover entre cuentas":
  - Form: cuenta origen, cuenta destino, monto, plazo (meses).
  - Resultado: comparativo con bar chart.
- Tab "Liquidación de TDC":
  - Selector de TDC + slider de pago mensual.
  - Resultado: meses para liquidar + total intereses pagados.

DASHBOARD DE ASESORÍA (/advisor):
- 5 cards con indicadores clave y semáforos.
- 1 card con "Recomendaciones del mes" (texto generado en backend basado en
  reglas heurísticas: si tasa de ahorro < 10% → recomendar revisar gastos,
  si fondo emergencia < 3 meses → recomendar priorizarlo, etc.).

Al terminar, commit "feat(advisor): indicators, goals and simulator"
y actualiza PROGRESS.md. NO avances a la siguiente fase.
```

**Criterios de aceptación:**
- Indicadores tienen tests con casos conocidos.
- Simulador da resultados verificables con calculadora financiera.

---

# FASE 11 — Backups, deployment y producción

**Objetivo:** Llevar todo a fp.hanshatch.com con setup automatizado.

## Prompt para Claude Code:

```
INFRAESTRUCTURA:

1. deploy/docker-compose.prod.yml:
   - Servicios: traefik, postgres, backend, frontend (nginx con build estático),
     backup.
   - Red externa `traefik-public` (compartida con n8n existente).
   - Labels Traefik para fp.hanshatch.com con TLS Let's Encrypt.
   - Volúmenes nombrados para postgres-data, traefik-certs.

2. deploy/traefik/traefik.yml + dynamic.yml:
   - Entrypoints :80 (redirect a :443) y :443.
   - Cert resolver con Let's Encrypt (email: hans@hatch.mx).
   - Middlewares: secure-headers (HSTS, X-Frame-Options, CSP, etc.),
     rate-limit en /api/auth/*.

3. deploy/scripts/backup.sh:
   - pg_dump | gzip | gpg --symmetric (pass de env) → /backups/fp-YYYY-MM-DD.sql.gz.gpg.
   - rclone copy a Google Drive remote.
   - Retención local 7 días, remota 30 días.

4. deploy/scripts/restore.sh:
   - Permite restaurar desde backup específico (test trimestral obligatorio).

5. deploy/ansible/playbook.yml (idempotente):
   - Tasks:
     - Crear usuario no-root para deployment.
     - Actualizar paquetes.
     - Instalar Docker y Docker Compose plugin (si no está).
     - Configurar UFW: deny incoming, allow 22, 80, 443.
     - Instalar fail2ban con jails para SSH.
     - Crear red Docker `traefik-public` si no existe.
     - Conectar contenedor n8n a la red (si está corriendo).
     - Clonar/actualizar repo en /opt/fp-hanshatch.
     - Generar .env.prod con secretos (interactivo la primera vez).
     - docker compose -f docker-compose.prod.yml up -d.
     - Configurar cron para backup.sh diario 3 AM.
     - Verificar health: curl -f https://fp.hanshatch.com/api/health.

6. backend/app/api/health.py:
   - GET /api/health: status, version, db_connected, timestamp.

7. CI con GitHub Actions (.github/workflows/ci.yml):
   - Lint (ruff, eslint).
   - Tests (pytest, vitest).
   - Build de imágenes Docker.
   - Opcional: push a registry y deploy SSH al VPS si el commit es en main.

8. README.md actualizado con instrucciones de:
   - Setup local (docker-compose.dev.yml).
   - Deploy a producción (ansible-playbook).
   - Restore desde backup.

DOCUMENTACIÓN FINAL:
- docs/DEPLOYMENT.md con guía paso a paso.
- docs/RUNBOOK.md con procedimientos comunes (restaurar backup, rotar
  secretos, ver logs, rollback de deploy).

PRUEBA DE PRODUCCIÓN:
- Antes de declarar Fase 11 completa, realizar:
  1. Deploy a fp.hanshatch.com.
  2. Verificar n8n sigue funcionando.
  3. Registrar usuario, enrolar TOTP.
  4. Probar todos los flujos desde el celular.
  5. Forzar un backup manual y restaurarlo en local con éxito.

Al terminar, commit "feat(infra): production deployment with traefik, backups, ansible"
y actualiza PROGRESS.md marcando Fase 11 completa.
```

**Criterios de aceptación:**
- https://fp.hanshatch.com responde con certificado válido.
- https://n8n.hanshatch.com sigue funcionando (no se rompió nada).
- Backup automático corre cada noche.
- Restore probado y funcional.

---

# FASE 12 — Pulido y hardening (continua)

Tareas continuas sin orden estricto:

- Telemetría básica: logs estructurados (structlog) + Sentry para errores.
- Pruebas E2E con Playwright (al menos: login, crear transacción, ver dashboard).
- Performance: índices adicionales si alguna query supera 100ms.
- Accesibilidad: audit con axe-core, llegar a WCAG AA.
- PWA opcional (instalable, splash screen, offline cache de UI).
- Notificaciones push web (cuando se materialice una recurrente, cuando alerta TDC).
- Atajos de teclado (cmd+K para captura rápida, j/k para navegar lista).
- Modo "wizard" para onboarding inicial (crear cuentas, fuentes, primera transacción).

---

# Plantilla de PROGRESS.md (que Claude Code irá actualizando)

```markdown
# PROGRESS

## Fases completadas

- [ ] Fase 0 — Bootstrap del proyecto (YYYY-MM-DD)
- [ ] Fase 1 — Modelo de datos y migraciones (YYYY-MM-DD)
- [ ] Fase 2 — Autenticación y seguridad (YYYY-MM-DD)
- [ ] Fase 3 — CRUD core (YYYY-MM-DD)
- [ ] Fase 4 — Frontend base + auth (YYYY-MM-DD)
- [ ] Fase 5 — Captura rápida + listado (YYYY-MM-DD)
- [ ] Fase 6 — Dashboard (YYYY-MM-DD)
- [ ] Fase 7 — Reportes (YYYY-MM-DD)
- [ ] Fase 8 — TDC + alertas (YYYY-MM-DD)
- [ ] Fase 9 — Recurrentes, presupuestos, proyecciones (YYYY-MM-DD)
- [ ] Fase 10 — Asesoría: indicadores, metas, simulador (YYYY-MM-DD)
- [ ] Fase 11 — Deployment a producción (YYYY-MM-DD)

## En curso

(actualizar con la fase y subtareas activas)

## Bloqueos / decisiones pendientes

(usar para dejar notas a futuro)

## Notas técnicas relevantes

(decisiones de arquitectura tomadas durante la implementación)
```

---

# Convenciones de commits

Usar Conventional Commits:
- `feat:` nueva funcionalidad
- `fix:` corrección de bug
- `refactor:` cambio sin alterar comportamiento
- `test:` agrega o ajusta tests
- `docs:` documentación
- `chore:` mantenimiento (deps, configs)
- `style:` formato, sin lógica

Ejemplo: `feat(transactions): add bulk creation endpoint`

---

# Checklist final antes de considerar el proyecto v1.0

- [ ] Todas las fases 0-11 completadas.
- [ ] Cobertura de tests backend > 80% en services/.
- [ ] Sin queries N+1 (verificado con SQLAlchemy echo en tests).
- [ ] Lighthouse score > 90 en mobile.
- [ ] Backup restaurado exitosamente al menos una vez.
- [ ] 2FA funcionando con Google Authenticator real.
- [ ] Probado en iPhone Safari y Android Chrome.
- [ ] Documentación completa (README, ARCHITECTURE, DATABASE, DEPLOYMENT, RUNBOOK).
- [ ] Snapshot del VPS tomado antes del primer deploy.

---

**Fin del plan de trabajo. Buena suerte, Hans 🚀**
