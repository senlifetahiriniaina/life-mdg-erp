<?php

use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\BackupController;
use App\Http\Controllers\Admin\RoleManagementController;
use App\Http\Controllers\Admin\ServerController;
use App\Http\Controllers\Api\AiChatController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\MetricsController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
| Module routes are registered by each module's RouteServiceProvider.
| This file handles global API routes only.
*/

// Detailed health check (unauthenticated — used by load balancers and uptime monitors)
Route::get('/health', HealthController::class);

// Prometheus-compatible metrics scrape endpoint.
// Guarded by the MetricsToken middleware: set METRICS_TOKEN and add
// "Authorization: Bearer <token>" to the scraper config. Fails closed in prod.
Route::get('/metrics', MetricsController::class)
    ->middleware(\App\Http\Middleware\MetricsToken::class);

// OpenAPI spec — public, no auth required
Route::get('/v1/openapi', [\App\Http\Controllers\Api\OpenApiController::class, 'spec']);

// JWT Authentication — public endpoints for token issuance/refresh
Route::prefix('v1/auth/jwt')->group(function () {
    Route::post('/login', [\App\Http\Controllers\Api\JwtAuthController::class, 'login']);
    Route::post('/refresh', [\App\Http\Controllers\Api\JwtAuthController::class, 'refresh']);
    Route::post('/verify', [\App\Http\Controllers\Api\JwtAuthController::class, 'verify']);
    Route::post('/logout', [\App\Http\Controllers\Api\JwtAuthController::class, 'logout']);
});

// Two-Factor Authentication (TOTP). Enrolment uses a full token; the login
// challenge (verify) uses the short-lived "2fa:challenge" token. These routes
// must NOT carry the 2fa-enforcement guard, otherwise admins could never enrol.
Route::prefix('v1/auth/2fa')->group(function () {
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/setup', [\App\Http\Controllers\Api\TwoFactorController::class, 'setup']);
        Route::post('/confirm', [\App\Http\Controllers\Api\TwoFactorController::class, 'confirm']);
        Route::delete('/', [\App\Http\Controllers\Api\TwoFactorController::class, 'disable']);
    });
    Route::middleware(['auth:sanctum', 'ability:2fa:challenge'])
        ->post('/verify', [\App\Http\Controllers\Api\TwoFactorController::class, 'verify']);
});

// Dashboard 360° — role-based metrics & AI insights
Route::middleware(['auth:sanctum', '2fa', \App\Http\Middleware\TenantRateLimitMiddleware::class])->prefix('v1')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'metrics']);
    Route::get('/dashboard/ai-insights', [DashboardController::class, 'aiInsights']);

    // Outbound Webhooks
    Route::apiResource('webhooks', \App\Http\Controllers\Api\WebhookController::class);
    Route::post('webhooks/{webhook}/redeliver', [\App\Http\Controllers\Api\WebhookController::class, 'redeliver']);
    Route::get('webhooks-events', [\App\Http\Controllers\Api\WebhookController::class, 'availableEvents']);

    // Logic Rules - No-code automation builder
    Route::prefix('automation')->group(function () {
        Route::apiResource('rules', \App\Http\Controllers\Api\LogicRuleController::class);
        Route::post('rules/{rule}/test', [\App\Http\Controllers\Api\LogicRuleController::class, 'test']);
        Route::post('rules/{rule}/enable', [\App\Http\Controllers\Api\LogicRuleController::class, 'enable']);
        Route::post('rules/{rule}/disable', [\App\Http\Controllers\Api\LogicRuleController::class, 'disable']);
        Route::get('rules/{rule}/executions', [\App\Http\Controllers\Api\LogicRuleController::class, 'executions']);
    });
});

// AI Chat — transversal layer for all modules (dedicated 20/min rate limit)
Route::middleware(['auth:sanctum', 'throttle:ai'])->post('/v1/ai/chat', [AiChatController::class, 'chat']);

// ── Admin API ──────────────────────────────────────────────────────────────
Route::middleware(['auth:sanctum', '2fa', \App\Http\Middleware\TenantRateLimitMiddleware::class])->prefix('v1/admin')->group(function () {
    // Servers
    Route::apiResource('servers', ServerController::class);
    Route::post('servers/{server}/ping', [ServerController::class, 'ping']);
    Route::get('servers/{server}/metrics', [ServerController::class, 'metrics']);
    Route::post('servers/{server}/deploy', [ServerController::class, 'deploy']);
    Route::get('server-providers', [ServerController::class, 'providers']);

    // Backups
    Route::get('backups', [BackupController::class, 'index']);
    Route::post('backups', [BackupController::class, 'store']);
    Route::get('backups/{backup}', [BackupController::class, 'show']);
    Route::delete('backups/{backup}', [BackupController::class, 'destroy']);
    Route::post('backups/{backup}/download', [BackupController::class, 'download']);
    Route::post('backups/{backup}/restore', [BackupController::class, 'restore']);

    // Backup schedules
    Route::get('backup-schedules', [BackupController::class, 'scheduleIndex']);
    Route::post('backup-schedules', [BackupController::class, 'scheduleStore']);
    Route::put('backup-schedules/{backupSchedule}', [BackupController::class, 'scheduleUpdate']);
    Route::delete('backup-schedules/{backupSchedule}', [BackupController::class, 'scheduleDestroy']);

    // Roles & permissions management
    Route::get('roles', [RoleManagementController::class, 'roles']);
    Route::get('permissions', [RoleManagementController::class, 'permissions']);
    Route::get('users', [RoleManagementController::class, 'users']);
    Route::get('users/{user}/roles', [RoleManagementController::class, 'userRoles']);
    Route::post('users/{user}/roles', [RoleManagementController::class, 'assignRole']);
    Route::delete('users/{user}/roles/{role}', [RoleManagementController::class, 'revokeRole']);
    Route::post('users/{user}/permissions', [RoleManagementController::class, 'assignPermission']);
    Route::delete('users/{user}/permissions/{permission}', [RoleManagementController::class, 'revokePermission']);

    // Audit logs
    Route::get('audit-logs', [AuditLogController::class, 'index']);

    // Admin dashboard stats (satellite API-first endpoint)
    Route::get('stats', [\App\Http\Controllers\Admin\AdminStatsController::class, 'index']);

    // Tenant management (superadmin — provision/suspend/purge)
    Route::get('tenants', [\App\Http\Controllers\Admin\TenantManagementController::class, 'index']);
    Route::get('tenants/{tenant}', [\App\Http\Controllers\Admin\TenantManagementController::class, 'show']);
    Route::post('tenants', [\App\Http\Controllers\Admin\TenantManagementController::class, 'store']);
    Route::put('tenants/{tenant}', [\App\Http\Controllers\Admin\TenantManagementController::class, 'update']);
    Route::post('tenants/{tenant}/suspend', [\App\Http\Controllers\Admin\TenantManagementController::class, 'suspend']);
    Route::post('tenants/{tenant}/activate', [\App\Http\Controllers\Admin\TenantManagementController::class, 'activate']);
    Route::delete('tenants/{tenant}', [\App\Http\Controllers\Admin\TenantManagementController::class, 'destroy']);
    Route::post('tenants/{tenant}/gdpr-purge', [\App\Http\Controllers\Admin\TenantManagementController::class, 'gdprPurge']);
});
