<?php

namespace App\Services\VisitReport;

use PhpOffice\PhpWord\Element\AbstractContainer;
use PhpOffice\PhpWord\Element\Table;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\Shared\Converter;
use PhpOffice\PhpWord\SimpleType\Jc;
use PhpOffice\PhpWord\SimpleType\TblWidth;
use PhpOffice\PhpWord\Style\Language;
use PhpOffice\PhpWord\Style\Paper;
use RuntimeException;
use ZipArchive;

/**
 * The visit report as an editable Word file (FR-K.5): the same sections,
 * order and values as the PDF (VR-3), built from real headings, paragraphs
 * and tables. Arial is used, as Cairo is not usually installed on the
 * doctor's computer. Arabic runs are RTL with the complex-script font and
 * size set; tables are mirrored with bidiVisual (VR-4).
 */
class WordVisitReport
{
    private const MARGIN_CM = 1.5;

    private const SIZE = 10.5;

    private const DUE_COLOR = 'C00000';

    /**
     * PHPWord marks cells noWrap by default, so long instructions would widen the table.
     */
    private const CELL = ['noWrap' => false];

    private VisitReportData $data;

    /**
     * @return string the .docx bytes
     */
    public function render(VisitReportData $data): string
    {
        $this->data = $data;
        $word = $this->document();
        // Whole twips: PHPWord's own A4 size has decimals, which OOXML does not allow.
        $paper = new Paper(config('visit_report.paper'));
        $margin = (int) round(Converter::cmToTwip(self::MARGIN_CM));
        $section = $word->addSection([
            'orientation' => 'portrait',
            'pageSizeW' => (int) round($paper->getWidth()),
            'pageSizeH' => (int) round($paper->getHeight()),
            'marginTop' => $margin,
            'marginBottom' => $margin,
            'marginLeft' => $margin,
            'marginRight' => $margin,
            'footerHeight' => (int) round(Converter::cmToTwip(0.8)),
        ]);
        // "1 / 2" at the bottom of every page, as in the PDF.
        $section->addFooter()->addPreserveText('{PAGE} / {NUMPAGES}', $this->font('1', ['size' => 9]), ['alignment' => Jc::CENTER]);

        $this->header($section);
        $section->addTitle($data->labels['title'], 1);
        $this->patientAndVisit($section);
        $this->workDone($section);
        $this->money($section);
        $this->payments($section);
        $this->outstanding($section);
        $this->prescriptions($section);
        $this->signature($section);

        return $this->bytes($word);
    }

    private function document(): PhpWord
    {
        $word = new PhpWord;
        $word->setDefaultFontName(config('visit_report.word_font'));
        $word->setDefaultFontSize(self::SIZE);
        $word->getSettings()->setThemeFontLang(new Language(Language::EN_US, null, 'ar-EG'));

        $creator = $this->data->header['clinic_name'] ?? $this->data->header['doctor_name'] ?? '';
        $word->getDocInfo()->setTitle($this->data->title)->setCreator($creator)->setLastModifiedBy($creator);

        $paragraph = ['bidi' => $this->data->rtl, 'alignment' => Jc::START, 'keepNext' => true];
        $word->addTitleStyle(1, $this->font($this->data->title, ['size' => 15, 'bold' => true]), $paragraph + ['spaceBefore' => 200, 'spaceAfter' => 120]);
        $word->addTitleStyle(2, $this->font($this->data->title, ['size' => 11.5, 'bold' => true]), $paragraph + ['spaceBefore' => 240, 'spaceAfter' => 80]);

        return $word;
    }

    private function header(AbstractContainer $section): void
    {
        $header = $this->data->header;
        $center = ['alignment' => Jc::CENTER, 'spaceAfter' => 0];

        if (isset($header['clinic_name'])) {
            $this->text($section, $header['clinic_name'], ['size' => 16, 'bold' => true], $center);
        }
        $doctor = ($header['doctor_name'] ?? '').(isset($header['doctor_title']) ? ' — '.$header['doctor_title'] : '');
        $this->text($section, $doctor, ['size' => 11.5, 'bold' => true], $center);

        $contact = array_filter([$header['address'] ?? null, $header['phone'] ?? null]);
        // The thin rule under the header, as in the PDF.
        $rule = $center + ['borderBottomSize' => 8, 'borderBottomColor' => '000000', 'spaceAfter' => 200];
        $rtl = VisitReportData::textDir($contact[array_key_first($contact)] ?? $doctor) === 'rtl';
        $run = $section->addTextRun($rule + ['bidi' => $rtl]);
        foreach (array_values($contact) as $i => $part) {
            if ($i > 0) {
                // The separator takes the line's direction, or it drifts to the line's end.
                $run->addText(' · ', $this->font(' ', ['size' => 9.5, 'rtl' => $rtl]));
            }
            $run->addText($part, $this->font($part, ['size' => 9.5]));
        }
    }

    private function patientAndVisit(AbstractContainer $section): void
    {
        $l = $this->data->labels;
        $patient = $this->data->patient;
        $visit = $this->data->visit;
        $rows = [
            [$l['patient'], $patient['name'], $l['visit_date'], $visit['date']],
            [$l['phone'], $patient['phone'] ?? '', $l['time'], $visit['walk_in'] ? $l['walk_in'] : $visit['time']],
            [$patient['age'] !== null ? $l['age'] : '', (string) ($patient['age'] ?? ''), $l['payment_status'], $visit['payment_status']],
        ];

        $table = $this->table($section, borders: false);
        foreach ($rows as $row) {
            $table->addRow();
            foreach ($row as $i => $value) {
                $cell = $table->addCell($i % 2 === 0 ? 1900 : 3200, self::CELL);
                $this->text($cell, $value, ['bold' => $i % 2 === 0]);
            }
        }
    }

    private function workDone(AbstractContainer $section): void
    {
        $section->addTitle($this->data->labels['work_done'], 2);
        $cell = $this->table($section)->addRow()->addCell(10206, self::CELL);
        // Each line keeps its own direction, as in the PDF.
        foreach (preg_split('/\R/u', $this->data->workDone) as $line) {
            $this->text($cell, $line);
        }
    }

    private function money(AbstractContainer $section): void
    {
        $l = $this->data->labels;
        $money = $this->data->money;
        $section->addTitle($l['money'], 2);

        $table = $this->table($section);
        $table->addRow();
        foreach (['total', 'paid', 'remaining'] as $key) {
            $due = $key === 'remaining' && $money['has_remaining'];
            $style = $key === 'remaining' ? ['bold' => true] + ($due ? ['color' => self::DUE_COLOR] : []) : [];
            $cell = $table->addCell(3402, self::CELL);
            $this->text($cell, $l[$key], $style, ['alignment' => Jc::CENTER]);
            $this->text($cell, $money[$key], $style + ['size' => 12.5], ['alignment' => Jc::CENTER]);
        }
    }

    private function payments(AbstractContainer $section): void
    {
        $l = $this->data->labels;
        $section->addTitle($l['payments'], 2);

        if ($this->data->payments === []) {
            $this->text($section, $l['no_payments']);

            return;
        }

        $this->grid($section, [$l['date'], $l['amount'], $l['method'], $l['recorded_by']], [2500, 2500, 2400, 2806], array_map(
            fn (array $p) => [$p['date'], $p['amount'], $p['method'], (string) $p['recorded_by']],
            $this->data->payments,
        ));
    }

    private function outstanding(AbstractContainer $section): void
    {
        $run = $section->addTextRun($this->paragraph($this->data->labels['outstanding'], ['spaceBefore' => 200]));
        $label = $this->data->labels['outstanding'].': ';
        $run->addText($label, $this->font($label, ['bold' => true]));
        $run->addText($this->data->outstanding, $this->font($this->data->outstanding, ['bold' => true] + ($this->data->hasOutstanding ? ['color' => self::DUE_COLOR] : [])));
    }

    private function prescriptions(AbstractContainer $section): void
    {
        if ($this->data->prescriptions === []) {
            return;
        }

        $l = $this->data->labels;
        $section->addTitle($l['prescriptions'], 2);
        foreach ($this->data->prescriptions as $prescription) {
            $this->text($section, $l['date'].': '.$prescription['issued_on'], ['size' => 9.5]);
            $this->grid($section, ['#', $l['drug'], $l['form'], $l['instructions']], [600, 3000, 1800, 4806], array_map(
                fn (array $item, int $i) => [(string) ($i + 1), $item['drug_name'], (string) $item['drug_form'], $item['instructions']],
                $prescription['items'],
                array_keys($prescription['items']),
            ));
        }
    }

    private function signature(AbstractContainer $section): void
    {
        $l = $this->data->labels;
        $section->addTextBreak(2);
        $table = $this->table($section, borders: false);
        $table->addRow();
        $this->text($table->addCell(5103, self::CELL), $l['generated_on'].': '.$this->data->generatedAt, ['size' => 9.5]);
        $this->text($table->addCell(5103, self::CELL), $l['signature'].': ______________________', [], ['alignment' => Jc::END]);
    }

    /**
     * A table with a repeated, bold header row and rows that never split.
     *
     * @param  list<string>  $headings
     * @param  list<int>  $widths  twips
     * @param  list<list<string>>  $rows
     */
    private function grid(AbstractContainer $section, array $headings, array $widths, array $rows): void
    {
        $table = $this->table($section);
        $table->addRow(null, ['tblHeader' => true, 'cantSplit' => true]);
        foreach ($headings as $i => $heading) {
            $this->text($table->addCell($widths[$i], ['bgColor' => 'F2F2F2'] + self::CELL), $heading, ['bold' => true]);
        }
        foreach ($rows as $row) {
            $table->addRow(null, ['cantSplit' => true]);
            foreach ($row as $i => $value) {
                $this->text($table->addCell($widths[$i], self::CELL), $value);
            }
        }
    }

    private function table(AbstractContainer $section, bool $borders = true): Table
    {
        return $section->addTable([
            'bidiVisual' => $this->data->rtl,
            'unit' => TblWidth::PERCENT,
            'width' => 100 * 50,
            'borderSize' => $borders ? 4 : 0,
            'borderColor' => $borders ? '808080' : 'FFFFFF',
            'cellMargin' => 70,
        ]);
    }

    /**
     * One paragraph of $text in its own direction, at the file's start side.
     *
     * @param  array<string, mixed>  $font
     * @param  array<string, mixed>  $paragraph
     */
    private function text(AbstractContainer $container, string $text, array $font = [], array $paragraph = []): void
    {
        $container->addText($text, $this->font($text, $font), $this->paragraph($text, ['spaceAfter' => 60] + $paragraph));
    }

    /**
     * Arabic text is a right-to-left run (complex-script font and size set
     * by PHPWord with the size); anything else, e.g. a phone or an amount,
     * stays left-to-right inside it.
     *
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function font(string $text, array $extra = []): array
    {
        return $extra + [
            'name' => config('visit_report.word_font'),
            'size' => self::SIZE,
            'rtl' => VisitReportData::textDir($text) === 'rtl',
        ];
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function paragraph(string $text, array $extra = []): array
    {
        $rtl = VisitReportData::textDir($text) === 'rtl' || ($this->data->rtl && ! preg_match('/\p{L}/u', $text));

        // "start" follows the paragraph's own direction, so a paragraph in
        // the other direction is aligned to "end" to stay on the file's side.
        return $extra + [
            'bidi' => $rtl,
            'alignment' => $rtl === $this->data->rtl ? Jc::START : Jc::END,
        ];
    }

    private function bytes(PhpWord $word): string
    {
        $path = tempnam(sys_get_temp_dir(), 'visit');
        try {
            IOFactory::createWriter($word, 'Word2007')->save($path);
            if ($this->data->rtl) {
                $this->makeSectionRtl($path);
            }

            return (string) file_get_contents($path);
        } finally {
            @unlink($path);
        }
    }

    /**
     * PHPWord has no section direction, so <w:bidi/> is added to the
     * section properties (page layout right-to-left, e.g. in Word's ruler).
     */
    private function makeSectionRtl(string $path): void
    {
        $zip = new ZipArchive;
        if ($zip->open($path) !== true) {
            throw new RuntimeException('Cannot open the generated .docx');
        }
        $xml = (string) $zip->getFromName('word/document.xml');
        // bidi comes after cols and before docGrid in CT_SectPr.
        $xml = preg_replace('#(<w:sectPr\b.*?)(<w:docGrid\b|</w:sectPr>)#s', '$1<w:bidi/>$2', $xml, 1);
        $zip->addFromString('word/document.xml', $xml);
        $zip->close();
    }
}
