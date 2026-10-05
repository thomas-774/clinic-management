<?php

use App\Models\Patient;
use App\Models\User;
use Illuminate\Support\Facades\Route;

// Same middleware stack as the /patient, /doctor and /assistant groups in routes/api.php.
beforeEach(function () {
    Route::middleware(['api', 'auth:sanctum'])->prefix('api/v1')->group(function () {
        Route::get('/patient/_ping', fn () => ['area' => 'patient'])->middleware('role:patient');
        Route::get('/doctor/_ping', fn () => ['area' => 'doctor'])->middleware('role:doctor');
        Route::get('/assistant/_ping', fn () => ['area' => 'assistant'])->middleware('role:assistant');
        Route::get('/shared/_ping', fn () => ['area' => 'shared'])->middleware('role:patient,doctor');
    });

    $this->withHeader('Accept-Language', 'en');
    $this->patientToken = Patient::factory()->create()->user->createToken('api')->plainTextToken;
    $this->doctorToken = User::factory()->doctor()->create()->createToken('api')->plainTextToken;
    $this->assistantToken = User::factory()->assistant()->create()->createToken('api')->plainTextToken;
});

it('lets an assistant into assistant routes only', function () {
    $this->withToken($this->assistantToken)->getJson('/api/v1/assistant/_ping')->assertOk();

    foreach (['patient', 'doctor', 'shared'] as $area) {
        app('auth')->forgetGuards();
        $this->withToken($this->assistantToken)->getJson("/api/v1/{$area}/_ping")->assertForbidden();
    }

    foreach ([$this->patientToken, $this->doctorToken] as $token) {
        app('auth')->forgetGuards();
        $this->withToken($token)->getJson('/api/v1/assistant/_ping')->assertForbidden();
    }
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

it('protects the real /patient, /doctor and /assistant route groups', function () {
    $routes = collect(Route::getRoutes()->getRoutes())
        ->filter(fn ($route) => preg_match('#^api/v1/(patient|doctor|assistant)/#', $route->uri()) && ! str_contains($route->uri(), '_ping'));

    expect($routes->filter(fn ($route) => str_starts_with($route->uri(), 'api/v1/assistant/')))->not->toBeEmpty();

    $routes->each(function ($route) {
        preg_match('#^api/v1/(patient|doctor|assistant)/#', $route->uri(), $match);

        expect($route->gatherMiddleware())->toContain('auth:sanctum', "role:{$match[1]}");
    });
});
