# T0-03 — Create Laravel backend

**Phase:** 0 · **Area:** Backend · **Size:** S · **Depends on:** T0-02
**Plan refs:** §6, §6.2 (time zone)

## Steps
- [ ] `composer create-project laravel/laravel backend` (Laravel 11).
- [ ] `php artisan install:api` (installs Sanctum and `routes/api.php`).
- [ ] Create local MySQL 8 database + user; configure `.env` (`DB_*`).
- [ ] Set `APP_TIMEZONE=Africa/Cairo` and confirm `config/app.php` reads it.
- [ ] Add `.env.example` with all keys (no secrets).
- [ ] Prefix API routes with `/api/v1`.

## Done when
- [ ] `php artisan migrate` runs against MySQL with no errors.
- [ ] `php artisan serve` starts.
