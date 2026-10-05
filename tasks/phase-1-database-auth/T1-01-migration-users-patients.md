# T1-01 — Migrations: users and patients

**Phase:** 1 · **Area:** Backend / DB · **Size:** S · **Depends on:** T0-03
**Plan refs:** §5.1 users, patients · §5.3

## Steps
- [ ] Edit the default `users` migration: `name` VARCHAR(120), `phone` VARCHAR(20) UNIQUE, `email` VARCHAR(150) UNIQUE nullable, `password`, `role` ENUM('patient','doctor').
- [ ] Create `patients`: `user_id` FK UNIQUE (cascade delete), `address` VARCHAR(255), `date_of_birth` DATE nullable, `gender` ENUM('male','female') nullable, `current_illness` TEXT nullable.

## Done when
- [ ] `php artisan migrate:fresh` succeeds and the database rejects a duplicate phone number.
