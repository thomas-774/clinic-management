<?php

namespace App\Models\Concerns;

use App\Enums\AuditAction;
use App\Services\AuditLogger;

/**
 * Writes an audit log row when a medical or money record is created, updated
 * or deleted (NFR-S.4). The using model says which patient a row belongs to
 * with auditPatientId(); it must not lazy-load a relation for that.
 *
 * save() and delete() run in a transaction, so a record and its audit row
 * are stored together or not at all.
 */
trait Auditable
{
    /** Columns whose change is not worth a row of its own. */
    private const AUDIT_IGNORED = ['created_at', 'updated_at'];

    abstract public function auditPatientId(): ?int;

    public static function bootAuditable(): void
    {
        static::created(fn (self $model) => $model->audit(AuditAction::Created));

        static::updated(function (self $model) {
            $fields = array_values(array_diff(array_keys($model->getChanges()), self::AUDIT_IGNORED));
            if ($fields !== []) {
                $model->audit(AuditAction::Updated, $fields);
            }
        });

        static::deleted(fn (self $model) => $model->audit(AuditAction::Deleted));
    }

    public function save(array $options = []): bool
    {
        return $this->getConnection()->transaction(fn () => parent::save($options));
    }

    public function delete(): ?bool
    {
        return $this->getConnection()->transaction(fn () => parent::delete());
    }

    /**
     * @param  list<string>  $fields
     */
    private function audit(AuditAction $action, array $fields = []): void
    {
        app(AuditLogger::class)->record($action, $this, $this->auditPatientId(), $fields);
    }
}
