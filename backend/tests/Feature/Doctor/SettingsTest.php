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
            'data' => [
                'slot_duration_minutes' => 45, 'booking_window_days' => 30, 'cancel_cutoff_hours' => 2,
                // T9-06: prescription print header, empty until the doctor fills it.
                'clinic_name' => null, 'doctor_title' => null, 'clinic_address' => null, 'clinic_phone' => null,
                'prescription_footer' => null, 'prescription_paper' => 'A5',
            ],
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

describe('prescription print header (T9-06)', function () {
    function printHeader(array $overrides = []): array
    {
        return [
            'clinic_name' => 'عيادة الابتسامة',
            'doctor_title' => 'أخصائي طب وجراحة الفم والأسنان',
            'clinic_address' => '12 شارع التحرير، الجيزة',
            'clinic_phone' => '0233334444',
            'prescription_footer' => 'السبت – الخميس، 5 – 9 مساءً',
            'prescription_paper' => 'A4',
            ...$overrides,
        ];
    }

    it('saves the fields and reads them back', function () {
        $this->actingAs($this->doctor)->putJson('/api/v1/doctor/settings', printHeader())
            ->assertOk()
            ->assertJsonPath('message', 'Settings saved.');

        $this->actingAs($this->doctor)->getJson('/api/v1/doctor/settings')
            ->assertOk()
            ->assertJson(['data' => printHeader()])
            // The booking settings keep their values.
            ->assertJsonPath('data.slot_duration_minutes', 45)
            ->assertJsonPath('data.cancel_cutoff_hours', 2);
    });

    it('keeps the header when only the booking settings are saved', function () {
        $this->actingAs($this->doctor)->putJson('/api/v1/doctor/settings', printHeader())->assertOk();

        $this->actingAs($this->doctor)->putJson('/api/v1/doctor/settings', [
            'slot_duration_minutes' => 30, 'booking_window_days' => 14, 'cancel_cutoff_hours' => 6,
        ])->assertOk()
            ->assertJsonPath('data.slot_duration_minutes', 30)
            ->assertJsonPath('data.clinic_name', 'عيادة الابتسامة')
            ->assertJsonPath('data.prescription_paper', 'A4');
    });

    it('clears a header field sent empty', function () {
        $this->actingAs($this->doctor)->putJson('/api/v1/doctor/settings', printHeader())->assertOk();

        $this->actingAs($this->doctor)->putJson('/api/v1/doctor/settings', ['clinic_phone' => ''])
            ->assertOk()
            ->assertJsonPath('data.clinic_phone', null)
            ->assertJsonPath('data.clinic_name', 'عيادة الابتسامة');
    });

    it('validates the paper size and lengths', function (array $overrides, string $field) {
        $this->actingAs($this->doctor)->putJson('/api/v1/doctor/settings', printHeader($overrides))
            ->assertUnprocessable()
            ->assertJsonValidationErrors([$field]);

        expect(DoctorSetting::first()?->clinic_name)->toBeNull();
    })->with([
        'paper B5' => [['prescription_paper' => 'B5'], 'prescription_paper'],
        'paper lower case' => [['prescription_paper' => 'a5'], 'prescription_paper'],
        'paper null' => [['prescription_paper' => null], 'prescription_paper'],
        'clinic name 151' => [['clinic_name' => str_repeat('a', 151)], 'clinic_name'],
        'footer 256' => [['prescription_footer' => str_repeat('a', 256)], 'prescription_footer'],
    ]);

    it('names the field in Arabic', function () {
        $this->actingAs($this->doctor)->putJson('/api/v1/doctor/settings', printHeader(['prescription_paper' => 'B5']), ['Accept-Language' => 'ar'])
            ->assertJsonPath('errors.prescription_paper.0', 'قيمة حقل مقاس الورق غير موجودة في قائمة القيم المسموح بها.');
    });
});

it('is for the doctor only', function () {
    $this->actingAs(Patient::factory()->create()->user)->getJson('/api/v1/doctor/settings')->assertForbidden();
});
