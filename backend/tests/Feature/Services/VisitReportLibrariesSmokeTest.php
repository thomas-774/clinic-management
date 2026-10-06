<?php

use Mpdf\Config\ConfigVariables;
use Mpdf\Config\FontVariables;
use Mpdf\Mpdf;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;

/*
 * T10-01: the PDF and Word libraries and the bundled Cairo font are usable,
 * so a missing font or extension fails here before the visit report does.
 */

it('bundles the Cairo font files the PDF uses', function () {
    foreach (config('visit_report.pdf_fonts.cairo') as $style => $file) {
        if (in_array($style, ['R', 'B'], true)) {
            expect(config('visit_report.pdf_font_dir').DIRECTORY_SEPARATOR.$file)->toBeFile();
        }
    }
});

it('renders Arabic with mPDF in the bundled Cairo font', function () {
    $defaults = (new ConfigVariables)->getDefaults();
    $fonts = (new FontVariables)->getDefaults();

    $mpdf = new Mpdf([
        'tempDir' => config('visit_report.temp_dir'),
        'format' => config('visit_report.paper'),
        'fontDir' => [...$defaults['fontDir'], config('visit_report.pdf_font_dir')],
        'fontdata' => $fonts['fontdata'] + config('visit_report.pdf_fonts'),
        'default_font' => config('visit_report.pdf_font'),
    ]);
    $mpdf->WriteHTML('<p dir="rtl">المتبقي <b>1,500.00</b> ج.م</p>');
    $pdf = $mpdf->Output('', 'S');

    expect($pdf)->toStartWith('%PDF')
        ->and($pdf)->toContain('Cairo-Regular')
        ->and($pdf)->toContain('Cairo-Bold');
});

it('writes a right-to-left docx with PHPWord', function () {
    $word = new PhpWord;
    $word->addSection()->addText('العمل المنجز', ['rtl' => true], ['bidi' => true]);

    $path = tempnam(sys_get_temp_dir(), 'docx');
    IOFactory::createWriter($word, 'Word2007')->save($path);

    $zip = new ZipArchive;
    expect($zip->open($path))->toBeTrue();
    $xml = $zip->getFromName('word/document.xml');
    $zip->close();
    unlink($path);

    expect($xml)->toContain('العمل المنجز')->and($xml)->toContain('<w:bidi');
});
