# Plan de Arquitectura — FP (Finanzas Personales)

**Proyecto:** Webapp personal de finanzas para Hans Hatch
**Dominio:** fp.hanshatch.com
**Hosting:** VPS Hostinger
**Fecha:** 2026-05-15

---

## 1. Resumen ejecutivo

Webapp **single-user**, **mobile-first**, en español, para registrar ingresos, egresos e inversiones con múltiples cuentas bancarias (Banamex, MercadoPago, Nu, Revolut) y proyectar flujo de caja, patrimonio neto y rendimientos. Acceso protegido con contraseña + 2FA, sobre HTTPS.

---

## 2. Stack tecnológico recomendado

Después de evaluar opciones para tu caso (single-user, VPS propio, necesidad de cálculos financieros y proyecciones, mantenibilidad a largo plazo), recomiendo:

| Capa | Tecnología | Por qué |
|---|---|---|
| **Backend** | **Python 3.12 + FastAPI** | Excelente para lógica financiera (decimal, fechas, proyecciones). Tipado fuerte, documentación automática (OpenAPI/Swagger), muy rápido. Ecosistema robusto (pandas para reportes, APScheduler para tareas programadas). |
| **Base de datos** | **PostgreSQL 16** | Soporte nativo de `NUMERIC` (decimales exactos, crítico en finanzas — nunca uses FLOAT para dinero), transacciones ACID, JSONB para flexibilidad, materialized views para reportes rápidos. |
| **ORM / Migraciones** | **SQLAlchemy 2.x + Alembic** | Estándar de la industria en Python. Migraciones versionadas (clave para no perder datos). |
| **Frontend** | **React + Vite + TypeScript + TailwindCSS + shadcn/ui** | Componentes accesibles, mobile-first, build rapidísimo. TypeScript previene errores en cálculos de dinero. |
| **Gráficas** | **Recharts** (o Chart.js como alternativa) | Curva de aprendizaje suave, suficiente para flujo de caja, dona de categorías y barras comparativas. |
| **Autenticación** | **JWT + Argon2 (hash de contraseña) + TOTP (2FA)** | Argon2id es el estándar moderno (mejor que bcrypt). TOTP con `pyotp` se compatibiliza con Google Authenticator/Authy. |
| **Reverse proxy** | **Nginx** | Termina TLS, sirve estáticos del frontend, hace proxy al backend, aplica headers de seguridad. |
| **TLS** | **Let's Encrypt (certbot)** | Gratis, renovación automática. |
| **Containers** | **Docker + Docker Compose** | Despliegue reproducible. Si algún día migras de Hostinger a otro VPS, solo levantas el compose. |
| **Backups** | **pg_dump + cron + rclone a Google Drive** | Respaldo diario cifrado fuera del VPS (regla 3-2-1). |
| **CI/CD (opcional)** | **GitHub Actions** | Tests + build + push de imagen a Docker Hub o GHCR; despliegue por SSH. |

### Por qué Python+FastAPI sobre las otras opciones

- **vs. PHP/Laravel:** PHP es válido en Hostinger, pero como tienes **VPS** no estás limitado. Para módulos de proyección, análisis y simuladores (tu requisito de asesoría), Python tiene `pandas`, `numpy`, `numpy_financial` (IRR, NPV, amortización) que son muy superiores.
- **vs. Node/Express:** Node está bien, pero los cálculos financieros con `Number` (float64) son riesgosos. Python tiene `Decimal` nativo y librerías financieras maduras.
- **vs. Django:** Django es excelente pero pesado para single-user. FastAPI es más liviano y moderno.

---

## 3. Modelo de datos (esquema lógico)

Tablas principales (en PostgreSQL, todos los montos como `NUMERIC(14,2)`):

```
users
  id, email, password_hash, totp_secret, totp_enabled, created_at, last_login_at

accounts                                  -- cuentas bancarias e inversiones
  id, name, type, institution, currency,
  initial_balance, is_active, color, icon,
  invest_apr (tasa nominal anual estimada),
  created_at

categories                                -- categorías + subcategorías (auto-referencia)
  id, parent_id, name, kind (INCOME|EXPENSE), color, icon, is_archived

sources                                   -- fuentes de ingreso (agencia, U-X, capacitación-Y)
  id, name, kind (AGENCY|UNIVERSITY|TRAINING|OTHER), notes

transactions                              -- núcleo del sistema
  id, date, type (INCOME|EXPENSE|TRANSFER|INTEREST|FEE),
  amount, account_id, category_id, source_id,
  counterparty_account_id (para transferencias),
  description, tags[], attachment_url, created_at

credit_cards
  id, account_id, statement_day, payment_day,
  credit_limit, current_balance, apr

recurring_rules                           -- ingresos/egresos recurrentes para proyecciones
  id, name, type, amount, account_id, category_id,
  rrule (RFC 5545), next_date, end_date, is_active

budgets                                   -- presupuestos por categoría
  id, category_id, period (MONTHLY|WEEKLY), amount, start_date, end_date

goals                                     -- metas de ahorro
  id, name, target_amount, current_amount,
  target_date, account_id, status

investment_yields                         -- rendimientos reales registrados
  id, account_id, period_start, period_end,
  amount_earned, apr_effective

audit_log                                 -- registro de accesos y cambios sensibles
  id, user_id, action, ip, user_agent, metadata, created_at
```

**Decisiones clave:**

- **Transferencias internas:** se modelan como **un solo registro** con `type=TRANSFER`, `account_id` (origen) y `counterparty_account_id` (destino). No se contabilizan ni como ingreso ni como egreso en los reportes. Esto evita inflar artificialmente los totales cuando muevas de Nu a Banamex para pagar la TDC.
- **Intereses de cajas de ahorro:** tipo `INTEREST`, vinculado a la cuenta donde se generaron. Se reportan separadamente del "ingreso operativo" (agencia/clases) para que veas el rendimiento real de cada vehículo.
- **TDC:** las tarjetas son `accounts` con flag de `credit_cards` para fechas de corte/pago y límite. El "pago de TDC" es un TRANSFER de tu cuenta de débito hacia la TDC.

---

## 4. Funcionalidades por módulo

### Módulo 1 — Captura (MVP)
- Formulario rápido mobile-first: tipo, monto, cuenta, categoría, fecha (default hoy), nota.
- Atajo "Transferencia entre cuentas" (un solo paso).
- Plantillas/recientes para captura aún más rápida ("Pago TDC Banamex", "Honorarios U-X").

### Módulo 2 — Dashboard
- Saldos por cuenta (con total general).
- Flujo del mes: ingresos vs. egresos, neto.
- Próximos vencimientos de TDC (semáforo si te falta liquidez para liquidar al corte).
- Tarjeta de patrimonio neto del mes.

### Módulo 3 — Reportes
- Ingresos por fuente (agencia / universidad / capacitación) — mensual y anual.
- Egresos por categoría con drill-down a subcategoría.
- Rendimientos por cuenta de inversión (intereses generados, APR efectivo, comparativo).
- Histórico de patrimonio neto.

### Módulo 4 — Proyecciones
- Flujo de caja a 1/3/6/12 meses usando `recurring_rules` (sueldos de U, retainers de agencia, rentas, suscripciones).
- Proyección de rendimientos por cuenta de inversión (interés compuesto).
- Vista de "¿qué pasa si muevo X de Banamex a Nu por 3 meses?" → simulador.

### Módulo 5 — Asesoría / Indicadores
- Tasa de ahorro = (ingresos - egresos) / ingresos.
- Razón de endeudamiento = deuda total / patrimonio.
- Meses de colchón de emergencia = saldos líquidos / egresos promedio.
- Metas (fondo de emergencia, viaje, retiro) con barra de progreso y fecha estimada.

### Módulo 6 — Alertas
- Email (vía Resend o SMTP de Hostinger) cuando:
  - Falten N días para corte/pago de TDC.
  - Una categoría exceda su presupuesto.
  - Una meta complete avance significativo.
- (Notificaciones push se podrían agregar luego con Web Push.)

---

## 5. Arquitectura de despliegue

```
                     Internet
                        │
                        ▼
              ┌──────────────────┐
              │  Nginx (HTTPS)   │  fp.hanshatch.com
              │  Let's Encrypt   │  + headers seguridad
              └──────┬───────────┘
                     │
        ┌────────────┴────────────┐
        ▼                         ▼
  /static (React build)    /api → FastAPI (uvicorn)
                                 │
                                 ▼
                          PostgreSQL 16
                                 │
                                 ▼
                          pg_dump diario
                                 │
                                 ▼
                    rclone → Google Drive (cifrado)
```

Todo orquestado con **Docker Compose** en el VPS:
- `nginx`
- `frontend` (build estático servido por nginx)
- `backend` (FastAPI + uvicorn)
- `db` (PostgreSQL con volumen persistente)
- `backup` (cron sidecar con pg_dump + rclone)

---

## 6. Seguridad

1. **Contraseña:** Argon2id, mínimo 12 caracteres, validación de fuerza.
2. **2FA:** TOTP obligatorio tras el primer login. Códigos de recuperación.
3. **Sesiones:** JWT en cookie HttpOnly + Secure + SameSite=Strict, expiración 30 min con refresh.
4. **Rate limiting:** en `/api/auth/*` con `slowapi`.
5. **Bloqueo de IP** tras 5 intentos fallidos (fail2ban a nivel sistema).
6. **Headers de seguridad** (Nginx): HSTS, CSP estricta, X-Frame-Options DENY, X-Content-Type-Options nosniff, Referrer-Policy strict-origin.
7. **Firewall UFW** en el VPS: solo 22 (SSH, mejor con llave), 80, 443.
8. **SSH:** llave pública, deshabilitar password login, cambiar puerto opcional.
9. **Auditoría:** tabla `audit_log` para logins, cambios de contraseña, exportaciones de datos.
10. **Backups cifrados** con GPG antes de subir a Google Drive.

---

## 7. Roadmap sugerido (fases)

**Fase 0 — Setup (1 semana)**
- Apuntar `fp.hanshatch.com` al VPS.
- Configurar VPS (Ubuntu 24.04, Docker, UFW, Nginx, certbot).
- Repo Git, estructura monorepo (backend/, frontend/, deploy/).

**Fase 1 — MVP (2-3 semanas)**
- Auth (login + 2FA).
- CRUD de cuentas, categorías, fuentes, transacciones.
- Dashboard básico (saldos + flujo del mes).
- Reporte de ingresos por fuente.

**Fase 2 — Inversiones y TDC (1-2 semanas)**
- Rendimientos y comparativo entre cuentas.
- Módulo de TDC con alertas de corte/pago.
- Transferencias internas con buen UX.

**Fase 3 — Proyecciones y presupuestos (1-2 semanas)**
- Reglas recurrentes.
- Flujo de caja proyectado.
- Presupuestos por categoría.

**Fase 4 — Asesoría (1 semana)**
- Indicadores financieros.
- Metas de ahorro.
- Simulador de escenarios.

**Fase 5 — Pulido**
- Backups verificados (probar restauración).
- Pruebas en celular.
- Documentación interna.

Total estimado trabajando en ratos: **6-10 semanas**.

---

## 8. Estructura de carpetas propuesta

```
fp-hanshatch/
├── backend/
│   ├── app/
│   │   ├── main.py
│   │   ├── core/         (config, security, deps)
│   │   ├── models/       (SQLAlchemy)
│   │   ├── schemas/      (Pydantic)
│   │   ├── api/          (routers)
│   │   ├── services/     (lógica de negocio: proyecciones, indicadores)
│   │   └── workers/      (tareas scheduled)
│   ├── alembic/
│   ├── tests/
│   ├── pyproject.toml
│   └── Dockerfile
├── frontend/
│   ├── src/
│   │   ├── components/
│   │   ├── pages/
│   │   ├── hooks/
│   │   ├── lib/          (api client, helpers)
│   │   └── App.tsx
│   ├── package.json
│   └── Dockerfile
├── deploy/
│   ├── docker-compose.yml
│   ├── nginx/
│   └── scripts/          (backup.sh, restore.sh)
└── README.md
```

---

## 9. Costos estimados

| Concepto | Costo |
|---|---|
| VPS Hostinger | Ya pagado |
| Dominio (subdominio) | $0 |
| Let's Encrypt | $0 |
| Google Drive (backups) | Ya pagado / plan gratuito |
| Resend (emails de alerta, opcional) | $0 (plan gratis 3k/mes) |
| **Total adicional mensual** | **$0** |

---

## 10. Lo que NO incluye (decisiones explícitas)

- ❌ Sincronización automática con bancos (Belvo/Plaid). Captura es manual.
- ❌ OCR de tickets. Se puede agregar en v2 si lo deseas.
- ❌ Multi-usuario. Solo Hans.
- ❌ Multi-moneda. Solo MXN.
- ❌ PWA / app nativa. Web responsive bastará.
- ❌ Doble entrada contable. Modelo simplificado pero íntegro.

---

## Próximo paso sugerido

Si te late este plan, el siguiente entregable lógico sería el **diseño detallado del esquema de BD con relaciones, índices y constraints**, junto con los wireframes de las pantallas clave (login, captura rápida, dashboard, reporte de fuentes).

¿Avanzamos por ahí, o prefieres revisar/ajustar algo de este plan primero?
