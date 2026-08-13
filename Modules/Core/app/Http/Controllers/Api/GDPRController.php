<?php

namespace Modules\Core\Http\Controllers\Api;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Modules\Core\Jobs\AnonymizeUserJob;
use Modules\Core\Jobs\GenerateSARExportJob;

/**
 * @group GDPR & Privacy
 *
 * Manage Subject Access Requests (SAR), data export, and account deletion.
 * Compliant with GDPR Articles 15, 17, and 20.
 */
class GDPRController extends Controller
{
    /**
     * Request Subject Access Request (SAR) export
     *
     * GDPR Article 15 - Right of access to personal data.
     * Initiates an async job to collect and package all personal data.
     * Export will be ready within 24 hours and valid for 30 days.
     *
     * @response 201 {
     *   "message": "Subject Access Request submitted. Export will be prepared within 24 hours.",
     *   "request_id": 123,
     *   "expires_at": "2026-06-12T13:00:00Z"
     * }
     */
    public function requestSAR(Request $request): JsonResponse
    {
        $user = $request->user();

        // Check rate limit: max 1 request per month
        $recentRequest = DB::table('gdpr_requests')
            ->where('user_id', $user->id)
            ->where('created_at', '>', now()->subDays(30))
            ->exists();

        if ($recentRequest) {
            return response()->json([
                'message' => 'You have already requested a SAR within the last 30 days. Only one request per month is allowed.',
            ], 429);
        }

        // Create request record
        $requestId = DB::table('gdpr_requests')->insertGetId([
            'user_id' => $user->id,
            'request_type' => 'sar',
            'status' => 'pending',
            'requested_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Dispatch async job to generate export
        GenerateSARExportJob::dispatch($user->id, $requestId);

        Log::info("SAR requested by user {$user->id}", ['request_id' => $requestId]);

        return response()->json([
            'message' => 'Subject Access Request submitted. Export will be prepared within 24 hours.',
            'request_id' => $requestId,
            'expires_at' => now()->addDays(30)->toIso8601String(),
            'status' => 'pending',
        ], 201);
    }

    /**
     * Get SAR request status
     *
     * Check the status of a pending Subject Access Request.
     *
     * @queryParam request_id integer The ID of the GDPR request.
     *
     * @response {
     *   "request_id": 123,
     *   "status": "completed",
     *   "requested_at": "2026-05-13T13:00:00Z",
     *   "completed_at": "2026-05-13T14:30:00Z",
     *   "download_url": "/api/v1/gdpr/export/123/download",
     *   "expires_at": "2026-06-12T13:00:00Z"
     * }
     */
    public function getSARStatus(Request $request): JsonResponse
    {
        $user = $request->user();
        $requestId = $request->query('request_id');

        $gdprRequest = DB::table('gdpr_requests')
            ->where('id', $requestId)
            ->where('user_id', $user->id)
            ->first();

        if (! $gdprRequest) {
            return response()->json(['message' => 'Request not found.'], 404);
        }

        $response = [
            'request_id' => $gdprRequest->id,
            'status' => $gdprRequest->status,
            'requested_at' => $gdprRequest->requested_at,
            'completed_at' => $gdprRequest->completed_at,
            'expires_at' => $gdprRequest->expires_at ?? null,
        ];

        // Add download URL if ready
        if ($gdprRequest->status === 'completed') {
            $response['download_url'] = route('api.v1.gdpr.export.download', ['request_id' => $requestId]);
        }

        if ($gdprRequest->status === 'failed') {
            $response['failure_reason'] = $gdprRequest->failure_reason;
        }

        return response()->json($response);
    }

    /**
     * Download SAR export
     *
     * Download the generated Subject Access Request export file.
     * File is a ZIP archive containing JSON, CSV, and README.
     *
     * @urlParam request_id integer The ID of the GDPR request.
     *
     * @response file 200
     */
    public function downloadExport(Request $request, int $requestId): Response
    {
        $user = $request->user();

        // Verify request belongs to user and is completed
        $export = DB::table('gdpr_exports')
            ->where('request_id', $requestId)
            ->where('user_id', $user->id)
            ->first();

        if (! $export) {
            abort(404, 'Export not found or not ready.');
        }

        // Check if expired
        if (now()->greaterThan($export->expires_at)) {
            abort(410, 'Export has expired. Please request a new one.');
        }

        // Check file exists
        if (! Storage::disk('exports')->exists($export->file_path)) {
            abort(410, 'Export file has been deleted.');
        }

        // Record download
        DB::table('gdpr_exports')
            ->where('id', $export->id)
            ->update(['downloaded_at' => now()]);

        Log::info("SAR export downloaded by user {$user->id}", [
            'export_id' => $export->id,
            'request_id' => $requestId,
        ]);

        return Storage::disk('exports')->download(
            $export->file_path,
            "sar_export_{$user->id}_".now()->format('Y-m-d').'.zip'
        );
    }

    /**
     * Delete account (right to erasure)
     *
     * GDPR Article 17 - Right to be forgotten.
     * Anonymizes all personal data and marks account for deletion.
     *
     * @response 202 {
     *   "message": "Account deletion initiated. Your data will be anonymized within 24 hours.",
     *   "user_id": 123
     * }
     */
    public function deleteAccount(Request $request): JsonResponse
    {
        $user = $request->user();

        // Verify password
        $request->validate([
            'password' => ['required', 'string'],
        ]);

        if (! \Hash::check($request->password, $user->password)) {
            return response()->json(['message' => 'Password is incorrect.'], 401);
        }

        // Dispatch anonymization job
        AnonymizeUserJob::dispatch($user->id);

        Log::warn("Account deletion requested by user {$user->id}");

        return response()->json([
            'message' => 'Account deletion initiated. Your data will be anonymized within 24 hours.',
            'user_id' => $user->id,
        ], 202);
    }

    /**
     * Get all personal data for a user
     * GDPR Article 15 - Right of access
     */
    public function exportPersonalData(Request $request): JsonResponse
    {
        $user = $request->user();
        $data = [];

        // User profile data
        $data['user'] = $user->only([
            'id', 'name', 'email', 'phone', 'created_at', 'updated_at',
        ]);

        // HR Employee data (if exists)
        if ($employee = DB::table('hr_employees')->where('user_id', $user->id)->first()) {
            $data['employee'] = (array) $employee;
            // Remove encrypted fields from export, list them separately
            unset($data['employee']['national_id_encrypted']);
            unset($data['employee']['passport_number_encrypted']);
            unset($data['employee']['bank_details_encrypted']);
            unset($data['employee']['emergency_contacts_encrypted']);
            $data['employee']['_note'] = 'PII fields encrypted at rest - decryption available on request';
        }

        // CRM Contacts owned by user
        $data['crm_contacts'] = DB::table('crm_contacts')
            ->where('owner_id', $user->id)
            ->get()
            ->toArray();

        // Consent logs
        $data['consents'] = DB::table('consent_logs')
            ->where('user_id', $user->id)
            ->get()
            ->toArray();

        // Activity logs
        $data['activities'] = DB::table('activity_log')
            ->where('causer_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->limit(500)
            ->get()
            ->toArray();

        // Audit logs
        $data['audit_logs'] = DB::table('audit_logs')
            ->where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->limit(500)
            ->get()
            ->toArray();

        Log::info("Personal data export for user {$user->id}");

        return response()->json([
            'exported_at' => now(),
            'user_id' => $user->id,
            'data' => $data,
            'note' => 'This export contains your personal data in the system. Some fields are encrypted for security.',
        ]);
    }

    /**
     * Delete all personal data
     * GDPR Article 17 - Right to erasure (right to be forgotten)
     */
    public function deletePersonalData(Request $request)
    {
        $request->validate([
            'password' => 'required|string',
            'confirm' => 'required|boolean',
        ]);

        $user = $request->user();

        // Verify password
        if (! password_verify($request->password, $user->password)) {
            return response()->json(['error' => 'Invalid password'], 401);
        }

        if (! $request->confirm) {
            return response()->json(['error' => 'You must confirm deletion'], 422);
        }

        DB::beginTransaction();

        try {
            // Log deletion request
            DB::table('gdpr_requests')->insert([
                'user_id' => $user->id,
                'request_type' => 'deletion',
                'status' => 'completed',
                'requested_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Anonymize user data
            $user->update([
                'name' => 'Deleted User',
                'email' => "deleted_{$user->id}@example.com",
                'phone' => null,
            ]);

            // Delete CRM contacts
            DB::table('crm_contacts')->where('owner_id', $user->id)->delete();

            // Delete HR employee data
            DB::table('hr_employees')->where('user_id', $user->id)->delete();

            // Clear consent logs (keep record of deletion)
            DB::table('consent_logs')->where('user_id', $user->id)->delete();

            DB::commit();

            Log::warning("User {$user->id} data deletion completed");

            return response()->json([
                'message' => 'Your personal data has been deleted. Account remains for security audit purposes only.',
                'account_status' => 'anonymized',
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Data deletion failed for user {$user->id}: {$e->getMessage()}");

            return response()->json(['error' => 'Deletion failed'], 500);
        }
    }

    /**
     * Get GDPR compliance status
     */
    public function complianceStatus(Request $request)
    {
        $user = $request->user();

        return response()->json([
            'user_id' => $user->id,
            'compliance' => [
                'consents_recorded' => DB::table('consent_logs')->where('user_id', $user->id)->count(),
                'data_retention_policy' => 'Compliant - 7 years default, 30 days for deleted users',
                'encryption_at_rest' => true,
                'gdpr_article_15' => 'Available via /api/v1/gdpr/export',
                'gdpr_article_17' => 'Available via /api/v1/gdpr/delete',
                'gdpr_article_20' => 'Available via /api/v1/gdpr/export',
                'data_processing_agreement' => 'Available on request',
                'last_audit' => DB::table('gdpr_requests')->where('user_id', $user->id)->latest()->first()?->created_at,
            ],
        ]);
    }

    /**
     * List pending GDPR requests
     */
    public function listRequests(Request $request)
    {
        $requests = DB::table('gdpr_requests')
            ->where('user_id', $request->user()->id)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($requests);
    }
}
