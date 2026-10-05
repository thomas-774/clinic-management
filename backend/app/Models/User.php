<?php

namespace App\Models;

use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'phone', 'email', 'password', 'role', 'is_active'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * Matches the column default, so a new model is active before a refresh.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_active' => true,
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'role' => UserRole::class,
            'is_active' => 'boolean',
        ];
    }

    /**
     * The clinic's doctor. v1 has exactly one (§10); with more doctors this
     * becomes a choice made by the patient.
     */
    public static function clinicDoctor(): self
    {
        return static::query()->where('role', UserRole::Doctor)->orderBy('id')->firstOrFail();
    }

    public function isDoctor(): bool
    {
        return $this->role === UserRole::Doctor;
    }

    public function isPatient(): bool
    {
        return $this->role === UserRole::Patient;
    }

    /**
     * The front-desk assistant (Module I).
     */
    public function isAssistant(): bool
    {
        return $this->role === UserRole::Assistant;
    }

    /**
     * The patient record of a patient user.
     */
    public function patient(): HasOne
    {
        return $this->hasOne(Patient::class);
    }

    /**
     * The settings row of a doctor user.
     */
    public function doctorSetting(): HasOne
    {
        return $this->hasOne(DoctorSetting::class, 'doctor_id');
    }

    public function workingHours(): HasMany
    {
        return $this->hasMany(WorkingHour::class, 'doctor_id');
    }

    public function blockedTimes(): HasMany
    {
        return $this->hasMany(BlockedTime::class, 'doctor_id');
    }

    /**
     * Appointments booked with this doctor.
     */
    public function doctorAppointments(): HasMany
    {
        return $this->hasMany(Appointment::class, 'doctor_id');
    }
}
