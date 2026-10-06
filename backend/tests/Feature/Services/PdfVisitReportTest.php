<?php

use App\Enums\PaymentMethod;
use App\Models\Payment;
use App\Models\Visit;
use App\Services\VisitReport\PdfVisitReport;
use App\Services\VisitReport\VisitReportService;
use Smalot\PdfParser\Parser;

// T10-03: the visit report as an A4 PDF (FR-K.2, FR-K.5, VR-4).

function visitPdf(Visit $visit, string $locale): string
{
    return app(PdfVisitReport::class)->render(app(VisitReportService::class)->build(Visit::findOrFail($visit->id), $locale));
}

it('holds the work done, the three amounts and the patient in both languages', function (string $locale, string $egp) {
    ['visit' => $visit] = visitReportFixture();

    $pdf = visitPdf($visit, $locale);
    $text = pdfText($pdf);

    expect($pdf)->toStartWith('%PDF')
        ->and($text)->toContain('Root canal, upper left 6')
        ->and($text)->toContain('Temporary filling')
        ->and($text)->toContain('Ahmed Ali')
        ->and($text)->toContain('01012345678')
        ->and($text)->toContain('Augmentin 1 g')
        ->and($text)->toContain('1 / 1')
        ->and($text)->not->toContain('SECRET-');
    foreach (['1,500.00', '800.00', '700.00', '1,700.00'] as $amount) {
        expect($text)->toContain($amount);
    }
    expect($text)->toContain($egp);
})->with([
    'en' => ['en', '1,500.00 EGP'],
    'ar' => ['ar', '1,500.00 م.ج'], // "ج.م" in visual order
]);

it('uses the language of the file for its labels', function () {
    ['visit' => $visit] = visitReportFixture();

    expect(pdfText(visitPdf($visit, 'en')))->toContain('Work done today')->toContain('Overall outstanding balance')
        ->and(pdfText(visitPdf($visit, 'ar')))->toContain(pdfArabic('العمل المنجز اليوم'))->not->toContain('Work done today');
});

it('is A4 portrait with the visit date in its title', function () {
    ['visit' => $visit] = visitReportFixture();

    $document = (new Parser)->parseContent(visitPdf($visit, 'en'));
    $page = $document->getPages()[0]->getDetails();

    expect($document->getDetails()['Title'])->toBe('Visit report — 7 Oct 2026')
        ->and($document->getDetails()['Creator'])->toBe('Smile Dental Clinic')
        // A4 = 210 × 297 mm = 595.28 × 841.89 pt.
        ->and(array_map(fn ($v) => round((float) $v), $page['MediaBox']))->toBe([0.0, 0.0, 595.0, 842.0]);
});

it('runs a long payments table onto a second page with its header row repeated', function () {
    ['visit' => $visit, 'doctor' => $doctor] = visitReportFixture();
    $visit->update(['total_amount' => 20000]);
    foreach (range(1, 18) as $i) {
        Payment::factory()->for($visit)->create(['amount' => 500, 'method' => PaymentMethod::Cash, 'paid_at' => "2026-10-09 10:{$i}", 'recorded_by' => $doctor->id]);
    }

    $document = (new Parser)->parseContent(visitPdf($visit, 'en'));
    $pages = $document->getPages();

    expect($pages)->toHaveCount(2)
        ->and($pages[0]->getText())->toContain('Recorded by')->toContain('1 / 2')
        ->and($pages[1]->getText())->toContain('Recorded by')->toContain('2 / 2');
});

it('leaves out the prescriptions section when the visit has none', function () {
    ['otherVisit' => $visit] = visitReportFixture();
    $visit->prescriptions()->each(fn ($rx) => $rx->delete());

    $text = pdfText(visitPdf($visit, 'en'));

    expect($text)->not->toContain('Prescriptions')
        ->and($text)->toContain('No payments yet.');
});
