# T0-03 — Create Laravel backend

**Phase:** 0 · **Area:** Backend · **Size:** S · **Depends on:** T0-02
**Plan refs:** §6, §6.2 (time zone)

## Steps
- [x] `composer create-project laravel/laravel backend` (Laravel 13; Laravel 11 is unsupported and every 11.x release has security advisories). Requires PHP 8.3 + Composer.
- [x] `php artisan install:api` (installs Sanctum and `routes/api.php`).
- [x] Create local MySQL 8 database + user; configure `.env` (`DB_*`).
- [x] Set `APP_TIMEZONE=Africa/Cairo` and confirm `config/app.php` reads it.
- [x] Add `.env.example` with all keys (no secrets).
- [x] Prefix API routes with `/api/v1`.

## Done when
- [x] `php artisan migrate` runs against MySQL with no errors.
- [x] `php artisan serve` starts.
