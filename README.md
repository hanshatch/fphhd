# FP — Finanzas Personales

Webapp personal de finanzas para Hans Hatch. Single-user, mobile-first, en español.

**Dominio:** https://fp.hanshatch.com
**Stack:** Laravel 13 + MySQL + Blade + Tailwind CSS v4

---

## Requisitos de desarrollo

- PHP 8.4+ (via Homebrew)
- Composer
- MySQL 8 (via Homebrew)
- Laravel Valet

---

## Arranque en desarrollo

```bash
# 1. Clonar
git clone https://github.com/hanshatch/fphhd fp
cd fp/app

# 2. Instalar dependencias
composer install
npm install

# 3. Configurar entorno
cp .env.example .env
php artisan key:generate
# Editar .env con credenciales de DB local

# 4. Migrar base de datos
php artisan migrate

# 5. Compilar assets
npm run dev

# 6. Enlazar con Valet
valet link fp
valet secure fp
```

La app queda en **https://fp.test**

---

## Comandos frecuentes

```bash
# Correr migraciones
php artisan migrate

# Crear nueva migración
php artisan make:migration create_accounts_table

# Correr tests
php artisan test

# Compilar assets en desarrollo (con hot reload)
npm run dev

# Compilar para producción
npm run build
```

---

## Deploy a producción

```bash
# En el servidor vía SSH
git pull
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan config:cache
php artisan route:cache
npm run build
```

---

## Estructura

```
fp/
├── app/          ← proyecto Laravel
├── CLAUDE.md     ← instrucciones para Claude Code
├── PROGRESS.md   ← estado del desarrollo
└── README.md
```
