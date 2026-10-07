<?php

namespace App\Http\Controllers\Api\V1\Doctor;

use App\Http\Controllers\Controller;
use App\Http\Requests\Doctor\AuditLogFilterRequest;
use App\Http\Resources\ApiResourceCollection;
use App\Http\Resources\AuditLogResource;
use App\Models\AuditLog;
use App\Models\Patient;
use Illuminate\Support\Carbon;

/**
 * Settings → Activity (NFR-S.5): who read or changed which medical or money
 * record. Doctor only (role middleware).
 */
class AuditLogController extends Controller
{
    public const PER_PAGE = 50;

    /**
     * GET /doctor/audit-logs?patient_id=&user_id=&action=&from=&to=&page= — newest first.
     * When filtered by patient, `filters.patient` names them even on an empty page.
     */
    public function index(AuditLogFilterRequest $request): ApiResourceCollection
    {
        $filters = $request->validated();

        $logs = AuditLog::query()
            ->with(['user', 'patient.user'])
            ->when($filters['patient_id'] ?? null, fn ($query, $id) => $query->where('patient_id', $id))
            ->when($filters['user_id'] ?? null, fn ($query, $id) => $query->where('user_id', $id))
            ->when($filters['action'] ?? null, fn ($query, $action) => $query->where('action', $action))
            ->when($filters['from'] ?? null, fn ($query, $from) => $query->where('created_at', '>=', Carbon::parse($from)->startOfDay()))
            ->when($filters['to'] ?? null, fn ($query, $to) => $query->where('created_at', '<', Carbon::parse($to)->addDay()->startOfDay()))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        $patient = isset($filters['patient_id']) ? Patient::with('user')->find($filters['patient_id']) : null;

        return AuditLogResource::collection($logs)->additional([
            'filters' => ['patient' => $patient ? ['id' => $patient->id, 'name' => $patient->user->name] : null],
        ]);
    }
}
