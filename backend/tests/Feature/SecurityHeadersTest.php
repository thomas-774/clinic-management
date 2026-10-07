<?php

use App\Http\Middleware\SecurityHeaders;
use App\Models\User;

/*
 * Security headers on every API response (T11-01, NFR-S.1).
 */

function assertSecurityHeaders($response): void
{
    foreach (SecurityHeaders::HEADERS as $name => $value) {
        $response->assertHeader($name, $value);
    }
    $response->assertHeader('Content-Security-Policy', SecurityHeaders::API_CSP);
}

function assertNotStored($response): void
{
    expect($response->headers->get('Cache-Control'))->toContain('no-store')
        ->and($response->headers->get('Pragma'))->toBe('no-cache');
}

function bearer(User $user): array
{
    return ['Authorization' => 'Bearer '.$user->createToken('test')->plainTextToken];
}

it('sends the headers to a guest, without no-store', function () {
    $response = $this->getJson('/api/v1/health')->assertOk();

    assertSecurityHeaders($response);
    expect($response->headers->get('Cache-Control'))->not->toContain('no-store')
        ->and($response->headers->has('Pragma'))->toBeFalse();
});

it('sends the headers and no-store to a logged-in user', function () {
    $response = $this->getJson('/api/v1/me', bearer(User::factory()->doctor()->create()))->assertOk();

    assertSecurityHeaders($response);
    assertNotStored($response);
});

it('sends the headers on errors: 404, 401 and a logged-in 403', function () {
    $notFound = $this->getJson('/api/v1/does-not-exist')->assertNotFound();
    assertSecurityHeaders($notFound);
    expect($notFound->headers->get('Cache-Control'))->not->toContain('no-store');

    assertSecurityHeaders($this->getJson('/api/v1/me')->assertUnauthorized());

    $forbidden = $this->getJson('/api/v1/doctor/patients', bearer(User::factory()->patient()->create()))->assertForbidden();
    assertSecurityHeaders($forbidden);
    assertNotStored($forbidden);
});

it('keeps the visit file a download, with no-store', function () {
    ['doctor' => $doctor, 'visit' => $visit] = visitReportFixture();

    $response = $this->get("/api/v1/doctor/visits/{$visit->id}/export?format=pdf", bearer($doctor))->assertOk();

    assertSecurityHeaders($response);
    assertNotStored($response);
    expect($response->headers->get('Content-Disposition'))->toStartWith('attachment; filename="visit-');
});

// T11-07 (ASVS 14.4.2): a JSON response opened in a browser tab is saved, not shown.
it('marks JSON responses as a download named api.json', function () {
    $this->getJson('/api/v1/health')->assertHeader('Content-Disposition', SecurityHeaders::API_DISPOSITION);
    $this->getJson('/api/v1/does-not-exist')->assertHeader('Content-Disposition', SecurityHeaders::API_DISPOSITION);
});

it('leaves the welcome page without the API headers', function () {
    $response = $this->get('/');

    expect($response->headers->has('Content-Disposition'))->toBeFalse()
        ->and($response->headers->has('Content-Security-Policy'))->toBeFalse();
});

it('sends HSTS only outside local and testing', function (string $env, bool $hsts) {
    $this->app['env'] = $env;

    $response = $this->getJson('/api/v1/health');

    expect($response->headers->get('Strict-Transport-Security'))->toBe($hsts ? SecurityHeaders::HSTS : null);
})->with([
    'testing' => ['testing', false],
    'local' => ['local', false],
    'production' => ['production', true],
    'staging' => ['staging', true],
]);
