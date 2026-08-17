<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Admin\AuditLog;
use App\Models\Admin\Backup;
use App\Models\Admin\BackupSchedule;
use App\Models\Admin\ServerConfig;
use App\Models\User;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

class AdminWebController extends Controller
{
    private const ADMIN_ROLES = [
        'super-admin', 'admin', 'system-admin', 'security-admin',
        'billing-admin', 'support-admin', 'content-admin', 'tenant-admin',
    ];

    private function authorizeAdmin(): void
    {
        $user = request()->user();
        if (! ($user instanceof User) || ! $user->hasAnyRole(self::ADMIN_ROLES)) {
            abort(403, 'Access denied.');
        }
    }

    public function index(): Response
    {
        $this->authorizeAdmin();

        $totalUsers    = User::count();
        $activeServers = ServerConfig::where('status', 'active')->count();
        $lastBackup    = Backup::where('status', 'completed')->latest('completed_at')->first();
        $storageUsed   = Backup::where('status', 'completed')->sum('size_bytes');

        return Inertia::render('Admin/Index', [
            'stats' => [
                'total_users'          => $totalUsers,
                'active_servers'       => $activeServers,
                'last_backup_at'       => $lastBackup?->completed_at?->toIso8601String(),
                'storage_used_bytes'   => $storageUsed,
                'pending_audit_alerts' => 0,
            ],
        ]);
    }

    public function servers(): Response
    {
        $this->authorizeAdmin();

        return Inertia::render('Admin/Servers/Index', [
            'servers' => ServerConfig::latest()->paginate(20),
        ]);
    }

    public function backups(): Response
    {
        $this->authorizeAdmin();

        return Inertia::render('Admin/Backups/Index', [
            'backups'   => Backup::with('triggeredBy:id,name')->latest()->paginate(20),
            'schedules' => BackupSchedule::latest()->get(),
        ]);
    }

    public function users(): Response
    {
        $this->authorizeAdmin();

        return Inertia::render('Admin/Users/Index', [
            'users' => User::with('roles:id,name')->latest()->paginate(20),
            'roles' => Role::all(),
        ]);
    }

    public function audit(): Response
    {
        $this->authorizeAdmin();

        return Inertia::render('Admin/AuditLog/Index', [
            'logs' => AuditLog::with('user:id,name,email')->latest()->paginate(50),
        ]);
    }

    /**
     * Had no route at all until now — TenantExchanges/Index.vue existed
     * with a full UI (self-fetches via axios, no server props needed) but
     * nothing in routes/web.php ever pointed to it.
     */
    public function exchanges(): Response
    {
        $this->authorizeAdmin();

        return Inertia::render('Admin/TenantExchanges/Index');
    }

    public function sandboxes(): Response
    {
        $this->authorizeAdmin();

        return Inertia::render('Admin/Sandboxes/Index');
    }
}
