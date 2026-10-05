<?php

it('returns ok and the server time', function () {
    $response = $this->getJson('/api/v1/health');

    $response->assertOk()->assertJson(['status' => 'ok']);
    expect($response->json('time'))->toBeString();
    expect(\Carbon\Carbon::parse($response->json('time'))->isValid())->toBeTrue();
});

it('allows the frontend origin through CORS', function () {
    $origin = config('cors.allowed_origins')[0];

    $this->withHeaders(['Origin' => $origin, 'Access-Control-Request-Method' => 'GET'])
        ->options('/api/v1/health')
        ->assertNoContent()
        ->assertHeader('Access-Control-Allow-Origin', $origin);
});

it('uses the Cairo time zone', function () {
    expect(config('app.timezone'))->toBe('Africa/Cairo');
});
