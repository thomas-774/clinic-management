<?php

use App\Console\Commands\RunBenchmark;
use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\MedicalHistoryEntry;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\Prescription;
use App\Models\PrescriptionItem;
use App\Models\Visit;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\PerformanceSeeder;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

/**
 * T11-09: the 5-year dataset seeder and `clinic:bench`, on a small version of
 * the dataset (the real one is 10,000 patients over 5 years).
 */
beforeEach(function () {
    config(['clinic.doctor.phone' => '01000000000', 'clinic.doctor.password' => 'secret-password']);
});

function seedSmallDataset(): void
{
    $seeder = app(PerformanceSeeder::class);
    $seeder->patients = 60;
    $seeder->days = 21;
    $seeder->futureDays = 1;
    $seeder->analyze = false;
    $seeder->setContainer(app())->__invoke();
}

describe('PerformanceSeeder', function () {
    it('is not part of db:seed', function () {
        $this->seed(DatabaseSeeder::class);

        // DatabaseSeeder's PatientSeeder makes 10; the performance set makes thousands.
        expect(Patient::count())->toBe(10);
    });

    it('builds visits with installments, prescriptions and history, never over 50 a day', function () {
        seedSmallDataset();

        expect(Patient::count())->toBe(60)
            ->and(Visit::count())->toBeGreaterThan(18 * 25)
            ->and(Prescription::count())->toBeGreaterThan(0)
            ->and(PrescriptionItem::count())->toBeGreaterThan(0)
            ->and(MedicalHistoryEntry::count())->toBeGreaterThan(0)
            ->and(Visit::query()->has('payments', '>=', 2)->exists())->toBeTrue()
            ->and(Visit::query()->toBase()->selectRaw('COUNT(*) AS n')->groupBy('visit_date')->get()->max('n'))->toBeLessThanOrEqual(50);

        // PR-2: no visit is paid more than its total; no payment is in the future.
        $overpaid = Visit::query()->withSum('payments', 'amount')->get()
            ->filter(fn (Visit $v) => bccomp((string) $v->payments_sum_amount, (string) $v->total_amount, 2) > 0);
        expect($overpaid)->toBeEmpty()
            ->and(Payment::query()->where('paid_at', '>', now()->endOfDay())->exists())->toBeFalse();

        // Today (also in Egyptian summer time, when midnight is 01:00): past slots done, later ones booked.
        $today = Appointment::query()->whereBetween('start_at', [today(), today()->endOfDay()])->get();
        expect($today->filter(fn ($a) => $a->start_at->gt(now()))->every(fn ($a) => $a->status === AppointmentStatus::Booked))->toBeTrue()
            ->and($today->filter(fn ($a) => $a->start_at->lte(now()))->contains(fn ($a) => $a->status === AppointmentStatus::Booked))->toBeFalse();

        // BR-4: at most one future appointment per patient.
        expect(Appointment::query()->where('start_at', '>', now())->where('status', 'booked')
            ->toBase()->selectRaw('COUNT(*) AS n')->groupBy('patient_id')->get()->max('n'))->toBeLessThanOrEqual(1);
    });

    it('stores the medical text encrypted, as the app does', function () {
        seedSmallDataset();

        $visit = Visit::query()->firstOrFail();
        $raw = DB::table('visits')->where('id', $visit->id)->value('work_done');
        expect($raw)->not->toBe($visit->work_done)
            ->and(Crypt::decryptString($raw))->toBe($visit->work_done);

        $entry = MedicalHistoryEntry::query()->firstOrFail();
        expect(Crypt::decryptString(DB::table('medical_history_entries')->where('id', $entry->id)->value('title')))->toBe($entry->title);

        $item = PrescriptionItem::query()->firstOrFail();
        expect(Crypt::decryptString(DB::table('prescription_items')->where('id', $item->id)->value('instructions')))->toBe($item->instructions);
    });

    it('refuses to run in production', function () {
        $this->app['env'] = 'production';

        expect(fn () => seedSmallDataset())->toThrow(RuntimeException::class);
        expect(Patient::count())->toBe(0);
    });
});

describe('clinic:bench', function () {
    it('times every key endpoint and leaves the data as it found it', function () {
        seedSmallDataset();
        $counts = fn () => [Appointment::count(), Visit::count(), Payment::count()];
        $before = $counts();

        $this->artisan('clinic:bench', ['--iterations' => 2])
            ->expectsOutputToContain('patients list')
            ->expectsOutputToContain('patients search')
            ->expectsOutputToContain('patient details')
            ->expectsOutputToContain('slots for a day')
            ->expectsOutputToContain('booking')
            ->expectsOutputToContain('schedule day')
            ->expectsOutputToContain("today's queue")
            ->expectsOutputToContain('visit save')
            ->expectsOutputToContain('report day')
            ->expectsOutputToContain('report week')
            ->expectsOutputToContain('report month')
            ->expectsOutputToContain('drug search')
            ->expectsOutputToContain('visit export pdf')
            ->expectsOutputToContain('visit export word')
            ->doesntExpectOutputToContain('OVER')
            ->assertSuccessful();

        expect($counts())->toBe($before);
    });

    it('runs only the endpoints asked for', function () {
        seedSmallDataset();

        $this->artisan('clinic:bench', ['--iterations' => 1, '--only' => 'drug'])
            ->expectsOutputToContain('drug search')
            ->doesntExpectOutputToContain('patients list')
            ->assertSuccessful();
    });

    it('refuses to run in production', function () {
        $this->app['env'] = 'production';

        $this->artisan('clinic:bench')->assertFailed();
    });

    it('takes nearest-rank percentiles', function () {
        $times = range(1, 100);
        shuffle($times);

        expect(RunBenchmark::percentile($times, 50))->toBe(50.0)
            ->and(RunBenchmark::percentile($times, 95))->toBe(95.0)
            ->and(RunBenchmark::percentile($times, 99))->toBe(99.0)
            ->and(RunBenchmark::percentile([7.5], 99))->toBe(7.5);
    });
});
