<?php

use Illuminate\Support\Facades\Route;
use Modules\Core\Http\Controllers\Api\AccountController;
use Modules\Core\Http\Controllers\Api\AIAssistantController;
use Modules\Core\Http\Controllers\Api\ApprovalController;
use Modules\Core\Http\Controllers\Api\AuditLogController;
use Modules\Core\Http\Controllers\Api\AuthController;
use Modules\Core\Http\Controllers\Api\ConsentController;
use Modules\Core\Http\Controllers\Api\ConsentWithdrawalController;
use Modules\Core\Http\Controllers\Api\CustomFieldController;
use Modules\Core\Http\Controllers\Api\GdprController;
use Modules\Core\Http\Controllers\Api\GlobalSearchController;
use Modules\Core\Http\Controllers\Api\HelpController;
use Modules\Core\Http\Controllers\Api\ImportController;
use Modules\Core\Http\Controllers\Api\ModuleController;
use Modules\Core\Http\Controllers\Api\NotificationController;
use Modules\Core\Http\Controllers\Api\PushTokenController;
use Modules\Core\Http\Controllers\Api\RealtimeController;
use Modules\Core\Http\Controllers\Api\SyncController;
use Modules\Core\Http\Controllers\Api\TenantController;
use Modules\Core\Http\Controllers\Api\TenantExchangeController;
use Modules\Core\Http\Controllers\Api\TenantRegistrationController;
use Modules\Core\Http\Controllers\Api\WorkflowController;

/*
|--------------------------------------------------------------------------
| Core API Routes — v1
|--------------------------------------------------------------------------
*/
Route::prefix('v1')->group(function () {

    // ─── Tenant Registration (public) ─────────────────────────────────────────
    Route::prefix('tenants')->group(function () {
        Route::post('register', [TenantRegistrationController::class, 'register']);
        Route::get('check-slug/{slug}', [TenantRegistrationController::class, 'checkSlug']);
        Route::middleware(['auth:sanctum', 'session.security', 'tenancy.user'])->get('me', [TenantRegistrationController::class, 'me']);
    });

    // ─── Tenant Management (super-admin only) ──────────────────────────────────
    Route::middleware(['auth:sanctum', 'session.security', 'tenancy.user', 'role:super-admin', 'throttle:simple_get'])->prefix('tenants')->group(function () {
        Route::get('/', [TenantController::class, 'index']);
        Route::get('{id}', [TenantController::class, 'show']);
        Route::get('{id}/modules', [TenantController::class, 'modules']);
        Route::get('{id}/stats', [TenantController::class, 'stats']);
        Route::middleware('throttle:create_post')->group(function () {
            Route::put('{id}', [TenantController::class, 'update']);
            Route::delete('{id}', [TenantController::class, 'destroy']);
            Route::post('{id}/suspend', [TenantController::class, 'suspend']);
            Route::post('{id}/activate', [TenantController::class, 'activate']);
            Route::post('{id}/reprovision', [TenantController::class, 'reprovision']);
            Route::put('{id}/modules', [TenantController::class, 'updateModules']);
        });
    });

    // ─── Authentication ───────────────────────────────────────────────────────
    Route::prefix('auth')->group(function () {
        Route::middleware('throttle:auth')->group(function () {
            Route::post('register', [AuthController::class, 'register']);
            Route::post('login', [AuthController::class, 'login']);
        });
        Route::middleware(['auth:sanctum', 'session.security', 'tenancy.user', 'throttle:simple_get'])->group(function () {
            Route::post('logout', [AuthController::class, 'logout']);
            Route::get('me', [AuthController::class, 'me']);
            Route::post('refresh', [AuthController::class, 'refresh']);
        });
    });

    // ─── Module Management ────────────────────────────────────────────────────
    Route::middleware(['auth:sanctum', 'session.security', 'tenancy.user', 'throttle:simple_get'])->prefix('modules')->group(function () {
        Route::get('/', [ModuleController::class, 'index']);
        Route::middleware('throttle:create_post')->group(function () {
            Route::post('{module}/enable', [ModuleController::class, 'enable']);
            Route::post('{module}/disable', [ModuleController::class, 'disable']);
            Route::put('{module}/settings', [ModuleController::class, 'updateSettings']);
        });
    });

    // ─── Offline Sync ─────────────────────────────────────────────────────────
    Route::middleware(['auth:sanctum', 'session.security', 'tenancy.user', 'throttle:sync'])->prefix('sync')->group(function () {
        Route::post('push', [SyncController::class, 'push']);
        Route::get('pull', [SyncController::class, 'pull']);
    });

    // ─── AI Assistant ─────────────────────────────────────────────────────────
    Route::middleware(['auth:sanctum', 'session.security', 'tenancy.user', 'throttle:expensive'])->prefix('ai')->group(function () {
        Route::post('ask', [AIAssistantController::class, 'ask']);
        Route::post('analyze', [AIAssistantController::class, 'analyzeData']);
        Route::post('generate-document', [AIAssistantController::class, 'generateDocument']);
    });

    // ─── Notifications ────────────────────────────────────────────────────────
    Route::middleware(['auth:sanctum', 'session.security', 'tenancy.user', 'throttle:simple_get'])->prefix('notifications')->group(function () {
        Route::get('/', [NotificationController::class, 'index']);
        Route::get('unread-count', [NotificationController::class, 'unreadCount']);
        Route::middleware('throttle:create_post')->group(function () {
            Route::post('read-all', [NotificationController::class, 'markAllRead']);
            Route::post('{id}/read', [NotificationController::class, 'markRead']);
            Route::delete('{id}', [NotificationController::class, 'destroy']);
        });
    });

    // ─── Push Tokens ─────────────────────────────────────────────────────────
    Route::middleware(['auth:sanctum', 'session.security', 'tenancy.user', 'throttle:create_post'])->prefix('push-tokens')->group(function () {
        Route::post('/', [PushTokenController::class, 'register']);
        Route::delete('/', [PushTokenController::class, 'deregister']);
    });

    // ─── Global Search ────────────────────────────────────────────────────────
    Route::middleware(['auth:sanctum', 'session.security', 'tenancy.user', 'throttle:complex_get'])->get('search', GlobalSearchController::class);

    // ─── Account / RGPD ──────────────────────────────────────────────────────
    Route::middleware(['auth:sanctum', 'session.security', 'tenancy.user', 'throttle:simple_get'])->prefix('account')->group(function () {
        Route::get('export', [AccountController::class, 'export']);
        Route::middleware('throttle:create_post')->delete('/', [AccountController::class, 'destroy']);
    });

    // ─── Consent (GDPR) ──────────────────────────────────────────────────────
    Route::prefix('auth/consent')->group(function () {
        Route::middleware('throttle:simple_get')->group(function () {
            Route::get('/', [ConsentController::class, 'index']);
            Route::get('check', [ConsentController::class, 'check']);
        });
        Route::middleware('throttle:create_post')->group(function () {
            Route::post('/', [ConsentController::class, 'store']);
            Route::post('bulk', [ConsentController::class, 'recordBulk']);
        });
        Route::middleware(['auth:sanctum', 'session.security', 'tenancy.user', 'throttle:create_post'])->post('withdraw', [ConsentController::class, 'withdraw']);
    });

    // ─── Contextual Help ─────────────────────────────────────────────────────
    Route::middleware(['auth:sanctum', 'session.security', 'tenancy.user', 'throttle:simple_get'])->prefix('help')->group(function () {
        Route::get('/', [HelpController::class, 'index']);
        Route::get('context', [HelpController::class, 'show']);
    });

    // ─── Custom Fields ────────────────────────────────────────────────────────
    Route::middleware(['auth:sanctum', 'session.security', 'tenancy.user', 'throttle:simple_get'])->prefix('core')->group(function () {
        Route::get('custom-fields/entity/{entityType}', [CustomFieldController::class, 'fieldsForEntity']);
        Route::get('custom-fields', [CustomFieldController::class, 'index']);
        Route::get('custom-fields/{field}', [CustomFieldController::class, 'show']);
        Route::get('entity/{entityType}/{entityId}/custom-values', [CustomFieldController::class, 'getValues']);
        Route::middleware('throttle:create_post')->group(function () {
            Route::post('custom-fields/validate', [CustomFieldController::class, 'validateValues']);
            Route::post('custom-fields/search', [CustomFieldController::class, 'searchByField']);
            Route::post('custom-fields', [CustomFieldController::class, 'store']);
            Route::put('custom-fields/{field}', [CustomFieldController::class, 'update']);
            Route::delete('custom-fields/{field}', [CustomFieldController::class, 'destroy']);
            Route::post('entity/{entityType}/{entityId}/custom-values', [CustomFieldController::class, 'saveValues']);
        });
    });

    // ─── GDPR Compliance ─────────────────────────────────────────────────────
    Route::middleware(['auth:sanctum', 'session.security', 'tenancy.user', 'throttle:simple_get'])->prefix('gdpr')->group(function () {
        Route::get('sar-status', [GdprController::class, 'getSARStatus']);
        Route::get('export', [GdprController::class, 'exportPersonalData']);
        Route::get('compliance-status', [GdprController::class, 'complianceStatus']);
        Route::get('requests', [GdprController::class, 'listRequests']);
        Route::get('export/{request_id}/download', [GdprController::class, 'downloadExport']);
        Route::middleware('throttle:create_post')->group(function () {
            Route::post('request-sar', [GdprController::class, 'requestSAR']);
            Route::post('delete-account', [GdprController::class, 'deleteAccount']);
            Route::post('delete', [GdprController::class, 'deletePersonalData']);
        });
    });

    // Simple consent update endpoint (used by frontend)
    Route::middleware(['auth:sanctum', 'session.security', 'tenancy.user', 'throttle:create_post'])->put('consent', function (\Illuminate\Http\Request $request) {
        $user = $request->user();
        $data = $request->validate(['cookie_consent' => 'nullable|boolean', 'marketing_consent' => 'nullable|boolean']);
        $update = [];
        if (array_key_exists('cookie_consent', $data)) {
            $update['cookie_consent'] = $data['cookie_consent'];
            $update['cookie_consent_at'] = now();
        }
        if (array_key_exists('marketing_consent', $data)) {
            $update['marketing_consent'] = $data['marketing_consent'];
            $update['marketing_consent_at'] = now();
        }
        $user->update($update);
        return response()->json(['message' => 'Consent updated.']);
    });

    // ─── Consent Management (GDPR Articles 7 & 21) ─────────────────────────
    Route::middleware(['auth:sanctum', 'session.security', 'tenancy.user', 'throttle:simple_get'])->prefix('consent')->group(function () {
        Route::get('preferences', [ConsentWithdrawalController::class, 'getPreferences']);
        Route::get('export', [ConsentWithdrawalController::class, 'exportHistory']);
        Route::middleware('throttle:create_post')->group(function () {
            Route::post('grant', [ConsentWithdrawalController::class, 'grant']);
            Route::post('withdraw', [ConsentWithdrawalController::class, 'withdraw']);
            Route::delete('history', [ConsentWithdrawalController::class, 'deleteHistory']);
        });
    });

    Route::middleware(['auth:sanctum', 'session.security', 'tenancy.user', 'throttle:simple_get'])->prefix('core/gdpr')->group(function () {
        Route::get('users/{userId}/consents', [GdprController::class, 'userConsents']);
        Route::get('export-download/{tokenOrId}', [GdprController::class, 'downloadExport']);
        Route::get('export', [GdprController::class, 'exportPersonalDataForUser']);
        Route::middleware('throttle:create_post')->group(function () {
            Route::post('consents', [GdprController::class, 'recordConsent']);
            Route::post('revoke', [GdprController::class, 'revokeConsent']);
            Route::post('requests', [GdprController::class, 'createDataRequest']);
            Route::post('sar', [GdprController::class, 'submitSAR']);
            Route::post('sar-confirm', [GdprController::class, 'confirmSAR']);
            Route::post('delete-account', [GdprController::class, 'gdprDeleteAccount']);
            Route::post('consent', [GdprController::class, 'updateConsent']);
        });

        Route::middleware('role:admin,manager')->group(function () {
            Route::get('consents', [GdprController::class, 'consents']);
            Route::get('requests/pending', [GdprController::class, 'pendingRequests']);
            Route::get('requests/overdue', [GdprController::class, 'overdueRequests']);
            Route::get('dashboard', [GdprController::class, 'dashboardStats']);
            Route::get('consent-breakdown', [GdprController::class, 'consentBreakdown']);
            Route::get('requests', [GdprController::class, 'dataRequests']);
            Route::get('requests/{request}', [GdprController::class, 'showRequest']);
            Route::middleware('throttle:create_post')->post('requests/{request}/process', [GdprController::class, 'processRequest']);
        });
    });

    // ─── Audit Log alias (for GDPR consent withdrawal tests) ──────────────────
    Route::middleware(['auth:sanctum', 'session.security', 'tenancy.user'])->get('core/audit-log', [AuditLogController::class, 'index']);

    // ─── Audit Logs — admin/manager only ──────────────────────────────────────
    Route::middleware(['auth:sanctum', 'session.security', 'tenancy.user', 'role:admin,manager', 'throttle:complex_get'])->prefix('core/audit-logs')->group(function () {
        Route::get('stats', [AuditLogController::class, 'stats']);
        Route::get('user/{userId}', [AuditLogController::class, 'userActivity']);
        Route::get('subject/{type}/{id}', [AuditLogController::class, 'subjectHistory']);
        Route::get('/', [AuditLogController::class, 'index']);
        Route::get('{auditLog}', [AuditLogController::class, 'show']);
    });

    // ─── Workflow Engine ──────────────────────────────────────────────────────
    Route::middleware(['auth:sanctum', 'session.security', 'tenancy.user', 'throttle:simple_get'])->prefix('core/workflows')->group(function () {
        Route::get('/', [WorkflowController::class, 'index']);
        Route::get('{module}/{type}', [WorkflowController::class, 'show']);
        Route::get('{module}/{type}/{subjectId}/state', [WorkflowController::class, 'state']);
        Route::middleware('throttle:create_post')->post('{module}/{type}/{subjectId}/transition', [WorkflowController::class, 'transition']);
    });

    // ─── Approval Workflows ───────────────────────────────────────────────────
    Route::middleware(['auth:sanctum', 'session.security', 'tenancy.user', 'throttle:simple_get'])->prefix('core/approvals')->group(function () {
        Route::get('workflows', [ApprovalController::class, 'indexWorkflows']);
        Route::get('workflows/{approvalWorkflow}', [ApprovalController::class, 'showWorkflow']);
        Route::get('pending', [ApprovalController::class, 'pending']);
        Route::get('instances/{subjectType}/{subjectId}', [ApprovalController::class, 'getInstance']);
        Route::middleware('throttle:create_post')->group(function () {
            Route::post('workflows', [ApprovalController::class, 'storeWorkflow']);
            Route::put('workflows/{approvalWorkflow}', [ApprovalController::class, 'updateWorkflow']);
            Route::delete('workflows/{approvalWorkflow}', [ApprovalController::class, 'destroyWorkflow']);
            Route::post('instances/{approvalInstance}/decide', [ApprovalController::class, 'decide']);
            Route::post('instances/{approvalInstance}/cancel', [ApprovalController::class, 'cancel']);
        });
    });

    // ─── Cross-Tenant Data Exchange ───────────────────────────────────────────
    Route::middleware(['auth:sanctum', 'session.security', 'tenancy.user', 'throttle:simple_get'])->prefix('core/exchanges')->group(function () {
        Route::get('incoming', [TenantExchangeController::class, 'incoming']);
        Route::get('outgoing', [TenantExchangeController::class, 'outgoing']);
        Route::get('{id}', [TenantExchangeController::class, 'show']);
        Route::middleware('throttle:create_post')->group(function () {
            Route::post('/', [TenantExchangeController::class, 'store']);
            Route::post('{id}/accept', [TenantExchangeController::class, 'accept']);
            Route::post('{id}/reject', [TenantExchangeController::class, 'reject']);
            Route::delete('{id}', [TenantExchangeController::class, 'cancel']);
        });
    });

    // ─── Data Import ─────────────────────────────────────────────────────────
    Route::middleware(['auth:sanctum', 'session.security', 'tenancy.user', 'throttle:simple_get'])->prefix('import')->group(function () {
        Route::get('jobs', [ImportController::class, 'index']);
        Route::get('jobs/{job}', [ImportController::class, 'show']);
        Route::get('jobs/{job}/rows', [ImportController::class, 'rows']);
        Route::middleware('throttle:create_post')->group(function () {
            Route::post('upload', [ImportController::class, 'upload']);
            Route::put('jobs/{job}/mapping', [ImportController::class, 'updateMapping']);
            Route::post('jobs/{job}/execute', [ImportController::class, 'execute']);
            Route::post('jobs/{job}/rollback', [ImportController::class, 'rollback']);
        });
    });

    // ─── Realtime Updates (WebSocket/SSE) ─────────────────────────────────────
    Route::middleware(['auth:sanctum', 'session.security', 'tenancy.user', 'throttle:simple_get'])->prefix('realtime')->group(function () {
        Route::get('subscribe', [RealtimeController::class, 'subscribe']);
        Route::get('health', [RealtimeController::class, 'health']);
    });
});
