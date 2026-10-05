# Clinic backend (Laravel 13 API)

Requires PHP 8.3+, Composer and MySQL 8.

## First-time setup

```bash
composer install
cp .env.example .env          # then set DB_PASSWORD
php artisan key:generate
# create databases `clinic` and `clinic_testing` and a MySQL user with access to both
php artisan migrate
php artisan serve             # http://localhost:8000
```

- API routes live in `routes/api.php` and are served under `/api/v1`.
- `APP_TIMEZONE=Africa/Cairo`; default locale `ar`, fallback `en`.
