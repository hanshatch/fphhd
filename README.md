# FP — Finanzas Personales

Webapp personal de finanzas para Hans Hatch. Single-user, mobile-first, en español.

**Dominio:** https://fp.hanshatch.com  
**Stack:** FastAPI + PostgreSQL + React + TypeScript + Tailwind

---

## Arranque en desarrollo

### Requisitos previos

- Docker Desktop instalado y corriendo
- Node 20+ (`node --version`)
- Python 3.12+ con `uv` (`uv --version`)

### 1. Clonar e instalar

```bash
git clone <repo> fp-hanshatch
cd fp-hanshatch
```

### 2. Configurar variables de entorno

```bash
cp backend/.env.example backend/.env
cp frontend/.env.example frontend/.env
# Editar los valores necesarios en ambos archivos
```

### 3. Levantar con Docker Compose (recomendado)

```bash
docker compose -f deploy/docker-compose.dev.yml up -d
```

La app queda disponible en:
- Frontend: http://localhost:5173
- Backend API: http://localhost:8000
- Docs API: http://localhost:8000/api/docs

### 4. O levantar servicios individualmente

**Base de datos:**
```bash
docker compose -f deploy/docker-compose.dev.yml up db -d
```

**Backend:**
```bash
cd backend
uv sync
uv run alembic upgrade head
uv run uvicorn app.main:app --reload
```

**Frontend:**
```bash
cd frontend
npm install
npm run dev
```

---

## Primer registro

Al abrir la app por primera vez, el sistema detecta que no hay usuario y muestra el formulario de registro. Después del registro se solicita enrolar un autenticador TOTP (Google Authenticator, Authy, etc.).

---

## Tests

```bash
# Backend
cd backend && uv run pytest --cov=app --cov-report=term-missing

# Frontend
cd frontend && npm run test
```

---

## Linters y formateo

```bash
# Backend
cd backend
uv run ruff check app/ tests/
uv run ruff format app/ tests/
uv run mypy app/

# Frontend
cd frontend
npm run lint
npm run format
```

---

## Deploy a producción

Ver [docs/DEPLOYMENT.md](docs/DEPLOYMENT.md).

---

## Estructura del proyecto

```
fp-hanshatch/
├── backend/          # FastAPI + SQLAlchemy + Alembic
├── frontend/         # React + Vite + TypeScript + Tailwind
├── deploy/           # Docker Compose, Traefik, Ansible, scripts
├── docs/             # Documentación técnica
├── CLAUDE.md         # Instrucciones para Claude Code
└── PROGRESS.md       # Estado de desarrollo
```
