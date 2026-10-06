<?php

/*
 * GET /doctor/visits/{visit}/export?format=pdf|docx (T10-05, FR-K.1 – K.6).
 */

beforeEach(function () {
    $this->fixture = visitReportFixture();
    $this->doctor = $this->fixture['doctor'];
    $this->visit = $this->fixture['visit'];
});

function exportVisit(int $visitId, ?string $format, string $language = 'en')
{
    $query = $format === null ? '' : '?format='.$format;

    return test()->actingAs(test()->doctor)
        ->withHeader('Accept-Language', $language)
        ->get("/api/v1/doctor/visits/{$visitId}/export{$query}", ['Accept' => 'application/json']);
}

it('sends the file with its type, an ASCII file name and no caching', function (string $format, string $type, string $magic) {
    $response = exportVisit($this->visit->id, $format);

    $response->assertOk()
        ->assertHeader('Content-Type', $type)
        ->assertHeader('Content-Disposition', "attachment; filename=\"visit-2026-10-07-{$this->visit->id}.{$format}\"");
    expect($response->headers->get('Cache-Control'))->toContain('no-store')
        ->and($response->getContent())->toStartWith($magic)
        ->and((int) $response->headers->get('Content-Length'))->toBe(strlen($response->getContent()));
})->with([
    'pdf' => ['pdf', 'application/pdf', '%PDF'],
    'docx' => ['docx', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', "PK\x03\x04"],
]);

it('rejects a missing or unknown format with a localized 422', function (?string $format, string $language, string $message) {
    exportVisit($this->visit->id, $format, $language)
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['format'])
        ->assertJsonPath('errors.format.0', fn (string $m) => str_contains($m, $message));
})->with([
    'missing' => [null, 'en', 'file format'],
    'xls' => ['xls', 'en', 'file format'],
    'missing, Arabic' => [null, 'ar', 'صيغة الملف'],
]);

it('gives 404 for an unknown visit', function () {
    exportVisit($this->visit->id + 1000, 'pdf')->assertNotFound();
});

it('writes the file in the Accept-Language language', function () {
    $en = pdfText(exportVisit($this->visit->id, 'pdf', 'en')->getContent());
    $ar = pdfText(exportVisit($this->visit->id, 'pdf', 'ar')->getContent());

    expect($en)->toContain('Work done')
        ->and($ar)->toContain(pdfArabic('العمل المنجز اليوم'))
        ->and($ar)->not->toContain('Work done');
});

it('lets the frontend read the file name across origins', function () {
    expect(config('cors.exposed_headers'))->toContain('Content-Disposition');
});
