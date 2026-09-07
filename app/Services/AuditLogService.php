<?php

namespace App\Services;

use App\Enums\AuditAction;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class AuditLogService
{
    public function record(User $actor, AuditAction $action, Model $subject, ?Request $request = null): AuditLog
    {
        $request ??= request();

        return AuditLog::query()->create([
            'department_id' => $actor->department_id,
            'account_id' => $actor->id,
            'action' => $action,
            'subject_type' => $subject->getMorphClass(),
            'subject_id' => $subject->getKey(),
            'ip_address' => $request instanceof Request ? $request->ip() : null,
            'user_agent' => $request instanceof Request ? $request->userAgent() : null,
            'created_at' => now(),
        ]);
    }

    /**
     * Record an audit event with no human actor (e.g. scheduled retention purge).
     */
    public function recordSystem(AuditAction $action, Model $subject): AuditLog
    {
        return AuditLog::query()->create([
            'department_id' => null,
            'account_id' => null,
            'action' => $action,
            'subject_type' => $subject->getMorphClass(),
            'subject_id' => $subject->getKey(),
            'ip_address' => null,
            'user_agent' => null,
            'created_at' => now(),
        ]);
    }
}
