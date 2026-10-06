<?php

use App\Enums\PaymentMethod;
use App\Http\Resources\VisitResource;
use App\Models\Patient;
use App\Models\User;
use App\Models\Visit;
use App\Services\PaymentService;
use App\Services\VisitReport\VisitReportData;
use App\Services\VisitReport\VisitReportService;
use Illuminate\Support\Carbon;

// T10-02: VisitReportService builds the one data object both file formats use (FR-K.2, VR-2, VR-3).

beforeEach(function () {
    $this->service = app(VisitReportService::class);
});

afterEach(fn () => Carbon::setTestNow());

it('builds every section of a visit with two payments and a linked prescription', function () {
    Carbon::setTestNow('2026-10-09 15:30:00');
    ['visit' => $visit] = visitReportFixture();

    $data = $this->service->build($visit, 'en');

    expect($data->locale)->toBe('en')
        ->and($data->rtl)->toBeFalse()
        ->and($data->title)->toBe('Visit report — 7 Oct 2026')
        ->and($data->header)->toBe([
            'clinic_name' => 'Smile Dental Clinic',
            'doctor_name' => 'Dr. Mona Adel',
            'doctor_title' => 'Dentist',
            'address' => '12 Tahrir St, Cairo',
            'phone' => '0225550000',
        ])
        ->and($data->patient)->toBe(['name' => 'Ahmed Ali', 'phone' => '01012345678', 'age' => 36])
        ->and($data->visit)->toBe([
            'id' => $visit->id,
            'date' => '7 Oct 2026',
            'time' => '10:00 – 10:45',
            'walk_in' => false,
            'payment_status' => 'Partially paid',
        ])
        ->and($data->workDone)->toBe("Root canal, upper left 6\nTemporary filling")
        ->and($data->money)->toBe([
            'total' => '1,500.00 EGP',
            'paid' => '800.00 EGP',
            'remaining' => '700.00 EGP',
            'has_remaining' => true,
        ])
        ->and($data->payments)->toBe([
            ['date' => '7 Oct 2026', 'amount' => '500.00 EGP', 'method' => 'Cash', 'recorded_by' => 'Dr. Mona Adel'],
            ['date' => '8 Oct 2026', 'amount' => '300.00 EGP', 'method' => 'Card', 'recorded_by' => 'Sara Hassan'],
        ])
        // 700 left on this visit + 1000 on the other one (FR-D.7).
        ->and($data->outstanding)->toBe('1,700.00 EGP')
        ->and($data->hasOutstanding)->toBeTrue()
        ->and($data->prescriptions)->toBe([[
            'issued_on' => '7 Oct 2026',
            'items' => [
                ['drug_name' => 'Augmentin 1 g', 'drug_form' => 'tablets', 'instructions' => '1 tablet every 12 hours for 5 days'],
                ['drug_name' => 'Brufen 400 mg', 'drug_form' => null, 'instructions' => 'After meals when needed'],
            ],
        ]])
        ->and($data->generatedAt)->toBe('9 Oct 2026 15:30')
        ->and($data->labels['work_done'])->toBe('Work done today')
        ->and($data->labels['signature'])->toBe('Signature');
});

it('builds the Arabic file whatever the request locale is', function () {
    app()->setLocale('en');
    ['visit' => $visit] = visitReportFixture();

    $data = $this->service->build($visit, 'ar');

    expect($data->rtl)->toBeTrue()
        ->and($data->labels['work_done'])->toBe('العمل المنجز اليوم')
        ->and($data->labels['remaining'])->toBe('المتبقي')
        ->and($data->visit['date'])->toBe('7 أكتوبر 2026')
        ->and($data->visit['payment_status'])->toBe('مدفوع جزئيًا')
        ->and($data->money['total'])->toBe('1,500.00 ج.م')
        ->and($data->payments[1]['method'])->toBe('بطاقة')
        ->and(app()->getLocale())->toBe('en');
});

it('takes paid and remaining from PaymentService, as VisitResource does, also after an installment', function () {
    Carbon::setTestNow('2026-10-20 11:00:00');
    ['visit' => $visit, 'doctor' => $doctor] = visitReportFixture();

    $sameAsApi = function () use ($visit) {
        $api = VisitResource::make(Visit::findOrFail($visit->id))->resolve();
        $data = $this->service->build(Visit::findOrFail($visit->id), 'en');

        expect($data->money['total'])->toBe(VisitReportService::money($api['total_amount'], 'en'))
            ->and($data->money['paid'])->toBe(VisitReportService::money($api['paid'], 'en'))
            ->and($data->money['remaining'])->toBe(VisitReportService::money($api['remaining'], 'en'));

        return $data;
    };

    expect($sameAsApi()->money['remaining'])->toBe('700.00 EGP');

    app(PaymentService::class)->addPayment($visit, 700, PaymentMethod::Wallet, recordedBy: $doctor);

    $after = $sameAsApi();
    expect($after->money)->toMatchArray(['paid' => '1,500.00 EGP', 'remaining' => '0.00 EGP', 'has_remaining' => false])
        ->and($after->visit['payment_status'])->toBe('Paid')
        ->and($after->payments)->toHaveCount(3)
        ->and($after->payments[2]['method'])->toBe('Wallet')
        ->and($after->outstanding)->toBe('1,000.00 EGP');
});

it('leaves out a walk-in time, an unknown age and empty header parts', function () {
    User::factory()->doctor()->create(['name' => 'Dr. Mona Adel']); // no settings row at all
    $patient = Patient::factory()->create(['date_of_birth' => null]);
    $visit = Visit::factory()->for($patient)->create(['appointment_id' => null, 'total_amount' => 0, 'work_done' => '']);

    $data = $this->service->build($visit, 'en');

    expect($data->header)->toBe(['doctor_name' => 'Dr. Mona Adel'])
        ->and($data->patient['age'])->toBeNull()
        ->and($data->visit['time'])->toBeNull()
        ->and($data->visit['walk_in'])->toBeTrue()
        ->and($data->labels['walk_in'])->toBe('Walk-in')
        ->and($data->visit['payment_status'])->toBe('Paid')
        ->and($data->money['remaining'])->toBe('0.00 EGP')
        ->and($data->payments)->toBe([])
        ->and($data->prescriptions)->toBe([]);
});

it('drops blank header parts from the settings', function () {
    $doctor = User::factory()->doctor()->create(['name' => 'Dr. Mona Adel']);
    $doctor->doctorSetting()->create(['clinic_name' => 'Smile', 'doctor_title' => '', 'clinic_address' => '  ', 'clinic_phone' => null]);

    $data = $this->service->build(Visit::factory()->create(), 'en');

    expect($data->header)->toBe(['clinic_name' => 'Smile', 'doctor_name' => 'Dr. Mona Adel']);
});

it('has no medical history, illness or drug note fields (FR-K.3)', function () {
    ['visit' => $visit] = visitReportFixture();

    $properties = array_map(fn (ReflectionProperty $p) => $p->getName(), (new ReflectionClass(VisitReportData::class))->getProperties());
    expect($properties)->toBe([
        'locale', 'rtl', 'title', 'labels', 'header', 'patient', 'visit', 'workDone',
        'money', 'payments', 'outstanding', 'hasOutstanding', 'prescriptions', 'generatedAt',
    ]);

    foreach (['ar', 'en'] as $locale) {
        $json = json_encode($this->service->build(Visit::findOrFail($visit->id), $locale)->toArray(), JSON_UNESCAPED_UNICODE);
        expect($json)->not->toContain('SECRET-');
    }
});

it('formats money with grouping and two decimals without floats', function (string $amount, string $en, string $ar) {
    expect(VisitReportService::money($amount, 'en'))->toBe($en)
        ->and(VisitReportService::money($amount, 'ar'))->toBe($ar);
})->with([
    ['0', '0.00 EGP', '0.00 ج.م'],
    ['999.5', '999.50 EGP', '999.50 ج.م'],
    ['1500', '1,500.00 EGP', '1,500.00 ج.م'],
    ['12345678.90', '12,345,678.90 EGP', '12,345,678.90 ج.م'],
    ['-250.00', '-250.00 EGP', '-250.00 ج.م'],
]);
