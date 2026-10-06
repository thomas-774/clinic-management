<?php

use App\Models\Visit;
use App\Services\VisitReport\VisitReportService;
use App\Services\VisitReport\WordVisitReport;

// T10-04: the visit report as an editable .docx (FR-K.2, FR-K.5, VR-3, VR-4).

/**
 * @return array<string, string> the zip's parts by name
 */
function visitDocx(Visit $visit, string $locale): array
{
    $bytes = app(WordVisitReport::class)->render(app(VisitReportService::class)->build(Visit::findOrFail($visit->id), $locale));
    $path = tempnam(sys_get_temp_dir(), 'docx');
    file_put_contents($path, $bytes);

    $zip = new ZipArchive;
    expect($zip->open($path, ZipArchive::CHECKCONS))->toBeTrue();
    $parts = [];
    foreach (['word/document.xml', 'docProps/core.xml', 'word/settings.xml'] as $name) {
        $parts[$name] = (string) $zip->getFromName($name);
    }
    $zip->close();
    unlink($path);

    return $parts;
}

it('is a valid docx holding the work done, the three amounts and the patient', function (string $locale, string $egp) {
    ['visit' => $visit] = visitReportFixture();

    $xml = visitDocx($visit, $locale)['word/document.xml'];

    expect($xml)->not->toBe('')
        ->and(simplexml_load_string($xml))->not->toBeFalse()
        ->and($xml)->toContain('Root canal, upper left 6')
        ->and($xml)->toContain('Temporary filling')
        ->and($xml)->toContain('Ahmed Ali')
        ->and($xml)->toContain('Augmentin 1 g')
        ->and($xml)->not->toContain('SECRET-');
    foreach (['1,500.00', '800.00', '700.00', '1,700.00'] as $amount) {
        expect($xml)->toContain("{$amount} {$egp}");
    }
})->with([
    'en' => ['en', 'EGP'],
    'ar' => ['ar', 'ج.م'],
]);

it('is right-to-left in Arabic only', function () {
    ['visit' => $visit] = visitReportFixture();

    $ar = visitDocx($visit, 'ar')['word/document.xml'];
    $en = visitDocx($visit, 'en')['word/document.xml'];

    // <w:bidi/> on paragraphs and the section; bidiVisual mirrors the tables.
    expect($ar)->toMatch('#<w:bidi\s*/>#')
        ->and($ar)->toMatch('#<w:sectPr\b.*<w:bidi/></w:sectPr>#s')
        ->and($ar)->toContain('<w:bidiVisual w:val="1"/>')
        ->and($ar)->toContain('<w:rtl/>')
        ->and($ar)->toContain('العمل المنجز اليوم')
        ->and($en)->not->toMatch('#<w:bidi[\s/>]#')
        ->and($en)->not->toContain('<w:rtl/>')
        ->and($en)->toContain('Work done today');
});

it('is A4 portrait in Arial with real headings, a repeated table header and its title and creator', function () {
    ['visit' => $visit] = visitReportFixture();

    $parts = visitDocx($visit, 'en');
    $xml = $parts['word/document.xml'];

    expect($xml)->toContain('<w:pgSz w:orient="portrait" w:w="11906" w:h="16838"/>')
        ->and($xml)->toContain('w:top="850" w:right="850" w:bottom="850" w:left="850"')
        ->and($xml)->toContain('w:cs="Arial"')
        ->and($xml)->toContain('<w:pStyle w:val="Heading1"/>')
        ->and($xml)->toContain('<w:pStyle w:val="Heading2"/>')
        ->and($xml)->toContain('<w:tblHeader w:val="1"/>')
        ->and($xml)->not->toContain('<w:noWrap/>')
        ->and($parts['docProps/core.xml'])->toContain('<dc:title>Visit report — 7 Oct 2026</dc:title>')
        ->and($parts['docProps/core.xml'])->toContain('<dc:creator>Smile Dental Clinic</dc:creator>');
});
