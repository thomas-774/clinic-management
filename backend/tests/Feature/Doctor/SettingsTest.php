<?php

use App\Models\DoctorSetting;
use App\Models\Patient;
use App\Models\User;

beforeEach(function () {
    $this->withHeader('Accept-Language', 'en');
    $this->doctor = User::factory()->doctor()->create();
});

it('returns the defaults, creating the row if it is missing', function () {
    $this->actingAs($this->doctor)->getJson('/api/v1/doctor/settings')
        ->assertOk()
        ->assertExactJson([
            'data' => ['slot_duration_minutes' => 45, 'booking_window_days' => 30, 'cancel_cutoff_hours' => 2],
            'message' => null,
        ]);

    expect(DoctorSetting::count())->toBe(1);
});

it('saves a 60-minute duration and a 24-hour cut-off', function () {
    $this->actingAs($this->doctor)->putJson('/api/v1/doctor/settings', [
        'slot_duration_minutes' => 60,
        'booking_window_days' => 30,
        'cancel_cutoff_hours' => 24,
    ])->assertOk()->assertJsonPath('message', 'Settings saved.');

    $this->actingAs($this->doctor)->getJson('/api/v1/doctor/settings')
        ->assertJsonPath('data.slot_duration_minutes', 60)
        ->assertJsonPath('data.cancel_cutoff_hours', 24);
    expect(DoctorSetting::count())->toBe(1);
});

it('validates the ranges', function (string $field, mixed $value) {
    $data = ['slot_duration_minutes' => 45, 'booking_window_days' => 30, 'cancel_cutoff_hours' => 2, $field => $value];

    $this->actingAs($this->doctor)->putJson('/api/v1/doctor/settings', $data)
        ->assertUnprocessable()
        ->assertJsonValidationErrors([$field]);
})->with([
    ['slot_duration_minutes', 9],
    ['slot_duration_minutes', 241],
    ['slot_duration_minutes', 45.5],
    ['booking_window_days', 0],
    ['booking_window_days', 91],
    ['cancel_cutoff_hours', -1],
    ['cancel_cutoff_hours', 73],
    ['cancel_cutoff_hours', null],
]);

it('accepts a cut-off of 0 hours', function () {
    $this->actingAs($this->doctor)->putJson('/api/v1/doctor/settings', [
        'slot_duration_minutes' => 30, 'booking_window_days' => 1, 'cancel_cutoff_hours' => 0,
    ])->assertOk()->assertJsonPath('data.cancel_cutoff_hours', 0);
});

it('is for the doctor only', function () {
    $this->actingAs(Patient::factory()->create()->user)->getJson('/api/v1/doctor/settings')->assertForbidden();
});
