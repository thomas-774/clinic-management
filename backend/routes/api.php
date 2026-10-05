<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\Doctor\BlockedTimeController;
use App\Http\Controllers\Api\V1\Doctor\MedicalHistoryController;
use App\Http\Controllers\Api\V1\Doctor\PatientController;
use App\Http\Controllers\Api\V1\Doctor\ScheduleController;
use App\Http\Controllers\Api\V1\Doctor\SettingsController;
use App\Http\Controllers\Api\V1\Doctor\WorkingHoursController;
use App\Http\Controllers\Api\V1\Patient\AppointmentController as PatientAppointmentController;
use App\Http\Controllers\Api\V1\Patient\ProfileController;
use App\Http\Controllers\Api\V1\SlotController;
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
    Route::get('/slots', [SlotController::class, 'index'])->middleware('role:patient,doctor')->name('slots.index');

    // Patient area: /api/v1/patient/* (profile, own appointments).
    Route::prefix('patient')->middleware('role:patient')->name('patient.')->group(function () {
        Route::get('/profile', [ProfileController::class, 'show'])->name('profile.show');
        Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
        Route::get('/appointments', [PatientAppointmentController::class, 'index'])->name('appointments.index');
        Route::post('/appointments', [PatientAppointmentController::class, 'store'])->name('appointments.store');
        Route::patch('/appointments/{appointment}/cancel', [PatientAppointmentController::class, 'cancel'])->name('appointments.cancel');
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

        Route::get('/appointments', [ScheduleController::class, 'index'])->name('appointments.index');
        Route::post('/appointments', [ScheduleController::class, 'store'])->name('appointments.store');
        Route::patch('/appointments/{appointment}/status', [ScheduleController::class, 'updateStatus'])->name('appointments.status');

        Route::get('/settings', [SettingsController::class, 'show'])->name('settings.show');
        Route::put('/settings', [SettingsController::class, 'update'])->name('settings.update');
        Route::get('/working-hours', [WorkingHoursController::class, 'index'])->name('working-hours.index');
        Route::put('/working-hours', [WorkingHoursController::class, 'update'])->name('working-hours.update');
        Route::get('/blocked-times', [BlockedTimeController::class, 'index'])->name('blocked-times.index');
        Route::post('/blocked-times', [BlockedTimeController::class, 'store'])->name('blocked-times.store');
        Route::delete('/blocked-times/{blockedTime}', [BlockedTimeController::class, 'destroy'])->name('blocked-times.destroy');
    });
});
