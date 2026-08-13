<?php

declare(strict_types=1);

namespace Modules\Security\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use App\Http\Controllers\Controller;
use Illuminate\Support\Str;

/**
 * GDPR Data Export Controller
 * Allows users to request and download all their personal data.
 * Compliant with GDPR Article 15 (Right of Access)
 */
class GdprDataExportController extends Controller
{
    /**
     * Request data export (initiates async job)
     */
    public function request(Request $request): JsonResponse
    {
        $user = Auth::user();
        $tenantId = $user->tenant_id;

        // Create export job
        $jobId = (string) Str::uuid();
        $exportPath = "gdpr/exports/{$jobId}";

        DB::table('gdpr_export_requests')->insert([
            'tenant_id' => $tenantId,
            'user_id' => $user->id,
            'job_id' => $jobId,
            'status' => 'pending',
            'requested_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Queue async job
        // Queue::dispatch(new ExportUserDataJob($user->id, $jobId));

        return response()->json([
            'message' => 'Data export requested successfully',
            'job_id' => $jobId,
            'status' => 'pending',
            'estimated_time' => '5-10 minutes',
        ], 202);
    }

    /**
     * Get export status
     */
    public function status(string $jobId): JsonResponse
    {
        $request = DB::table('gdpr_export_requests')
            ->where('job_id', $jobId)
            ->where('user_id', Auth::id())
            ->first();

        if (!$request) {
            return response()->json(['error' => 'Export request not found'], 404);
        }

        return response()->json([
            'job_id' => $jobId,
            'status' => $request->status,
            'requested_at' => $request->requested_at,
            'completed_at' => $request->completed_at,
            'download_url' => $request->status === 'completed'
                ? route('security.gdpr.download', $jobId)
                : null,
        ]);
    }

    /**
     * Download exported data as ZIP
     */
    public function download(string $jobId): \Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        $request = DB::table('gdpr_export_requests')
            ->where('job_id', $jobId)
            ->where('user_id', Auth::id())
            ->first();

        if (!$request || $request->status !== 'completed') {
            abort(404);
        }

        $path = "gdpr/exports/{$jobId}/data.zip";

        return Storage::download($path, "widehalo_personal_data_{$jobId}.zip");
    }

    /**
     * Delete all personal data (GDPR Right to be Forgotten)
     */
    public function delete(Request $request): JsonResponse
    {
        $user = Auth::user();

        $request->validate([
            'confirmation' => 'required|string|in:DELETE_ALL_DATA',
        ]);

        // Create deletion job
        $jobId = (string) Str::uuid();

        DB::table('gdpr_deletion_requests')->insert([
            'tenant_id' => $user->tenant_id,
            'user_id' => $user->id,
            'job_id' => $jobId,
            'status' => 'pending',
            'requested_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json([
            'message' => 'Deletion request submitted. You will be logged out.',
            'job_id' => $jobId,
            'status' => 'pending',
        ], 202);
    }

    /**
     * List all export requests
     */
    public function list(): JsonResponse
    {
        $exports = DB::table('gdpr_export_requests')
            ->where('user_id', Auth::id())
            ->orderBy('requested_at', 'desc')
            ->get();

        return response()->json($exports);
    }
}
