<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

/*
 * T11-07 (ASVS V7.4.1, V14.3.2, NFR-S.7): with APP_DEBUG=false a server error
 * is a bare { message }: no exception class, file, stack trace or SQL.
 */

beforeEach(function () {
    config()->set('app.debug', false);
    $this->withHeader('Accept-Language', 'en');

    Route::middleware('api')->prefix('api/v1/_test')->group(function () {
        Route::get('/sql-error', fn () => DB::select('select secret_column from no_such_table where id = ?', [42]));
        Route::get('/crash', fn () => throw new RuntimeException('SECRET internal detail in '.__FILE__));
    });
});

it('hides the SQL of a failed query', function () {
    $response = $this->getJson('/api/v1/_test/sql-error');

    $response->assertStatus(500)->assertExactJson(['message' => 'Server Error']);
    expect($response->getContent())->not->toContain('no_such_table')
        ->not->toContain('secret_column')
        ->not->toContain('SQLSTATE');
});

it('hides the message, file and trace of any exception', function () {
    $response = $this->getJson('/api/v1/_test/crash');

    $response->assertStatus(500)->assertExactJson(['message' => 'Server Error']);
    expect($response->getContent())->not->toContain('SECRET')
        ->not->toContain('.php')
        ->not->toContain('trace');
});

it('hides them even when the client does not ask for JSON', function () {
    $response = $this->get('/api/v1/_test/sql-error');

    $response->assertStatus(500)->assertExactJson(['message' => 'Server Error']);
});

it('would show the details only with debug on (the switch the test relies on)', function () {
    config()->set('app.debug', true);

    expect($this->getJson('/api/v1/_test/sql-error')->assertStatus(500)->getContent())->toContain('no_such_table');
});
