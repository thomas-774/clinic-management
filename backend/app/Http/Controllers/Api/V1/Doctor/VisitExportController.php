<?php

namespace App\Http\Controllers\Api\V1\Doctor;

use App\Http\Controllers\Controller;
use App\Http\Requests\Doctor\ExportVisitRequest;
use App\Models\Visit;
use App\Services\VisitReport\PdfVisitReport;
use App\Services\VisitReport\VisitReportService;
use App\Services\VisitReport\WordVisitReport;
use Illuminate\Http\Response;

/**
 * The visit report file (Module K), doctor only (FR-K.6).
 */
class VisitExportController extends Controller
{
    private const CONTENT_TYPES = [
        'pdf' => 'application/pdf',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    ];

    /**
     * GET /doctor/visits/{visit}/export?format=pdf|docx — built from the
     * database now, in the request's language (FR-K.5), and never stored
     * (VR-1). The file name is plain ASCII (VR-5).
     */
    public function __invoke(ExportVisitRequest $request, Visit $visit, VisitReportService $reports): Response
    {
        $format = $request->validated('format');
        $data = $reports->build($visit, app()->getLocale());

        $bytes = $format === 'pdf'
            ? app(PdfVisitReport::class)->render($data)
            : app(WordVisitReport::class)->render($data);
        $name = sprintf('visit-%s-%d.%s', $visit->visit_date->format('Y-m-d'), $visit->id, $format);

        return response($bytes, 200, [
            'Content-Type' => self::CONTENT_TYPES[$format],
            'Content-Disposition' => 'attachment; filename="'.$name.'"',
            'Content-Length' => (string) strlen($bytes),
            'Cache-Control' => 'no-store, private',
        ]);
    }
}
