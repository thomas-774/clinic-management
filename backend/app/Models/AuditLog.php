<?php

namespace App\Models;

use App\Enums\AuditAction;
use App\Enums\UserRole;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use LogicException;

/**
 * One read or change of a medical or money record (NFR-S.4). Append-only:
 * the app can never edit or delete a row through the model; `audit:prune`
 * (T11-05) removes old rows with a query.
 */
#[Fillable(['user_id', 'user_role', 'action', 'auditable_type', 'auditable_id', 'patient_id', 'changed_fields', 'ip', 'user_agent'])]
class AuditLog extends Model
{
    public const UPDATED_AT = null;

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Audit log rows cannot be changed.'));
        static::deleting(fn () => throw new LogicException('Audit log rows cannot be deleted.'));
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'action' => AuditAction::class,
            'user_role' => UserRole::class,
            'changed_fields' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function auditable(): MorphTo
    {
        return $this->morphTo();
    }
}
