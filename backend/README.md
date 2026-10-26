# Backend del panel (Laravel 13 + PostgreSQL)

API y lógica del panel de proyectos de MisioWeb. Para instalarlo en el servidor, ver `deploy/README.md`.

## Qué hay

- **Esquema** (`database/migrations/2026_10_26_000001_create_panel_tables.php`): las tablas de `docs/spec.md`.
- **Catálogo** (`database/seeders/CatalogSeeder.php`): widgets y requisitos tomados del prototipo. Precios de ejemplo.
- **Propuesta** (`app/Services/ProposalCalculator.php`): base más widgets, en USD y en pesos. Los montos se calculan en centavos enteros para que no haya errores de redondeo.
- **Señales del plugin** (`app/Services/SignalMapper.php`): qué requisito marca cada señal del plugin de WordPress.
- **Caso de ejemplo** (`database/seeders/SankalpaDemoSeeder.php`): el proyecto Sankalpa del prototipo.

## Desarrollo local

```bash
cd backend
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate --seed
php artisan db:seed --class=SankalpaDemoSeeder
php artisan panel:propuesta
```

En desarrollo alcanza con SQLite; en producción se usa PostgreSQL.

## Tests

```bash
php artisan test
```

Los tests usan SQLite en memoria. Para correrlos contra PostgreSQL:

```bash
DB_CONNECTION=pgsql DB_HOST=127.0.0.1 DB_DATABASE=misioweb_test DB_USERNAME=misioweb DB_PASSWORD=... php artisan test
```
