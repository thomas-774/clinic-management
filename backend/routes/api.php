<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\Doctor\MedicalHistoryController;
use App\Http\Controllers\Api\V1\Doctor\PatientController;
use App\Http\Controllers\Api\V1\Patient\ProfileController;
use Illuminate\Support\Facades\Route;

// All routes are prefixed with /api/v1 (bootstrap/app.php).

Route::get('/health', fn () => response()->json([
    'status' => 'ok',
    'time' => now()->toIso8601String(),
]));

Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login');
    Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');
});

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [AuthController::class, 'me']);

    // Patient area: /api/v1/patient/* (profile, own appointments).
    Route::prefix('patient')->middleware('role:patient')->name('patient.')->group(function () {
        Route::get('/profile', [ProfileController::class, 'show'])->name('profile.show');
        Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    });

    // Doctor area: /api/v1/doctor/* (patients, schedule, visits, settings, reports).
    Route::prefix('doctor')->middleware('role:doctor')->name('doctor.')->group(function () {
        Route::get('/patients', [PatientController::class, 'index'])->name('patients.index');
        Route::post('/patients', [PatientController::class, 'store'])->name('patients.store');
        Route::get('/patients/{patient}', [PatientController::class, 'show'])->name('patients.show');
        Route::put('/patients/{patient}', [PatientController::class, 'update'])->name('patients.update');

        Route::scopeBindings()->prefix('/patients/{patient}/history')->name('patients.history.')->group(function () {
            Route::post('/', [MedicalHistoryController::class, 'store'])->name('store');
            Route::put('/{medicalHistoryEntry}', [MedicalHistoryController::class, 'update'])->name('update');
            Route::delete('/{medicalHistoryEntry}', [MedicalHistoryController::class, 'destroy'])->name('destroy');
        });
    });
});
