<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin\AuditLog;
use App\Models\Admin\Backup;
use App\Models\Admin\ServerConfig;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminStatsController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user?->hasAnyRole(['super-admin', 'admin', 'system-admin'])) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $totalUsers    = User::count();
        $activeServers = ServerConfig::where('status', 'active')->count();
        $lastBackup    = Backup::where('status', 'completed')->latest('completed_at')->first();
        $storageUsed   = Backup::where('status', 'completed')->sum('size_bytes');
        $recentAudits  = AuditLog::latest()->limit(5)->get(['id', 'action', 'user_id', 'created_at']);

        return response()->json([
            'data' => [
                'total_users'          => $totalUsers,
                'active_servers'       => $activeServers,
                'last_backup_at'       => $lastBackup?->completed_at?->toIso8601String(),
                'storage_used_bytes'   => $storageUsed,
                'pending_audit_alerts' => 0,
                'recent_audit_entries' => $recentAudits,
            ],
        ]);
    }
}
