<?php

use App\Models\User;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Route;

/*
 * Rate limits on writes, search and register (T11-02, NFR-S.2).
 */

/** An empty POST: rejected by validation (422), but counted by the limiter. */
function emptyWrite(User $user, string $language = 'en')
{
    return test()->actingAs($user)->postJson('/api/v1/doctor/blocked-times', [], ['Accept-Language' => $language]);
}

it('keeps the limits in config', function () {
    expect(config('clinic.rate_limits'))->toBe(['login' => 5, 'register' => 5, 'writes' => 60, 'search' => 120]);
});

it('answers the 61st write in a minute with 429, Retry-After and a translated message', function (string $language, string $message) {
    $doctor = User::factory()->doctor()->create();

    foreach (range(1, 60) as $_) {
        emptyWrite($doctor)->assertUnprocessable();
    }

    $response = emptyWrite($doctor, $language)->assertTooManyRequests();

    expect((int) $response->headers->get('Retry-After'))->toBeGreaterThan(0)
        ->and($response->json())->toBe(['message' => str_replace(':seconds', $response->headers->get('Retry-After'), $message)]);
})->with([
    'en' => ['en', 'Too many requests. Please try again in :seconds seconds.'],
    'ar' => ['ar', 'طلبات كثيرة جدًا. يرجى المحاولة مرة أخرى بعد :seconds ثانية.'],
]);

it('counts writes per user and never counts reads', function () {
    $doctor = User::factory()->doctor()->create();
    $assistant = User::factory()->assistant()->create();

    foreach (range(1, 60) as $_) {
        emptyWrite($doctor);
    }
    emptyWrite($doctor)->assertTooManyRequests();

    // Reads by the same user still pass.
    $this->actingAs($doctor)->getJson('/api/v1/doctor/settings')->assertOk();
    // Another user is not affected.
    $this->actingAs($assistant)->postJson('/api/v1/assistant/patients', [])->assertUnprocessable();
    // The limit resets after a minute.
    $this->travel(61)->seconds();
    emptyWrite($doctor)->assertUnprocessable();
});

it('limits searches per user', function () {
    config(['clinic.rate_limits.search' => 3]);
    $doctor = User::factory()->doctor()->create();

    foreach (range(1, 3) as $_) {
        $this->actingAs($doctor)->getJson('/api/v1/doctor/drugs/search?q=amox')->assertOk();
    }
    $this->actingAs($doctor)->getJson('/api/v1/doctor/drugs/search?q=amox')->assertTooManyRequests();
    // One budget for all of a user's searches.
    $this->actingAs($doctor)->getJson('/api/v1/doctor/patients?search=ali')->assertTooManyRequests();
    $this->actingAs(User::factory()->doctor()->create())->getJson('/api/v1/doctor/drugs/search?q=amox')->assertOk();
});

it('limits register to 5 a minute per IP', function () {
    $register = fn (string $ip) => $this->withServerVariables(['REMOTE_ADDR' => $ip])->postJson('/api/v1/auth/register', []);

    foreach (range(1, 5) as $_) {
        $register('10.0.0.1')->assertUnprocessable();
    }
    $register('10.0.0.1')->assertTooManyRequests()->assertJsonStructure(['message']);
    $register('10.0.0.2')->assertUnprocessable();
});

/** Every API route, with its HTTP methods and middleware. */
function apiRoutes(): array
{
    return collect(Route::getRoutes()->getRoutes())
        ->filter(fn (RoutingRoute $route) => str_starts_with($route->uri(), 'api/'))
        ->values()
        ->all();
}

it('throttles every write route', function () {
    $unthrottled = collect(apiRoutes())
        ->reject(fn (RoutingRoute $route) => array_diff($route->methods(), ['GET', 'HEAD']) === [])
        ->reject(fn (RoutingRoute $route) => collect($route->gatherMiddleware())->contains(fn ($m) => is_string($m) && str_starts_with($m, 'throttle:')))
        ->map(fn (RoutingRoute $route) => implode('|', $route->methods()).' '.$route->uri())
        ->values()
        ->all();

    expect($unthrottled)->toBe([]);
});

it('throttles patient search, drug search and slots with the search limiter', function (string $uri) {
    $route = collect(apiRoutes())->first(fn (RoutingRoute $route) => $route->uri() === $uri && in_array('GET', $route->methods()));

    expect($route->gatherMiddleware())->toContain('throttle:search');
})->with([
    'api/v1/slots',
    'api/v1/doctor/patients',
    'api/v1/assistant/patients',
    'api/v1/doctor/drugs/search',
    'api/v1/doctor/drugs',
]);
