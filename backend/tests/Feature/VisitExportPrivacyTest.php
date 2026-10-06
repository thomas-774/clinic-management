<?php

use Illuminate\Support\Facades\Storage;

/*
 * T10-06: visit file permissions and contents (§2, §9.1, FR-K.2, FR-K.3,
 * FR-K.6, VR-1 – VR-3). The fixture's texts that must never reach the file
 * all start with "SECRET-" (see visitReportFixture()).
 */

beforeEach(function () {
    $this->withHeader('Accept-Language', 'en');
    $this->travelTo('2026-10-09 15:30:00');
    $this->fixture = visitReportFixture();
    $this->visit = $this->fixture['visit'];
    $this->uri = fn (string $format) => "/api/v1/doctor/visits/{$this->visit->id}/export?format={$format}";
});

/**
 * The file's text: the PDF through smalot/pdfparser, the docx from its
 * document.xml with one line per paragraph.
 */
function exportedText(string $format): string
{
    $response = test()->actingAs(test()->fixture['doctor'])->get((test()->uri)($format));
    $response->assertOk();

    return $format === 'pdf' ? pdfText($response->getContent()) : docxText($response->getContent());
}

function docxText(string $bytes): string
{
    $path = tempnam(sys_get_temp_dir(), 'docx');
    file_put_contents($path, $bytes);
    $zip = new ZipArchive;
    $zip->open($path);
    $xml = (string) $zip->getFromName('word/document.xml');
    $zip->close();
    unlink($path);

    return html_entity_decode(strip_tags(str_replace('</w:p>', "</w:p>\n", $xml)), ENT_QUOTES | ENT_XML1, 'UTF-8');
}

/**
 * @return list<string> every "1,500.00"-style amount, sorted
 */
function amountsIn(string $text): array
{
    preg_match_all('/\d{1,3}(?:,\d{3})*\.\d{2}/', $text, $m);
    sort($m[0]);

    return $m[0];
}

describe('permissions (FR-K.6)', function () {
    it('answers 401 to a guest', function (string $format) {
        $this->getJson(($this->uri)($format))->assertUnauthorized();
    })->with(['pdf', 'docx']);

    it('refuses the patient, even for their own visit', function (string $format) {
        $this->actingAs($this->fixture['patient']->user)->getJson(($this->uri)($format))->assertForbidden();
    })->with(['pdf', 'docx']);

    it('refuses the assistant', function (string $format) {
        $this->actingAs($this->fixture['assistant'])->getJson(($this->uri)($format))->assertForbidden();
    })->with(['pdf', 'docx']);

    it('answers 401 to a doctor whose account was deactivated', function () {
        $doctor = $this->fixture['doctor'];
        $token = $doctor->createToken('api')->plainTextToken;
        $this->withToken($token)->get(($this->uri)('pdf'))->assertOk();

        $doctor->update(['is_active' => false]);
        app('auth')->forgetGuards();

        $this->withToken($token)->getJson(($this->uri)('pdf'))->assertUnauthorized();
    });
});

describe('contents', function () {
    it('never holds medical history, current illness or another visit (FR-K.3)', function (string $format, string $language) {
        $this->withHeader('Accept-Language', $language);
        $patient = $this->fixture['patient']->load('medicalHistoryEntries');
        expect($patient->medicalHistoryEntries->pluck('patient_visible')->all())->toEqualCanonicalizing([true, false]);

        $text = exportedText($format);

        expect($text)->toContain('Root canal, upper left 6')
            ->and($text)->not->toContain('SECRET-HISTORY-PRIVATE')
            ->and($text)->not->toContain('SECRET-HISTORY-VISIBLE')
            ->and($text)->not->toContain('SECRET-DETAILS')
            ->and($text)->not->toContain('SECRET-ILLNESS')
            ->and($text)->not->toContain('SECRET-OTHER-VISIT')
            ->and($text)->not->toContain('SECRET-');
    })->with(['pdf', 'docx'])->with(['en', 'ar']);

    it('holds the same values in both formats (VR-3)', function (string $language) {
        $this->withHeader('Accept-Language', $language);

        $pdf = exportedText('pdf');
        $docx = exportedText('docx');

        expect(amountsIn($pdf))->toBe(amountsIn($docx))
            ->and(amountsIn($docx))->toBe(['1,500.00', '1,700.00', '300.00', '500.00', '700.00', '800.00']);
        foreach (['Root canal, upper left 6', 'Temporary filling', 'Ahmed Ali', '01012345678', 'Sara Hassan', 'Augmentin 1 g', 'Brufen 400 mg'] as $value) {
            expect($pdf)->toContain($value)->and($docx)->toContain($value);
        }
    })->with(['en', 'ar']);

    it('lists the same payments in both formats (VR-3)', function () {
        $pdf = exportedText('pdf');
        $docx = exportedText('docx');

        foreach (['Cash', 'Card'] as $method) {
            expect(substr_count($pdf, $method))->toBe(1)
                ->and(substr_count($docx, $method))->toBe(1);
        }
        foreach (['7 Oct 2026', '8 Oct 2026'] as $date) {
            expect(substr_count($pdf, $date))->toBe(substr_count($docx, $date));
        }
    });

    it('is always current: an installment changes paid, remaining and the overall balance (VR-1)', function (string $format) {
        $before = exportedText($format);
        expect($before)->toContain('800.00 EGP')->toContain('700.00 EGP')->toMatch('/Overall outstanding balance:\s*1,700\.00 EGP/');

        $this->actingAs($this->fixture['doctor'])
            ->postJson("/api/v1/doctor/visits/{$this->visit->id}/payments", ['amount' => 200])
            ->assertCreated();

        $after = exportedText($format);
        expect($after)->toContain('1,000.00 EGP')->toContain('500.00 EGP')
            ->and($after)->not->toContain('800.00 EGP')
            ->and($after)->not->toContain('700.00 EGP')
            ->and($after)->toMatch('/Overall outstanding balance:\s*1,500\.00 EGP/');
    })->with(['pdf', 'docx']);

    it("holds this visit's prescription only, without drug notes or warnings", function (string $format) {
        $text = exportedText($format);

        expect($text)->toContain('Augmentin 1 g')
            ->and($text)->toContain('1 tablet every 12 hours for 5 days')
            ->and($text)->not->toContain('SECRET-OTHER-DRUG')
            ->and($text)->not->toContain('SECRET-USES')
            ->and($text)->not->toContain('SECRET-WARNING')
            ->and($text)->not->toContain('SECRET-RX-NOTES');
    })->with(['pdf', 'docx']);

    it('stores nothing (VR-1)', function (string $format) {
        Storage::fake('local');
        Storage::fake('public');

        exportedText($format);

        expect(Storage::disk('local')->allFiles())->toBe([])
            ->and(Storage::disk('public')->allFiles())->toBe([]);
    })->with(['pdf', 'docx']);
});
