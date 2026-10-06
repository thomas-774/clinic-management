<?php

namespace App\Services\VisitReport;

use Mpdf\Config\ConfigVariables;
use Mpdf\Config\FontVariables;
use Mpdf\Mpdf;
use Mpdf\Output\Destination;

/**
 * The visit report as an A4 PDF (FR-K.5), laid out from VisitReportData by
 * the reports/visit Blade view. mPDF shapes and joins Arabic and lays it out
 * right-to-left in the bundled Cairo font (VR-4). Nothing is written to disk
 * but mPDF's font cache (VR-1).
 */
class PdfVisitReport
{
    private const MARGIN_MM = 15;

    /**
     * @return string the PDF bytes
     */
    public function render(VisitReportData $data): string
    {
        $mpdf = $this->mpdf($data);
        $mpdf->SetTitle($data->title);
        $mpdf->SetAuthor($data->header['clinic_name'] ?? $data->header['doctor_name'] ?? '');
        $mpdf->SetCreator($data->header['clinic_name'] ?? $data->header['doctor_name'] ?? '');
        // "1 / 2" at the bottom of every page.
        $mpdf->SetHTMLFooter('<div style="text-align: center; font-size: 9pt;">{PAGENO} / {nbpg}</div>');

        $mpdf->WriteHTML(view('reports.visit', [
            'data' => $data,
            'font' => config('visit_report.pdf_font'),
        ])->render());

        return $mpdf->Output('', Destination::STRING_RETURN);
    }

    private function mpdf(VisitReportData $data): Mpdf
    {
        $defaults = (new ConfigVariables)->getDefaults();
        $fonts = (new FontVariables)->getDefaults();

        $mpdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => config('visit_report.paper'),
            'orientation' => 'P',
            'margin_left' => self::MARGIN_MM,
            'margin_right' => self::MARGIN_MM,
            'margin_top' => self::MARGIN_MM,
            'margin_bottom' => self::MARGIN_MM + 5,
            'margin_footer' => 8,
            'tempDir' => config('visit_report.temp_dir'),
            'fontDir' => [...$defaults['fontDir'], config('visit_report.pdf_font_dir')],
            'fontdata' => $fonts['fontdata'] + config('visit_report.pdf_fonts'),
            'default_font' => config('visit_report.pdf_font'),
            // Only Cairo: never swap in one of mPDF's own fonts per script.
            'autoScriptToLang' => false,
            'autoLangToFont' => false,
        ]);
        $mpdf->SetDirectionality($data->rtl ? 'rtl' : 'ltr');

        return $mpdf;
    }
}
