<?php

namespace App\Http\Resources;

use App\Models\Payment;
use App\Services\ReportService;
use Illuminate\Http\Request;

/**
 * One row of the reports payments table (FR-H.3). Expects the payment to be
 * loaded by ReportService::payments().
 *
 * @mixin Payment
 */
class ReportPaymentResource extends ApiResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return app(ReportService::class)->paymentRow($this->resource);
    }
}
