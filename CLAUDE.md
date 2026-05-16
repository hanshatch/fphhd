# CLAUDE.md — Instrucciones permanentes para Claude Code

> Este archivo lo lee Claude Code automáticamente en cada sesión.

---

## 1. Identidad del proyecto

**Nombre:** FP (Finanzas Personales)
**Dueño / único usuario:** Hans Hatch (`hans@hatch.mx`)
**Dominio producción:** `fp.hanshatch.com`
**Repositorio:** `fphhd` (github.com/hanshatch/fphhd)
**Idioma de interfaz:** español (México)
**Moneda:** MXN
**Tipo de app:** webapp single-user, mobile-first

---

## 2. Stack técnico

| Capa | Tecnología |
|---|---|
| Framework | Laravel 13 |
| Base de datos | MySQL 8 |
| ORM / Migraciones | Eloquent + Laravel Migrations |
| Frontend | Blade + Tailwind CSS v4 |
| Gráficas | Chart.js |
| Auth + 2FA | Laravel Breeze + pragmarx/google2fa |
| Dinero sin float | PHP bcmath (nativo) |
| Tareas programadas | Laravel Scheduler |
| Deploy | Git + Composer + php artisan migrate vía SSH |

---

## 3. Entornos

- **Dev:** Mac local con Valet, URL https://fp.test, DB MySQL local `fp_local`
- **Prod:** Hosting con SSH, URL https://fp.hanshatch.com, DB MySQL del hosting

---

## 4. Principios no negociables

### 4.1 Dinero
- **NUNCA** usar `float` para montos. Siempre:
  - PHP: `bcmath` para cálculos (`bcadd`, `bcsub`, `bcmul`, `bcdiv` con escala 2).
  - MySQL: `DECIMAL(14,2)`.
  - Blade: helper `format_currency()` definido en `app/helpers.php`.
- Inputs: aceptar `1,234.56`, `1234.56`, `.50`. Parsear antes de guardar.

### 4.2 Transferencias internas
- Tipo `transfer` en `transactions` con `account_id` (origen) y `counterparty_account_id` (destino).
- **NO** se cuentan como ingreso ni egreso en reportes.
- Sí afectan saldo de ambas cuentas.

### 4.3 Intereses
- Tipo `interest` separado de `income`.
- En reportes de ingresos operativos NO aparecen.
- En rendimientos por cuenta sí.

### 4.4 Mobile-first
- Diseñar primero para viewport 375px. Luego escalar.
- Botones táctiles mínimo 44x44px.
- Inputs numéricos con `inputmode="decimal"`.
- FAB (botón flotante "+") siempre visible.

### 4.5 Seguridad
- Passwords con `bcrypt` (Laravel default, BCRYPT_ROUNDS=12).
- TOTP obligatorio tras primer login (pragmarx/google2fa).
- Sesiones Laravel estándar (cookie segura).
- 5 intentos fallidos → bloqueo 15 min.
- Toda acción sensible registrada en `audit_logs`.

### 4.6 Migraciones
- **Siempre** con `php artisan make:migration`. Nunca SQL directo en producción.
- Cada migración con nombre descriptivo.
- Revisar la migración antes de correrla en producción.

---

## 5. Convenciones de código

### PHP / Laravel
- PSR-12 para estilo de código.
- Naming: `snake_case` para variables/métodos, `PascalCase` para clases.
- Modelos en singular (`Account`, `Transaction`), tablas en plural (`accounts`, `transactions`).
- Lógica de negocio en `app/Services/`, no en controladores ni modelos.
- Controladores delgados: reciben request, llaman servicio, devuelven respuesta.
- Sin `dd()` en código que va a producción.

### Blade
- Componentes reutilizables en `resources/views/components/`.
- Layouts en `resources/views/layouts/`.
- Una vista por pantalla en `resources/views/pages/`.

### MySQL
- Tablas en plural `snake_case`: `transactions`, `credit_cards`.
- PKs: `id` auto-increment.
- Timestamps: `created_at`, `updated_at`.
- Montos: `DECIMAL(14,2)`.

### Git
- Conventional Commits: `feat:`, `fix:`, `refactor:`, `test:`, `docs:`, `chore:`.
- 1 commit por fase completada mínimo.

---

## 6. Estructura de carpetas

```
fp/
├── app/                        ← proyecto Laravel
│   ├── app/
│   │   ├── Http/Controllers/
│   │   ├── Models/
│   │   ├── Services/           ← lógica financiera
│   │   └── Helpers/
│   ├── database/
│   │   ├── migrations/
│   │   └── seeders/
│   ├── resources/
│   │   ├── views/
│   │   │   ├── components/
│   │   │   ├── layouts/
│   │   │   └── pages/
│   │   ├── css/
│   │   └── js/
│   ├── routes/web.php
│   └── .env.example
├── CLAUDE.md
├── PROGRESS.md
└── README.md
```

---

## 7. Glosario financiero

| Término | Significado |
|---|---|
| **Cuenta (Account)** | Cualquier vehículo de dinero: débito, TDC, caja de ahorro, efectivo. |
| **TDC** | Tarjeta de crédito. Tipo `credit`. |
| **Caja de ahorro** | Cuenta tipo `savings` que genera intereses (Nu, Revolut, MercadoPago). |
| **Fuente (Source)** | Origen del ingreso operativo: agencia, universidad, capacitación. |
| **Transferencia interna** | Movimiento entre dos cuentas propias. No afecta totales ingreso/egreso. |
| **Corte** | Fecha de cierre del periodo de la TDC. |
| **Pago** | Fecha límite para liquidar la TDC sin intereses. |
| **APR efectivo** | Tasa real calculada con intereses observados sobre saldo promedio. |

---

## 8. Reglas de UX

- Todo en español de México.
- Montos: `$1,234.56` — negativos en rojo, positivos en verde, transferencias en gris.
- Fechas cortas: `15 may 2026`. Fechas largas: `viernes, 15 de mayo de 2026`.
- Empty states siempre con CTA claro.
- Confirmaciones destructivas con modal.
- Toasts: 3 segundos, 5 con "Deshacer".

---

## 9. Flujo de trabajo en cada sesión

1. Leer `PROGRESS.md`.
2. Confirmar con Hans qué fase trabajar.
3. `git status` limpio antes de empezar.
4. Commits pequeños y descriptivos.
5. Al terminar: correr `php artisan test`, actualizar `PROGRESS.md`, commit final.

---

## 10. Lo que NO debes hacer

- ❌ `float` para dinero.
- ❌ SQL directo en producción (solo migraciones).
- ❌ Multi-usuario, multi-tenancy, roles.
- ❌ APIs bancarias (captura manual).
- ❌ Subir `.env` al repo.
- ❌ `dd()` en código de producción.
- ❌ Lógica de negocio en controladores o vistas.

---

**Fin del documento.**
