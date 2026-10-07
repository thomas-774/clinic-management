<?php

use Carbon\CarbonInterface;

return [

    /*
    |--------------------------------------------------------------------------
    | Calendar and money
    |--------------------------------------------------------------------------
    |
    | The week starts on Saturday in Egypt (§8 Phase 6); reports and the
    | dashboard read this one value. Amounts are in EGP (PR-6).
    |
    */

    'week_start' => CarbonInterface::SATURDAY,

    'currency' => 'EGP',

    /*
    |--------------------------------------------------------------------------
    | Booking limits
    |--------------------------------------------------------------------------
    |
    | How many future appointments (booked or checked in) one patient may hold
    | at a time (FR-E.5, BR-4). Raise it here without code changes.
    |
    */

    'max_active_appointments' => (int) env('CLINIC_MAX_ACTIVE_APPOINTMENTS', 1),

    /*
    |--------------------------------------------------------------------------
    | Rate limits (requests per minute)
    |--------------------------------------------------------------------------
    |
    | §9.2, NFR-S.2: login per account + IP, register per IP, writes
    | (POST / PUT / PATCH / DELETE) and searches per user. A request over the
    | limit gets 429 with Retry-After and a translated { message }.
    |
    */

    'rate_limits' => [
        'login' => (int) env('CLINIC_RATE_LIMIT_LOGIN', 5),
        'register' => (int) env('CLINIC_RATE_LIMIT_REGISTER', 5),
        'writes' => (int) env('CLINIC_RATE_LIMIT_WRITES', 60),
        'search' => (int) env('CLINIC_RATE_LIMIT_SEARCH', 120),
    ],

    /*
    |--------------------------------------------------------------------------
    | Doctor account
    |--------------------------------------------------------------------------
    |
    | The single doctor account created by DoctorSeeder (T1-07). Set these in
    | .env; the seeder stops with an error when phone or password is missing.
    |
    */

    'doctor' => [
        'name' => env('DOCTOR_NAME', 'Dr. Doctor'),
        'phone' => env('DOCTOR_PHONE'),
        'email' => env('DOCTOR_EMAIL'),
        'password' => env('DOCTOR_PASSWORD'),
    ],

];
