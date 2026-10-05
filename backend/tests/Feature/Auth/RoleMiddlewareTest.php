<?php

use App\Models\Patient;
use App\Models\User;
use Illuminate\Support\Facades\Route;

// Same middleware stack as the /patient and /doctor groups in routes/api.php.
beforeEach(function () {
    Route::middleware(['api', 'auth:sanctum'])->prefix('api/v1')->group(function () {
        Route::get('/patient/_ping', fn () => ['area' => 'patient'])->middleware('role:patient');
        Route::get('/doctor/_ping', fn () => ['area' => 'doctor'])->middleware('role:doctor');
        Route::get('/shared/_ping', fn () => ['area' => 'shared'])->middleware('role:patient,doctor');
    });

    $this->withHeader('Accept-Language', 'en');
    $this->patientToken = Patient::factory()->create()->user->createToken('api')->plainTextToken;
    $this->doctorToken = User::factory()->doctor()->create()->createToken('api')->plainTextToken;
});

it('lets a patient into patient routes only', function () {
    $this->withToken($this->patientToken)->getJson('/api/v1/patient/_ping')->assertOk();

    app('auth')->forgetGuards();
    $this->withToken($this->patientToken)->getJson('/api/v1/doctor/_ping')
        ->assertForbidden()
        ->assertExactJson(['message' => 'This action is unauthorized.']);
});

it('lets a doctor into doctor routes only', function () {
    $this->withToken($this->doctorToken)->getJson('/api/v1/doctor/_ping')->assertOk();

    app('auth')->forgetGuards();
    $this->withToken($this->doctorToken)->getJson('/api/v1/patient/_ping')->assertForbidden();
});

it('accepts several roles', function () {
    $this->withToken($this->patientToken)->getJson('/api/v1/shared/_ping')->assertOk();

    app('auth')->forgetGuards();
    $this->withToken($this->doctorToken)->getJson('/api/v1/shared/_ping')->assertOk();
});

it('returns 401, not 403, without a token', function () {
    $this->getJson('/api/v1/doctor/_ping')->assertUnauthorized();
});

it('translates the 403 message', function () {
    $this->withToken($this->patientToken)
        ->getJson('/api/v1/doctor/_ping', ['Accept-Language' => 'ar'])
        ->assertForbidden()
        ->assertExactJson(['message' => 'هذا الإجراء غير مصرح به.']);
});

it('protects the real /patient and /doctor route groups', function () {
    $routes = collect(Route::getRoutes()->getRoutes())
        ->filter(fn ($route) => preg_match('#^api/v1/(patient|doctor)/#', $route->uri()) && ! str_contains($route->uri(), '_ping'));

    $routes->each(function ($route) {
        $area = str_starts_with($route->uri(), 'api/v1/doctor/') ? 'doctor' : 'patient';

        expect($route->gatherMiddleware())->toContain('auth:sanctum', "role:{$area}");
    });
})->skip(fn () => ! collect(Route::getRoutes()->getRoutes())->contains(
    fn ($route) => preg_match('#^api/v1/(patient|doctor)/#', $route->uri()) && ! str_contains($route->uri(), '_ping'),
), 'No /patient or /doctor routes yet (added in Phase 2).');
