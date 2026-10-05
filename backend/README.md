# Clinic backend (Laravel 13 API)

Requires PHP 8.3+, Composer and MySQL 8.

## First-time setup

```bash
composer install
cp .env.example .env          # then set DB_PASSWORD and DOCTOR_PHONE / DOCTOR_PASSWORD
php artisan key:generate
# create databases `clinic` and `clinic_testing` and a MySQL user with access to both
php artisan migrate --seed    # doctor account, default settings, Sat–Thu 17:00–21:00, 10 fake patients
php artisan serve             # http://localhost:8000
```

- The doctor logs in with `DOCTOR_PHONE` (or `DOCTOR_EMAIL`) and `DOCTOR_PASSWORD`; seeded patients use the password `password`.
- Reset the local data at any time with `php artisan migrate:fresh --seed`.
- API routes live in `routes/api.php` and are served under `/api/v1`.
- Responses are `{ data, message }`; errors are JSON `{ message, errors? }` in the language of `Accept-Language` (`ar` default, `en`).
- `APP_TIMEZONE=Africa/Cairo`; dates are ISO 8601 with the Cairo offset.
- Arabic/English messages come from `lang/` (published by `laravel-lang`; run `php artisan lang:update` after upgrading it). Business constants live in `config/clinic.php`.

## Tests

```bash
php artisan test
```

Pest runs against the MySQL database `clinic_testing` (set in `phpunit.xml`; credentials come from `.env`). Feature tests use `RefreshDatabase`.
