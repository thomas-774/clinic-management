<?php

use App\Http\Controllers\Api\V1\Assistant\PatientController as AssistantPatientController;
use App\Http\Controllers\Api\V1\Assistant\ScheduleController as AssistantScheduleController;
use App\Http\Controllers\Api\V1\Assistant\VisitController as AssistantVisitController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\Doctor\BlockedTimeController;
use App\Http\Controllers\Api\V1\Doctor\DrugController;
use App\Http\Controllers\Api\V1\Doctor\MedicalHistoryController;
use App\Http\Controllers\Api\V1\Doctor\PatientController;
use App\Http\Controllers\Api\V1\Doctor\PrescriptionController;
use App\Http\Controllers\Api\V1\Doctor\ReportController;
use App\Http\Controllers\Api\V1\Doctor\ScheduleController;
use App\Http\Controllers\Api\V1\Doctor\SettingsController;
use App\Http\Controllers\Api\V1\Doctor\StaffController;
use App\Http\Controllers\Api\V1\Doctor\VisitController;
use App\Http\Controllers\Api\V1\Doctor\VisitExportController;
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
    Route::get('/slots', [SlotController::class, 'index'])->middleware('role:patient,doctor,assistant')->name('slots.index');

    // Patient area: /api/v1/patient/* (profile, own appointments).
    Route::prefix('patient')->middleware('role:patient')->name('patient.')->group(function () {
        Route::get('/profile', [ProfileController::class, 'show'])->name('profile.show');
        Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
        Route::get('/appointments', [PatientAppointmentController::class, 'index'])->name('appointments.index');
        Route::post('/appointments', [PatientAppointmentController::class, 'store'])->name('appointments.store');
        Route::patch('/appointments/{appointment}/cancel', [PatientAppointmentController::class, 'cancel'])->name('appointments.cancel');
    });

    // Doctor area: /api/v1/doctor/* (patients, schedule, visits, settings, reports, drugs, prescriptions).
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

        Route::post('/visits', [VisitController::class, 'store'])->name('visits.store');
        Route::put('/visits/{visit}', [VisitController::class, 'update'])->name('visits.update');
        Route::post('/visits/{visit}/payments', [VisitController::class, 'storePayment'])->name('visits.payments.store');
        Route::get('/visits/{visit}/export', VisitExportController::class)->name('visits.export');

        Route::get('/settings', [SettingsController::class, 'show'])->name('settings.show');
        Route::put('/settings', [SettingsController::class, 'update'])->name('settings.update');
        Route::get('/working-hours', [WorkingHoursController::class, 'index'])->name('working-hours.index');
        Route::put('/working-hours', [WorkingHoursController::class, 'update'])->name('working-hours.update');
        Route::get('/staff', [StaffController::class, 'index'])->name('staff.index');
        Route::post('/staff', [StaffController::class, 'store'])->name('staff.store');
        Route::put('/staff/{staff}', [StaffController::class, 'update'])->name('staff.update');
        Route::get('/blocked-times', [BlockedTimeController::class, 'index'])->name('blocked-times.index');
        Route::post('/blocked-times', [BlockedTimeController::class, 'store'])->name('blocked-times.store');
        Route::delete('/blocked-times/{blockedTime}', [BlockedTimeController::class, 'destroy'])->name('blocked-times.destroy');

        Route::get('/drugs/search', [DrugController::class, 'search'])->name('drugs.search');
        Route::get('/drugs', [DrugController::class, 'index'])->name('drugs.index');
        Route::post('/drugs', [DrugController::class, 'store'])->name('drugs.store');
        Route::get('/drugs/{drug}', [DrugController::class, 'show'])->name('drugs.show');
        Route::put('/drugs/{drug}', [DrugController::class, 'update'])->name('drugs.update');

        Route::get('/patients/{patient}/prescriptions', [PrescriptionController::class, 'index'])->name('patients.prescriptions.index');
        Route::post('/patients/{patient}/prescriptions', [PrescriptionController::class, 'store'])->name('patients.prescriptions.store');
        Route::get('/prescriptions/{prescription}', [PrescriptionController::class, 'show'])->name('prescriptions.show');
        Route::put('/prescriptions/{prescription}', [PrescriptionController::class, 'update'])->name('prescriptions.update');
        Route::delete('/prescriptions/{prescription}', [PrescriptionController::class, 'destroy'])->name('prescriptions.destroy');

        Route::get('/reports/summary', [ReportController::class, 'summary'])->name('reports.summary');
        Route::get('/reports/payments', [ReportController::class, 'payments'])->name('reports.payments');
        Route::get('/reports/outstanding', [ReportController::class, 'outstanding'])->name('reports.outstanding');
        Route::get('/reports/daily-revenue', [ReportController::class, 'dailyRevenue'])->name('reports.daily-revenue');
    });

    // Assistant (front desk) area: /api/v1/assistant/* — contact info and money only (Module I).
    Route::prefix('assistant')->middleware('role:assistant')->name('assistant.')->group(function () {
        Route::get('/patients', [AssistantPatientController::class, 'index'])->name('patients.index');
        Route::post('/patients', [AssistantPatientController::class, 'store'])->name('patients.store');
        Route::get('/patients/{patient}', [AssistantPatientController::class, 'show'])->name('patients.show');
        Route::put('/patients/{patient}', [AssistantPatientController::class, 'update'])->name('patients.update');

        Route::get('/visits/unpaid', [AssistantVisitController::class, 'unpaid'])->name('visits.unpaid');
        Route::post('/visits/{visit}/payments', [AssistantVisitController::class, 'storePayment'])->name('visits.payments.store');

        Route::get('/appointments', [AssistantScheduleController::class, 'index'])->name('appointments.index');
        Route::post('/appointments', [AssistantScheduleController::class, 'store'])->name('appointments.store');
        Route::patch('/appointments/{appointment}/status', [AssistantScheduleController::class, 'updateStatus'])->name('appointments.status');
    });
});
