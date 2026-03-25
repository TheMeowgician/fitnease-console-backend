<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = AuditLog::with('adminUser:id,name,email');

        if ($adminId = $request->query('admin_user_id')) {
            $query->where('admin_user_id', $adminId);
        }

        if ($action = $request->query('action')) {
            $query->where('action', $action);
        }

        if ($resourceType = $request->query('resource_type')) {
            $query->where('resource_type', $resourceType);
        }

        if ($from = $request->query('from')) {
            $query->where('created_at', '>=', $from);
        }

        if ($to = $request->query('to')) {
            $query->where('created_at', '<=', $to);
        }

        $logs = $query->orderBy('created_at', 'desc')->paginate(30);

        return response()->json($logs);
    }
}
