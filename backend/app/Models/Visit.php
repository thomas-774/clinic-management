<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Database\Factories\VisitFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * The remaining balance is not stored; it is computed from the payments (PR-1).
 */
#[Fillable(['patient_id', 'appointment_id', 'visit_date', 'work_done', 'total_amount'])]
class Visit extends Model
{
    use Auditable;

    /** @use HasFactory<VisitFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'visit_date' => 'date:Y-m-d',
            'total_amount' => 'decimal:2',
        ];
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function prescriptions(): HasMany
    {
        return $this->hasMany(Prescription::class);
    }

    /**
     * Adds payments_sum_amount in the same query, so PaymentService can give
     * paid / remaining for a list of visits without one query each.
     */
    public function scopeWithPaid(Builder $query): void
    {
        $query->withSum('payments', 'amount');
    }

    /**
     * Visits that still owe money: total > sum of payments (PR-1).
     */
    public function scopeUnpaid(Builder $query): void
    {
        $query->whereRaw('visits.total_amount > (select coalesce(sum(payments.amount), 0) from payments where payments.visit_id = visits.id)');
    }

    /**
     * The patient this record belongs to, for the audit log (NFR-S.4).
     */
    public function auditPatientId(): ?int
    {
        return $this->patient_id;
    }
}
