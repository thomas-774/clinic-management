<?php

return [

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
