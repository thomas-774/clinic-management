<?php

use App\Models\BlockedTime;
use App\Models\Patient;
use App\Models\User;

beforeEach(function () {
    $this->withHeader('Accept-Language', 'en');
    $this->travelTo('2026-10-05 12:00:00');
    $this->doctor = User::factory()->doctor()->create();
});

it('creates and deletes a whole-day block', function () {
    $id = $this->actingAs($this->doctor)->postJson('/api/v1/doctor/blocked-times', ['date' => '2026-10-06', 'reason' => 'Holiday'])
        ->assertCreated()
        ->assertJsonPath('message', 'Time blocked.')
        ->assertJsonPath('data.whole_day', true)
        ->assertJsonPath('data.start_time', null)
        ->assertJsonPath('data.reason', 'Holiday')
        ->json('data.id');

    $this->actingAs($this->doctor)->deleteJson("/api/v1/doctor/blocked-times/{$id}")
        ->assertOk()
        ->assertJsonPath('message', 'Block removed.');
    expect(BlockedTime::count())->toBe(0);
});

it('creates a time-range block', function () {
    $this->actingAs($this->doctor)->postJson('/api/v1/doctor/blocked-times', [
        'date' => '2026-10-05', 'start_time' => '18:00', 'end_time' => '19:00',
    ])->assertCreated()
        ->assertJsonPath('data.whole_day', false)
        ->assertJsonPath('data.date', '2026-10-05')
        ->assertJsonPath('data.start_time', '18:00')
        ->assertJsonPath('data.end_time', '19:00');
});

it('lists blocks from today onward, whole days first', function () {
    $blocks = $this->doctor->blockedTimes();
    $blocks->create(['date' => '2026-10-04', 'reason' => 'Past']);
    $blocks->create(['date' => '2026-10-07', 'start_time' => '18:00', 'end_time' => '19:00', 'reason' => 'Range']);
    $blocks->create(['date' => '2026-10-07', 'reason' => 'Whole day']);
    $blocks->create(['date' => '2026-10-20', 'reason' => 'Later']);
    BlockedTime::create(['doctor_id' => User::factory()->doctor()->create()->id, 'date' => '2026-10-08', 'reason' => 'Other doctor']);

    $reasons = fn (string $query = '') => $this->actingAs($this->doctor)
        ->getJson("/api/v1/doctor/blocked-times{$query}")->json('data.*.reason');

    expect($reasons())->toBe(['Whole day', 'Range', 'Later'])
        ->and($reasons('?from=2026-10-01&to=2026-10-10'))->toBe(['Past', 'Whole day', 'Range']);
});

it('requires both times or neither, with the end after the start', function () {
    $post = fn (array $data) => $this->actingAs($this->doctor)->postJson('/api/v1/doctor/blocked-times', ['date' => '2026-10-06', ...$data]);

    $post(['start_time' => '18:00'])->assertJsonValidationErrors(['end_time']);
    $post(['end_time' => '18:00'])->assertJsonValidationErrors(['start_time']);
    $post(['start_time' => '19:00', 'end_time' => '18:00'])->assertJsonValidationErrors(['end_time']);
});

it('rejects a date in the past', function () {
    $this->actingAs($this->doctor)->postJson('/api/v1/doctor/blocked-times', ['date' => '2026-10-04'])
        ->assertJsonValidationErrors(['date']);
});

it('does not delete another doctor\'s block', function () {
    $foreign = BlockedTime::create(['doctor_id' => User::factory()->doctor()->create()->id, 'date' => '2026-10-08']);

    $this->actingAs($this->doctor)->deleteJson("/api/v1/doctor/blocked-times/{$foreign->id}")->assertNotFound();
    expect($foreign->fresh())->not->toBeNull();
});

it('is for the doctor only', function () {
    $this->actingAs(Patient::factory()->create()->user)
        ->postJson('/api/v1/doctor/blocked-times', ['date' => '2026-10-06'])
        ->assertForbidden();
});
