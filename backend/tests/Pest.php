<?php

use App\Enums\PaymentMethod;
use App\Models\Appointment;
use App\Models\Drug;
use App\Models\MedicalHistoryEntry;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\Prescription;
use App\Models\PrescriptionItem;
use App\Models\User;
use App\Models\Visit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Smalot\PdfParser\Parser;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}

/**
 * Phase 10: one fully filled visit for the visit report tests — header,
 * appointment, two payments, a linked prescription — plus data that must
 * never reach the file: history entries, current illness, drug warnings,
 * another visit's prescription. The secret texts all start with "SECRET-".
 *
 * @return array{doctor: User, assistant: User, patient: Patient, visit: Visit, otherVisit: Visit, drug: Drug}
 */
function visitReportFixture(): array
{
    $doctor = User::factory()->doctor()->create(['name' => 'Dr. Mona Adel']);
    $doctor->doctorSetting()->create([
        'clinic_name' => 'Smile Dental Clinic',
        'doctor_title' => 'Dentist',
        'clinic_address' => '12 Tahrir St, Cairo',
        'clinic_phone' => '0225550000',
    ]);
    $assistant = User::factory()->assistant()->create(['name' => 'Sara Hassan']);

    $patient = Patient::factory()
        ->for(User::factory()->patient()->state(['name' => 'Ahmed Ali', 'phone' => '01012345678']))
        ->create(['date_of_birth' => '1990-03-15', 'current_illness' => 'SECRET-ILLNESS toothache since Monday']);
    MedicalHistoryEntry::factory()->for($patient)->create(['title' => 'SECRET-HISTORY-PRIVATE', 'details' => 'SECRET-DETAILS', 'patient_visible' => false]);
    MedicalHistoryEntry::factory()->for($patient)->create(['title' => 'SECRET-HISTORY-VISIBLE', 'patient_visible' => true]);

    $appointment = Appointment::factory()->for($patient)->for($doctor, 'doctor')->at('2026-10-07 10:00')->completed()->create();
    $visit = Visit::factory()->for($patient)->create([
        'appointment_id' => $appointment->id,
        'visit_date' => '2026-10-07',
        'work_done' => "Root canal, upper left 6\nTemporary filling",
        'total_amount' => 1500,
    ]);
    Payment::factory()->for($visit)->create(['amount' => 500, 'method' => PaymentMethod::Cash, 'paid_at' => '2026-10-07 10:40', 'recorded_by' => $doctor->id]);
    Payment::factory()->for($visit)->create(['amount' => 300, 'method' => PaymentMethod::Card, 'paid_at' => '2026-10-08 12:00', 'recorded_by' => $assistant->id]);

    $drug = Drug::factory()->create(['trade_name' => 'Augmentin 1 g', 'form' => 'tablets', 'uses' => 'SECRET-USES', 'warnings' => 'SECRET-WARNING']);
    $rx = Prescription::factory()->forVisit($visit)->create(['doctor_id' => $doctor->id, 'notes' => 'SECRET-RX-NOTES']);
    PrescriptionItem::factory()->for($rx)->forDrug($drug)->create(['instructions' => '1 tablet every 12 hours for 5 days', 'position' => 1]);
    PrescriptionItem::factory()->for($rx)->create(['drug_name' => 'Brufen 400 mg', 'drug_form' => null, 'instructions' => 'After meals when needed', 'position' => 2]);

    // Another, unpaid visit of the same patient with its own prescription.
    $otherVisit = Visit::factory()->for($patient)->create(['visit_date' => '2026-09-01', 'total_amount' => 1000, 'work_done' => 'SECRET-OTHER-VISIT scaling']);
    $otherRx = Prescription::factory()->forVisit($otherVisit)->create(['doctor_id' => $doctor->id, 'notes' => null]);
    PrescriptionItem::factory()->for($otherRx)->create(['drug_name' => 'SECRET-OTHER-DRUG Flagyl', 'position' => 1]);

    return compact('doctor', 'assistant', 'patient', 'visit', 'otherVisit', 'drug');
}

/**
 * The text of a PDF, NFKC-normalized so Arabic presentation forms become
 * plain letters. mPDF writes Arabic in visual order, so look for an Arabic
 * phrase with pdfArabic().
 */
function pdfText(string $pdf): string
{
    return Normalizer::normalize((new Parser)->parseContent($pdf)->getText(), Normalizer::FORM_KC);
}

/**
 * An Arabic phrase as it appears in pdfText() (visual, right-to-left order).
 * Only for phrases without a lam-alef ligature.
 */
function pdfArabic(string $phrase): string
{
    return implode('', array_reverse(mb_str_split($phrase)));
}
