<?php

namespace App\Models;

use App\Enums\AppointmentStatus;
use App\Models\Concerns\Auditable;
use Database\Factories\PatientFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

#[Fillable(['user_id', 'address', 'date_of_birth', 'gender', 'current_illness'])]
class Patient extends Model
{
    use Auditable;

    /** @use HasFactory<PatientFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date:Y-m-d',
            // Encrypted at rest with APP_KEY (NFR-S.6): never filter or sort on it in SQL.
            'current_illness' => 'encrypted',
        ];
    }

    /**
     * The patient list (FR-C.1, FR-I.2): search by part of the name or phone,
     * sorted by name, with the last visit date.
     */
    public function scopeForList(Builder $query, string $search = ''): void
    {
        $query
            ->select('patients.*')
            ->join('users', 'users.id', '=', 'patients.user_id')
            ->with('user')
            ->withMax('visits', 'visit_date')
            ->when($search !== '', function ($query) use ($search) {
                $like = '%'.addcslashes($search, '\\%_').'%';
                $query->where(fn ($q) => $q
                    ->where('users.name', 'like', $like)
                    ->orWhere('users.phone', 'like', $like));
            })
            // users.id breaks ties (one user per patient): both sort keys on
            // users let MySQL read the name index in order and stop at the page.
            ->orderBy('users.name')
            ->orderBy('users.id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function medicalHistoryEntries(): HasMany
    {
        return $this->hasMany(MedicalHistoryEntry::class);
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }

    /**
     * The earliest appointment still ahead: booked or checked in, not over yet (FR-B.4).
     */
    public function nextAppointment(): ?Appointment
    {
        return $this->appointments()
            ->whereIn('status', [AppointmentStatus::Booked, AppointmentStatus::CheckedIn])
            ->where('end_at', '>', now())
            ->orderBy('start_at')
            ->first();
    }

    public function visits(): HasMany
    {
        return $this->hasMany(Visit::class);
    }

    public function payments(): HasManyThrough
    {
        return $this->hasManyThrough(Payment::class, Visit::class);
    }

    public function prescriptions(): HasMany
    {
        return $this->hasMany(Prescription::class);
    }

    /**
     * The patient this record belongs to, for the audit log (NFR-S.4).
     * The patient record itself.
     */
    public function auditPatientId(): ?int
    {
        return $this->getKey();
    }
}
