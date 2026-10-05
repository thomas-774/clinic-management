<?php

use App\Http\Resources\ApiResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

class TestUserResource extends ApiResource
{
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'created_at' => $this->created_at];
    }
}

beforeEach(function () {
    Route::middleware('api')->prefix('api/v1/_test')->group(function () {
        Route::post('/validate', fn (Request $request) => $request->validate(['phone' => ['required']]));
        Route::get('/user/{user}', fn (User $user) => TestUserResource::make($user)->withMessage('Loaded.'));
        Route::get('/users', fn () => TestUserResource::collection(User::all()));
        Route::get('/conflict', fn () => abort(409, 'Slot no longer available.'));
        Route::get('/forbidden', fn () => abort(403));
        Route::get('/protected', fn () => 'secret')->middleware('auth:sanctum');
    });
});

describe('error responses', function () {
    it('returns a JSON 401 for a protected route without a token', function () {
        $this->getJson('/api/v1/_test/protected', ['Accept-Language' => 'ar'])
            ->assertUnauthorized()
            ->assertExactJson(['message' => 'غير مصرح بالدخول. يرجى تسجيل الدخول.']);
    });

    it('returns a JSON 401 even when the client does not ask for JSON', function () {
        $this->get('/api/v1/_test/protected', ['Accept-Language' => 'en'])
            ->assertUnauthorized()
            ->assertExactJson(['message' => 'Unauthenticated.']);
    });

    it('returns JSON for 403, 404 and 409', function () {
        $this->get('/api/v1/_test/forbidden', ['Accept-Language' => 'en'])
            ->assertForbidden()->assertExactJson(['message' => 'Forbidden']);
        $this->get('/api/v1/_test/user/999', ['Accept-Language' => 'en'])
            ->assertNotFound()->assertExactJson(['message' => 'Not Found']);
        $this->get('/api/v1/does-not-exist', ['Accept-Language' => 'en'])
            ->assertNotFound()->assertExactJson(['message' => 'Not Found']);
        $this->get('/api/v1/_test/conflict', ['Accept-Language' => 'en'])
            ->assertConflict()->assertExactJson(['message' => 'Slot no longer available.']);
    });
});

describe('localized messages', function () {
    // '' = no language preference (the test client otherwise sends en-us by default).
    it('returns Arabic validation messages by default and for Accept-Language: ar', function (string $language) {
        $this->postJson('/api/v1/_test/validate', [], ['Accept-Language' => $language])
            ->assertUnprocessable()
            ->assertJsonPath('errors.phone.0', 'حقل الهاتف مطلوب.');
    })->with(['', 'ar', 'ar-EG,ar;q=0.9']);

    it('returns English validation messages for Accept-Language: en', function () {
        $this->postJson('/api/v1/_test/validate', [], ['Accept-Language' => 'en-US,en;q=0.9'])
            ->assertUnprocessable()
            ->assertJsonStructure(['message', 'errors' => ['phone']])
            ->assertJsonPath('errors.phone.0', 'The phone field is required.');
    });

    it('falls back to Arabic for an unsupported language', function () {
        $this->postJson('/api/v1/_test/validate', [], ['Accept-Language' => 'fr'])
            ->assertJsonPath('errors.phone.0', 'حقل الهاتف مطلوب.');
    });
});

describe('success responses', function () {
    it('wraps a resource as { data, message }', function () {
        $user = User::factory()->create();

        $this->getJson("/api/v1/_test/user/{$user->id}")
            ->assertOk()
            ->assertExactJsonStructure(['data' => ['id', 'created_at'], 'message'])
            ->assertJsonPath('message', 'Loaded.');
    });

    it('wraps a collection as { data, message }', function () {
        User::factory()->count(2)->create();

        $this->getJson('/api/v1/_test/users')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('message', null);
    });

    it('serializes dates as ISO 8601 in Cairo time', function () {
        $this->travelTo('2026-10-06 17:00:00');
        $user = User::factory()->create();

        $this->getJson("/api/v1/_test/user/{$user->id}")
            ->assertJsonPath('data.created_at', '2026-10-06T17:00:00+03:00');
    });
});

it('keeps the clinic business constants in config', function () {
    expect(config('clinic.max_active_appointments'))->toBe(1);
});
