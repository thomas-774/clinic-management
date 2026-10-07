<?php

namespace App\Services;

use App\Enums\AuditAction;
use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * Writes the audit log (NFR-S.4): who (user, role, IP, browser) did what to
 * which record of which patient, and the names of the changed fields — never
 * their values.
 *
 * Only staff (doctor, assistant) are logged: a patient reading or editing
 * their own data, and seeders / console commands (no user), write nothing.
 * A record read twice in one request is logged once.
 */
class AuditLogger
{
    /** Request attribute holding the reads already logged in this request. */
    private const LOGGED = 'audit.logged';

    public function __construct(private readonly Request $request) {}

    /**
     * @param  list<string>  $changedFields  names only
     */
    public function record(AuditAction $action, Model $record, Patient|int|null $patient, array $changedFields = []): ?AuditLog
    {
        $user = Auth::user();
        if (! $user instanceof User || $user->role === UserRole::Patient) {
            return null;
        }

        if ($this->isRead($action)) {
            $key = $action->value.'|'.$record->getMorphClass().'|'.$record->getKey();
            $logged = $this->request->attributes->get(self::LOGGED, []);
            if (isset($logged[$key])) {
                return null;
            }
            $this->request->attributes->set(self::LOGGED, $logged + [$key => true]);
        }

        return AuditLog::create([
            'user_id' => $user->id,
            'user_role' => $user->role,
            'action' => $action,
            'auditable_type' => $record->getMorphClass(),
            'auditable_id' => $record->getKey(),
            'patient_id' => $patient instanceof Patient ? $patient->getKey() : $patient,
            'changed_fields' => $changedFields === [] ? null : array_values(array_map('strval', $changedFields)),
            'ip' => $this->request->ip(),
            'user_agent' => Str::limit((string) $this->request->userAgent(), 252) ?: null,
        ]);
    }

    private function isRead(AuditAction $action): bool
    {
        return in_array($action, [AuditAction::Viewed, AuditAction::Exported, AuditAction::Printed], true);
    }
}
