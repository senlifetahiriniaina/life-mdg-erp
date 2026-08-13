<?php

declare(strict_types=1);

namespace Modules\Core\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Core\Models\ApprovalInstance;
use Modules\Core\Models\DataRequest;
use Modules\Core\Models\GdprConsent;
use Modules\Core\Services\GdprService;

/**
 * @group Controllers - Gdpr
 *
 * Manage Gdpr resources.
 */
class GdprController extends Controller
{
    public function __construct(private readonly GdprService $gdprService) {}

    /**
     * GET /core/gdpr/consents?user_id=...
     */
    public function consents(Request $request): JsonResponse
    {
        $query = GdprConsent::query();

        if ($request->has('user_id')) {
            $query->where('user_id', $request->integer('user_id'));
        }

        return response()->json($query->get());
    }

    /**
     * POST /core/gdpr/consents
     */
    public function recordConsent(Request $request): JsonResponse
    {
        $data = $request->validate([
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'email' => ['nullable', 'string', 'max:255'],
            'consent_type' => ['required', 'string', 'in:marketing,analytics,functional,necessary'],
            'granted' => ['required', 'boolean'],
            'ip_address' => ['nullable', 'string', 'max:45'],
            'user_agent' => ['nullable', 'string'],
            'source' => ['nullable', 'string', 'max:100'],
        ]);

        // Non-admin callers may only record consent for themselves
        if (! $request->user()->hasAnyRole(['admin', 'manager'])) {
            $data['user_id'] = $request->user()->id;
        }

        $consent = $this->gdprService->recordConsent($data);

        return response()->json($consent, 201);
    }

    /**
     * POST /core/gdpr/revoke
     */
    public function revokeConsent(Request $request): JsonResponse
    {
        $data = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'consent_type' => ['required', 'string', 'in:marketing,analytics,functional,necessary'],
        ]);

        // Non-admin callers may only revoke their own consents
        if (! $request->user()->hasAnyRole(['admin', 'manager']) && $data['user_id'] !== $request->user()->id) {
            abort(403, 'You may only revoke your own consents.');
        }

        $result = $this->gdprService->revokeConsent($data['user_id'], $data['consent_type']);

        if (! $result) {
            return response()->json(['message' => 'Consent record not found.'], 404);
        }

        return response()->json(['message' => 'Consent revoked successfully.']);
    }

    /**
     * GET /core/gdpr/users/{userId}/consents
     */
    public function userConsents(Request $request, int $userId): JsonResponse
    {
        // Non-admin callers may only view their own consents
        if (! $request->user()->hasAnyRole(['admin', 'manager']) && $userId !== $request->user()->id) {
            abort(403, 'You may only view your own consents.');
        }

        $consents = $this->gdprService->getUserConsents($userId);

        return response()->json($consents);
    }

    /**
     * GET /core/gdpr/requests
     */
    public function dataRequests(Request $request): JsonResponse
    {
        return response()->json(DataRequest::all());
    }

    /**
     * POST /core/gdpr/requests
     */
    public function createDataRequest(Request $request): JsonResponse
    {
        $data = $request->validate([
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'email' => ['required', 'string', 'max:255'],
            'request_type' => ['required', 'string', 'in:export,deletion,rectification,access'],
            'notes' => ['nullable', 'string'],
        ]);

        $dataRequest = $this->gdprService->createDataRequest($data);

        return response()->json($dataRequest, 201);
    }

    /**
     * GET /core/gdpr/requests/pending
     */
    public function pendingRequests(): JsonResponse
    {
        return response()->json($this->gdprService->getPendingRequests());
    }

    /**
     * GET /core/gdpr/requests/overdue
     */
    public function overdueRequests(): JsonResponse
    {
        return response()->json($this->gdprService->getOverdueRequests());
    }

    /**
     * GET /core/gdpr/requests/{request}
     */
    public function showRequest(DataRequest $request): JsonResponse
    {
        return response()->json($request);
    }

    /**
     * POST /core/gdpr/requests/{request}/process
     */
    public function processRequest(Request $httpRequest, DataRequest $request): JsonResponse
    {
        $data = $httpRequest->validate([
            'action' => ['required', 'string', 'in:export,delete,reject'],
            'reason' => ['nullable', 'string'],
        ]);

        $result = match ((string) $data['action']) {
            'export' => $this->gdprService->processExportRequest($request),
            'delete' => $this->gdprService->processDeletionRequest($request),
            default => tap($request, fn ($r) => $r->reject((string) ($data['reason'] ?? ''))),
        };

        return response()->json($result->fresh());
    }

    /**
     * GET /core/gdpr/dashboard
     */
    public function dashboardStats(): JsonResponse
    {
        return response()->json($this->gdprService->getDashboardStats());
    }

    /**
     * GET /core/gdpr/consent-breakdown
     */
    public function consentBreakdown(): JsonResponse
    {
        return response()->json($this->gdprService->getConsentBreakdown());
    }

    /**
     * POST /core/gdpr/sar — Submit a Subject Access Request
     */
    public function submitSAR(Request $request): JsonResponse
    {
        $user = $request->user();

        $token = Str::random(64);
        DB::table('gdpr_sar_requests')->insert([
            'user_id' => $user->id,
            'email' => $request->input('email', $user->email),
            'format' => $request->input('format', 'json'),
            'status' => 'pending',
            'confirmation_token' => $token,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Log to GDPR audit log
        if (DB::getSchemaBuilder()->hasTable('gdpr_audit_logs')) {
            DB::table('gdpr_audit_logs')->insert([
                'action' => 'sar_requested',
                'user_id' => $user->id,
                'timestamp' => now(),
                'immutable' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return response()->json(['message' => 'SAR submitted', 'token' => $token], 202);
    }

    /**
     * POST /core/gdpr/sar-confirm — Confirm a SAR request
     */
    public function confirmSAR(Request $request): JsonResponse
    {
        $token = $request->input('token');
        $sar = DB::table('gdpr_sar_requests')->where('confirmation_token', $token)->first();

        if (!$sar) {
            return response()->json(['message' => 'Invalid token'], 404);
        }

        // Check 48-hour expiry
        $createdAt = \Carbon\Carbon::parse($sar->created_at);
        if ($createdAt->addHours(48)->isPast()) {
            return response()->json(['message' => 'Confirmation link has expired'], 410);
        }

        DB::table('gdpr_sar_requests')->where('id', $sar->id)->update([
            'status' => 'confirmed',
            'confirmed_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json(['message' => 'SAR confirmed']);
    }

    /**
     * POST /core/gdpr/delete-account — GDPR Right to Erasure
     */
    public function gdprDeleteAccount(Request $request): JsonResponse
    {
        $user = $request->user();
        $escalateToAdmin = $request->boolean('escalate_to_admin', false);

        // Check for pending approvals
        $pendingApprovals = ApprovalInstance::where('initiated_by', $user->id)
            ->where('status', 'pending')
            ->get();

        if ($pendingApprovals->isNotEmpty() && !$escalateToAdmin) {
            return response()->json([
                'message' => 'Cannot delete account: you have pending approvals that must be resolved first',
            ], 409);
        }

        // Escalate pending approvals if requested
        if ($escalateToAdmin && $pendingApprovals->isNotEmpty()) {
            $admin = User::where('id', '!=', $user->id)->first();
            foreach ($pendingApprovals as $approval) {
                $approval->update([
                    'escalated_to' => $admin?->id,
                    'escalation_reason' => 'Account owner requested deletion - user deletion',
                ]);
            }
        }

        // Anonymize audit logs (GDPR right to erasure)
        $userId = (int) $user->id;
        foreach (['core_audit_logs', 'audit_log_entries'] as $auditTable) {
            if (DB::getSchemaBuilder()->hasTable($auditTable)) {
                try {
                    // Use raw PDO to bypass Eloquent/FK constraints and set user_id to 0
                    // (anonymized sentinel value per GDPR right to erasure)
                    $pdo = DB::connection()->getPdo();
                    if (DB::connection()->getDriverName() === 'sqlite') {
                        $pdo->exec('PRAGMA foreign_keys = OFF');
                    }
                    $stmt = $pdo->prepare("UPDATE {$auditTable} SET user_id = 0, ip_address = NULL, user_agent = NULL WHERE user_id = ?");
                    $stmt->execute([$userId]);
                    if (DB::connection()->getDriverName() === 'sqlite') {
                        $pdo->exec('PRAGMA foreign_keys = ON');
                    }
                } catch (\Throwable $e) {
                    // If FK constraints block this, null out instead
                    try {
                        DB::table($auditTable)
                            ->where('user_id', $userId)
                            ->update(['user_id' => null, 'ip_address' => null]);
                    } catch (\Throwable $e2) {
                        // Silently skip on persistent failure
                    }
                }
            }
        }

        // Delete related approval instances with approved status
        ApprovalInstance::where('initiated_by', $user->id)
            ->where('status', '!=', 'pending')
            ->delete();

        // Delete the user
        $user->delete();

        return response()->json(['message' => 'Account deleted successfully']);
    }

    /**
     * POST /core/gdpr/consent — Update consent preferences
     */
    public function updateConsent(Request $request): JsonResponse
    {
        $user = $request->user();

        $existing = DB::table('gdpr_consents')->where('user_id', $user->id)->first();

        $data = [
            'user_id' => $user->id,
            'analytics' => $request->input('analytics', $existing?->analytics ?? false) ? 1 : 0,
            'marketing' => $request->input('marketing', $existing?->marketing ?? false) ? 1 : 0,
            'updated_at' => now(),
        ];

        if ($existing) {
            DB::table('gdpr_consents')->where('user_id', $user->id)->update($data);
        } else {
            $data['created_at'] = now();
            DB::table('gdpr_consents')->insert($data);
        }

        return response()->json(['message' => 'Consent updated', 'consent' => $data]);
    }

    /**
     * GET /core/gdpr/export — Export personal data for the authenticated user
     */
    public function exportPersonalDataForUser(Request $request): JsonResponse
    {
        $user = $request->user();

        // Build export data with decrypted PII
        $userData = [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
        ];

        // Try to decrypt phone and address if set
        try {
            if ($user->phone) {
                $userData['phone'] = decrypt($user->phone);
            }
        } catch (\Throwable $e) {
            $userData['phone'] = $user->phone;
        }

        try {
            if ($user->address) {
                $userData['address'] = decrypt($user->address);
            }
        } catch (\Throwable $e) {
            $userData['address'] = $user->address;
        }

        return response()->json(['user' => $userData]);
    }

    /**
     * GET /core/gdpr/export/{request_id}/download or /core/gdpr/export-download/{token}
     * Secure download of encrypted SAR export.
     * Token-based access prevents unauthorized downloads.
     */
    public function downloadExport($tokenOrId)
    {
        // Try to download using token first (most secure)
        $result = $this->gdprService->generateExportDownload($tokenOrId);

        if (! $result['success']) {
            return response()->json(['error' => $result['error']], 404);
        }

        return response()->streamDownload(
            fn () => print ($result['content']),
            $result['filename'],
            [
                'Content-Type' => 'application/json; charset=UTF-8',
                'Content-Length' => strlen($result['content']),
                'X-Content-Type-Options' => 'nosniff',
                'X-Frame-Options' => 'DENY',
                'Content-Disposition' => 'attachment; filename="'.$result['filename'].'"',
            ]
        );
    }
}
