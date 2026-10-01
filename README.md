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

Un `git push` a `main` dispara el webhook de Hostinger, que hace el pull en el servidor.
Los assets (`app/public/build`) se compilan en local con `npm run build` y se suben al repo.
Las migraciones y cachés siguen siendo manuales:

```bash
# Por SSH en el servidor (acceso en tus notas privadas), dentro de la carpeta app/
php artisan migrate --force      # solo si hay migraciones nuevas
php artisan view:clear && php artisan route:clear && php artisan config:cache
```

---

## Estructura

```
fp/
├── app/              ← proyecto Laravel
├── docs/
│   ├── brand/        ← manual de identidad y logo
│   └── planes/       ← planes internos (solo locales, fuera del repo)
├── CLAUDE.md         ← instrucciones para Claude Code
├── PROGRESS.md       ← estado del desarrollo
└── README.md
```
