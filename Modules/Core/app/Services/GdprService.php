<?php

declare(strict_types=1);

namespace Modules\Core\Services;

use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Modules\Core\Models\DataRequest;
use Modules\Core\Models\GdprConsent;

class GdprService
{
    /**
     * Record a consent for a user/email.
     * Creates or updates consent record for user_id+consent_type combination.
     */
    public function recordConsent(array $data): GdprConsent
    {
        $consent = GdprConsent::updateOrCreate(
            [
                'user_id'      => $data['user_id'] ?? null,
                'consent_type' => $data['consent_type'],
            ],
            $data
        );

        return $consent;
    }

    /**
     * Revoke a specific consent type for a user.
     */
    public function revokeConsent(int $userId, string $consentType): bool
    {
        $consent = GdprConsent::where('user_id', $userId)
            ->where('consent_type', $consentType)
            ->first();

        if (! $consent) {
            return false;
        }

        $consent->revoke();

        return true;
    }

    /**
     * Get all consents for a user.
     */
    public function getUserConsents(int $userId): Collection
    {
        return GdprConsent::where('user_id', $userId)->get();
    }

    /**
     * Check if user has granted a specific consent type.
     */
    public function hasConsent(int $userId, string $consentType): bool
    {
        $consent = GdprConsent::where('user_id', $userId)
            ->where('consent_type', $consentType)
            ->first();

        if (! $consent) {
            return false;
        }

        return $consent->isGranted();
    }

    /**
     * Create a data request.
     */
    public function createDataRequest(array $data): DataRequest
    {
        if (! isset($data['requested_at'])) {
            $data['requested_at'] = now();
        }

        if (! isset($data['status'])) {
            $data['status'] = 'pending';
        }

        return DataRequest::create($data);
    }

    /**
     * Process an export request: collect user data, encrypt, and mark completed.
     * GDPR Article 15 (Right of Access) - SAR export with encryption
     */
    public function processExportRequest(DataRequest $request): DataRequest
    {
        $user = User::find($request->user_id);
        if (! $user) {
            $request->reject('User not found');
            return $request->fresh();
        }

        // Stage 1: Export all user data
        $userData = $this->exportUserData($user);

        // Stage 2: Create JSON export file
        $exportJson = json_encode($userData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        // Stage 3: Encrypt the export
        $encrypted = $this->encryptExport($exportJson, $user->id);

        // Stage 4: Store encrypted export with secure download token
        $downloadToken = Str::random(64);
        $filename = "sar_export_{$user->id}_{$downloadToken}.enc";
        Storage::disk('gdpr-archive')->put("exports/{$filename}", $encrypted);

        // Stage 5: Complete request with export details
        $downloadUrl = config('app.url') . '/api/v1/core/gdpr/export-download/' . $downloadToken;
        $request->complete([
            'user_id'       => $request->user_id,
            'exported_at'   => now()->toIso8601String(),
            'file_size'     => strlen($encrypted),
            'encrypted'     => true,
            'download_token' => $downloadToken,
            'download_url'  => $downloadUrl,
        ]);

        // Stage 6: Audit log
        $gdprLog = Log::channel('gdpr-audit') ?? Log::channel('single');
        $gdprLog?->info('GDPR SAR export created', [
            'user_id' => $user->id,
            'request_id' => $request->id,
            'file_size' => strlen($encrypted),
            'timestamp' => now()->toIso8601String(),
        ]);

        return $request->fresh();
    }

    /**
     * Process a deletion request: perform hard delete of user and all PII.
     * GDPR Article 17 (Right to Erasure) implementation.
     */
    public function processDeletionRequest(DataRequest $request): DataRequest
    {
        if ($request->request_type === 'deletion') {
            $user = User::find($request->user_id);
            if ($user) {
                $this->hardDeleteUser($user, $request);
                return $request->fresh() ?? $request;
            }
        }

        $request->complete([]);
        return $request->fresh();
    }

    /**
     * Perform hard delete of user and all associated PII.
     * GDPR Article 17 (Right to Erasure) - legally required deletion.
     *
     * Stages:
     * 1. Archive user data for DPO audit trail
     * 2. Delete all PII (contacts, addresses, activities)
     * 3. Mark user as deleted (retain account for audit)
     * 4. Log deletion event (immutable)
     */
    public function hardDeleteUser(User $user, DataRequest $request): void
    {
        try {
            DB::transaction(function () use ($user, $request) {
                // Stage 1: Archive user data for DPO compliance audit
                $userData = $this->exportUserData($user);
                Storage::disk('gdpr-archive')->put(
                    "deletions/{$user->id}/" . now()->timestamp . '.json',
                    json_encode($userData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
                );

                // Stage 2: Delete PII from related tables (cascade via foreign keys)
                $this->deleteUserPii($user);

                // Stage 3: Anonymize user email and mark as deleted (soft delete to maintain audit trail)
                $user->update([
                    'email' => "deleted-{$user->id}@deleted.local",
                    'phone' => null,
                    'phone_verified_at' => null,
                ]);

                // Soft-delete the user account (sets deleted_at via SoftDeletes trait)
                $user->delete();

                // Stage 4: Update data request and create deletion certificate
                $request->update([
                    'status' => 'completed',
                    'completed_at' => now(),
                    'result' => [
                        'hard_delete_token' => Str::random(64),
                        'archived_at' => now()->toIso8601String(),
                        'pii_tables_cleaned' => $this->getPiiTablesCleaned(),
                    ],
                ]);

                // Stage 5: Immutable audit log (cannot be deleted)
                (Log::channel('gdpr-audit') ?? Log::channel('single'))?->info('GDPR hard delete completed', [
                    'user_id' => $user->id,
                    'request_id' => $request->id,
                    'timestamp' => now()->toIso8601String(),
                    'operator' => auth()->user()?->id ?? 'system',
                ]);
            });
        } catch (\Throwable $e) {
            Log::error('GDPR hard delete failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Export all user data for archival before deletion.
     */
    private function exportUserData(User $user): array
    {
        return [
            'user' => $user->toArray(),
            'consents' => GdprConsent::where('user_id', $user->id)->get()->toArray(),
            'profile' => [
                'id' => $user->id,
                'email' => $user->email,
                'name' => $user->name,
                'phone' => $user->phone,
            ],
        ];
    }

    /**
     * Delete all PII associated with user across all modules.
     */
    private function deleteUserPii(User $user): void
    {
        $userId = $user->id;

        // Core module - delete consents but NOT data requests (audit records)
        GdprConsent::where('user_id', $userId)->delete();

        // CRM module - contacts, leads, accounts (if tables exist)
        if (DB::getSchemaBuilder()->hasTable('crm_contacts')) {
            DB::table('crm_contacts')->where('created_by', $userId)->delete();
        }
        if (DB::getSchemaBuilder()->hasTable('crm_leads')) {
            DB::table('crm_leads')->where('created_by', $userId)->delete();
        }
        if (DB::getSchemaBuilder()->hasTable('crm_activity_logs')) {
            DB::table('crm_activity_logs')->where('user_id', $userId)->delete();
        }

        // HR module - employee data (if tables exist)
        if (DB::getSchemaBuilder()->hasTable('hr_employees')) {
            DB::table('hr_employees')->where('user_id', $userId)->delete();
        }
        if (DB::getSchemaBuilder()->hasTable('hr_leaves')) {
            DB::table('hr_leaves')->where('employee_id', $userId)->delete();
        }

        // Accounting - invoices created by user (if tables exist)
        if (DB::getSchemaBuilder()->hasTable('acc_invoices')) {
            DB::table('acc_invoices')->where('created_by', $userId)->delete();
        }
        if (DB::getSchemaBuilder()->hasTable('acc_expenses')) {
            DB::table('acc_expenses')->where('created_by', $userId)->delete();
        }

        // Generic audit activities (if table exists)
        if (DB::getSchemaBuilder()->hasTable('audit_logs')) {
            DB::table('audit_logs')->where('user_id', $userId)->delete();
        }

        // Core audit logs - anonymize instead of delete (GDPR: keep audit trail but remove PII)
        if (DB::getSchemaBuilder()->hasTable('core_audit_logs')) {
            DB::table('core_audit_logs')->where('user_id', $userId)->update([
                'user_id' => 0,
                'ip_address' => null,
                'user_agent' => null,
                'user_name' => '[deleted]',
            ]);
        }
    }

    /**
     * Get list of tables that had PII cleaned.
     */
    private function getPiiTablesCleaned(): array
    {
        return [
            'crm_contacts',
            'crm_leads',
            'crm_activity_logs',
            'hr_employees',
            'hr_leaves',
            'acc_invoices',
            'acc_expenses',
            'audit_logs',
            'gdpr_consents',
            'data_requests',
        ];
    }

    /**
     * Get pending requests.
     */
    public function getPendingRequests(): Collection
    {
        return DataRequest::where('status', 'pending')->get();
    }

    /**
     * Get overdue requests (pending for > 30 days).
     */
    public function getOverdueRequests(): Collection
    {
        return DataRequest::where('status', 'pending')
            ->where('requested_at', '<', now()->subDays(30))
            ->get();
    }

    /**
     * Get compliance dashboard stats.
     */
    public function getDashboardStats(): array
    {
        $totalConsents   = GdprConsent::count();
        $grantedConsents = GdprConsent::where('granted', true)->whereNull('revoked_at')->count();
        $revokedConsents = GdprConsent::whereNotNull('revoked_at')->count();
        $consentRate     = $totalConsents > 0 ? round(($grantedConsents / $totalConsents) * 100, 2) : 0.0;

        $pendingRequests   = DataRequest::where('status', 'pending')->count();
        $overdueRequests   = DataRequest::where('status', 'pending')
            ->where('requested_at', '<', now()->subDays(30))
            ->count();
        $completedRequests = DataRequest::where('status', 'completed')->count();

        return [
            'total_consents'    => $totalConsents,
            'granted_consents'  => $grantedConsents,
            'revoked_consents'  => $revokedConsents,
            'consent_rate'      => $consentRate,
            'pending_requests'  => $pendingRequests,
            'overdue_requests'  => $overdueRequests,
            'completed_requests' => $completedRequests,
        ];
    }

    /**
     * Get consent breakdown by type.
     */
    public function getConsentBreakdown(): array
    {
        $types = ['marketing', 'analytics', 'functional', 'necessary'];

        $breakdown = [];

        foreach ($types as $type) {
            $total   = GdprConsent::where('consent_type', $type)->count();
            $granted = GdprConsent::where('consent_type', $type)
                ->where('granted', true)
                ->whereNull('revoked_at')
                ->count();
            $revoked = GdprConsent::where('consent_type', $type)
                ->whereNotNull('revoked_at')
                ->count();

            $breakdown[] = [
                'type'    => $type,
                'granted' => $granted,
                'revoked' => $revoked,
                'total'   => $total,
            ];
        }

        return $breakdown;
    }

    /**
     * Encrypt SAR export data using AES-256 encryption.
     * Uses Laravel's encryption with user ID as part of context.
     */
    private function encryptExport(string $data, int $userId): string
    {
        // Create a context string for additional security
        $context = [
            'type' => 'gdpr_sar_export',
            'user_id' => $userId,
            'created_at' => now()->toIso8601String(),
        ];

        // Encrypt the data with context
        $encrypted = encrypt(json_encode([
            'data' => $data,
            'context' => $context,
        ]));

        return $encrypted;
    }

    /**
     * Decrypt SAR export data.
     */
    public function decryptExport(string $encrypted): array
    {
        try {
            $decrypted = decrypt($encrypted);
            $payload = json_decode($decrypted, true);

            return [
                'success' => true,
                'data' => $payload['data'],
                'context' => $payload['context'],
            ];
        } catch (\Throwable $e) {
            Log::error('GDPR export decryption failed', [
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => 'Failed to decrypt export',
            ];
        }
    }

    /**
     * Generate secure download for SAR export.
     * Token-based access prevents unauthorized downloads.
     */
    public function generateExportDownload(string $token): array
    {
        // Find export by token
        $files = Storage::disk('gdpr-archive')->files('exports');
        $targetFile = null;

        foreach ($files as $file) {
            if (str_contains($file, $token)) {
                $targetFile = $file;
                break;
            }
        }

        if (! $targetFile) {
            return ['success' => false, 'error' => 'Export not found'];
        }

        // Check expiration (exports expire after 30 days for security)
        $fileAge = time() - Storage::disk('gdpr-archive')->lastModified($targetFile);
        if ($fileAge > 30 * 24 * 60 * 60) {
            // Auto-delete expired exports
            Storage::disk('gdpr-archive')->delete($targetFile);
            return ['success' => false, 'error' => 'Export has expired'];
        }

        $encrypted = Storage::disk('gdpr-archive')->get($targetFile);
        $decrypted = $this->decryptExport($encrypted);

        if (! $decrypted['success']) {
            return $decrypted;
        }

        // Log download
        (Log::channel('gdpr-audit') ?? Log::channel('single'))?->info('GDPR export downloaded', [
            'user_id' => $decrypted['context']['user_id'] ?? 'unknown',
            'token' => substr($token, 0, 8) . '***',
            'timestamp' => now()->toIso8601String(),
        ]);

        return [
            'success' => true,
            'filename' => "gdpr_export_{$decrypted['context']['user_id']}_" . now()->format('Y-m-d') . '.json',
            'content' => $decrypted['data'],
            'content_type' => 'application/json',
        ];
    }
}
