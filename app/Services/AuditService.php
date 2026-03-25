<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Support\Facades\Auth;

class AuditService
{
    public static function log(
        string $action,
        string $resourceType,
        ?int $resourceId = null,
        ?array $details = null
    ): AuditLog {
        return AuditLog::create([
            'admin_user_id' => Auth::id(),
            'action' => $action,
            'resource_type' => $resourceType,
            'resource_id' => $resourceId,
            'details' => $details,
            'ip_address' => request()->ip(),
        ]);
    }
}
