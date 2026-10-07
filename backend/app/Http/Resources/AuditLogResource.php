<?php

namespace App\Http\Resources;

use App\Models\AuditLog;
use App\Models\MedicalHistoryEntry;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\Prescription;
use App\Models\PrescriptionItem;
use App\Models\Visit;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * One audit log row for Settings → Activity (NFR-S.5). The record type and
 * the changed field names come translated (lang/{ar,en}/audit.php); the
 * action and role are codes the frontend translates.
 *
 * @mixin AuditLog
 */
class AuditLogResource extends ApiResource
{
    /** auditable_type → key in audit.records */
    public const RECORD_TYPES = [
        Patient::class => 'patient',
        MedicalHistoryEntry::class => 'history_entry',
        Visit::class => 'visit',
        Payment::class => 'payment',
        Prescription::class => 'prescription',
        PrescriptionItem::class => 'prescription_item',
    ];

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $type = self::RECORD_TYPES[$this->auditable_type] ?? Str::snake(class_basename($this->auditable_type));

        return [
            'id' => $this->id,
            'created_at' => $this->created_at,
            'action' => $this->action->value,
            'user' => $this->user ? ['id' => $this->user->id, 'name' => $this->user->name] : null,
            'user_role' => $this->user_role?->value,
            'record' => [
                'type' => $type,
                'id' => $this->auditable_id,
                'label' => $this->label("audit.records.{$type}", $type),
            ],
            'patient' => $this->patient ? ['id' => $this->patient->id, 'name' => $this->patient->user->name] : null,
            'changed_fields' => array_map(
                fn (string $field) => ['name' => $field, 'label' => $this->label("audit.fields.{$field}", $field)],
                $this->changed_fields ?? [],
            ),
            'ip' => $this->ip,
        ];
    }

    /** The translation, or the code made readable ("work_done" → "Work done"). */
    private function label(string $key, string $code): string
    {
        $text = __($key);

        return $text === $key ? Str::ucfirst(str_replace('_', ' ', $code)) : $text;
    }
}
