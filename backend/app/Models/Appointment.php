<?php

namespace App\Models;

use App\Enums\AppointmentStatus;
use Database\Factories\AppointmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * active_slot is a generated column (T1-04) and is never written.
 */
#[Fillable(['doctor_id', 'patient_id', 'start_at', 'end_at', 'status', 'checked_in_at', 'cancelled_at'])]
#[Hidden(['active_slot'])]
class Appointment extends Model
{
    /** @use HasFactory<AppointmentFactory> */
    use HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'booked',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'start_at' => 'datetime',
            'end_at' => 'datetime',
            'status' => AppointmentStatus::class,
            'checked_in_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'doctor_id');
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function visit(): HasOne
    {
        return $this->hasOne(Visit::class);
    }

    /**
     * Still ahead of the patient: booked or checked in, and not over yet.
     */
    public function isUpcoming(): bool
    {
        return in_array($this->status, [AppointmentStatus::Booked, AppointmentStatus::CheckedIn], true)
            && $this->end_at->isFuture();
    }

    /**
     * BR-5: a patient may cancel a booked appointment only until
     * start_at − cancel_cutoff_hours (doctor setting). The doctor has no limit.
     */
    public function patientCanCancel(): bool
    {
        $cutoffHours = ($this->doctor->doctorSetting ?? new DoctorSetting)->cancel_cutoff_hours;

        return $this->status === AppointmentStatus::Booked
            && now()->lte($this->start_at->copy()->subHours($cutoffHours));
    }

    /**
     * Frees the slot: active_slot becomes NULL, so it can be booked again.
     */
    public function cancel(): void
    {
        $this->transitionTo(AppointmentStatus::Cancelled);
    }

    /**
     * Sets the status and its timestamp (checked_in_at / cancelled_at).
     * Callers check AppointmentStatus::canTransitionTo() first (§4.3).
     */
    public function transitionTo(AppointmentStatus $to): void
    {
        $this->update([
            'status' => $to,
            ...match ($to) {
                AppointmentStatus::CheckedIn => ['checked_in_at' => now()],
                AppointmentStatus::Cancelled => ['cancelled_at' => now()],
                default => [],
            },
        ]);
    }
}
