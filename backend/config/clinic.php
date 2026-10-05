<?php

return [

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
