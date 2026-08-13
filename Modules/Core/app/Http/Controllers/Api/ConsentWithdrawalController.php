<?php

namespace Modules\Core\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * @group GDPR & Privacy
 *
 * Manage user consent preferences and withdrawal.
 * Implements GDPR Articles 7 & 21 - Right to withdraw consent.
 */
class ConsentWithdrawalController extends Controller
{
    /**
     * Get current consent preferences
     *
     * Retrieve user's current consent status for all consent types.
     *
     * @response {
     *   "consents": {
     *     "essential": {"granted": true, "granted_at": "2026-05-13T10:00:00Z", "withdrawn_at": null},
     *     "analytics": {"granted": false, "granted_at": null, "withdrawn_at": null},
     *     "marketing": {"granted": false, "granted_at": null, "withdrawn_at": null},
     *     "cookies": {"granted": true, "granted_at": "2026-05-13T10:00:00Z", "withdrawn_at": null}
     *   }
     * }
     */
    public function getPreferences(Request $request): JsonResponse
    {
        $user = $request->user();

        $consentTypes = ['essential', 'analytics', 'marketing', 'cookies', 'third_party'];
        $consents = [];

        foreach ($consentTypes as $type) {
            $latest = DB::table('consent_logs')
                ->where('user_id', $user->id)
                ->where('consent_type', $type)
                ->orderBy('created_at', 'desc')
                ->first();

            $consents[$type] = [
                'granted' => $latest?->granted ?? false,
                'granted_at' => $latest && $latest->granted ? $latest->created_at : null,
                'withdrawn_at' => $latest && ! $latest->granted ? $latest->created_at : null,
                'last_updated' => $latest?->created_at,
            ];
        }

        return response()->json([
            'user_id' => $user->id,
            'consents' => $consents,
            'last_updated' => DB::table('consent_logs')
                ->where('user_id', $user->id)
                ->orderBy('created_at', 'desc')
                ->first()?->created_at,
        ]);
    }

    /**
     * Withdraw consent for specific types
     *
     * GDPR Article 7(3) - Right to withdraw consent at any time.
     * Can withdraw one or more consent types.
     *
     * @bodyParam consent_types string[] required Array of consent types to withdraw (analytics, marketing, cookies)
     *
     * @response 200 {
     *   "message": "Consent withdrawn successfully",
     *   "withdrawn": ["analytics", "marketing"],
     *   "withdrawn_at": "2026-05-13T10:30:00Z"
     * }
     */
    public function withdraw(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'consent_types' => ['required', 'array', 'min:1'],
            'consent_types.*' => ['required', 'string', 'in:analytics,marketing,cookies,third_party'],
        ]);

        $withdrawn = [];

        foreach ($validated['consent_types'] as $type) {
            // Essential/required cookies cannot be withdrawn
            if ($type === 'essential') {
                continue;
            }

            // Record withdrawal
            DB::table('consent_logs')->insert([
                'user_id' => $user->id,
                'consent_type' => $type,
                'granted' => false,
                'ip_address' => $request->ip(),
                'user_agent' => substr($request->userAgent() ?? '', 0, 255),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $withdrawn[] = $type;

            Log::info("User {$user->id} withdrew consent for: {$type}");
        }

        // Update user's consent flags
        $updates = [];
        foreach ($withdrawn as $type) {
            $updates[$type.'_consent'] = false;
        }

        if (! empty($updates)) {
            $user->update($updates);
        }

        return response()->json([
            'message' => 'Consent withdrawn successfully',
            'withdrawn' => $withdrawn,
            'withdrawn_at' => now()->toIso8601String(),
        ]);
    }

    /**
     * Grant consent for specific types
     *
     * GDPR Article 7(1) - Explicit consent for specific purposes.
     *
     * @bodyParam consent_types string[] required Array of consent types to grant
     *
     * @response 200 {
     *   "message": "Consent granted successfully",
     *   "granted": ["analytics", "marketing"],
     *   "granted_at": "2026-05-13T10:30:00Z"
     * }
     */
    public function grant(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'consent_types' => ['required', 'array', 'min:1'],
            'consent_types.*' => ['required', 'string', 'in:analytics,marketing,cookies,third_party,essential'],
        ]);

        $granted = [];

        foreach ($validated['consent_types'] as $type) {
            // Check if already granted
            $latest = DB::table('consent_logs')
                ->where('user_id', $user->id)
                ->where('consent_type', $type)
                ->orderBy('created_at', 'desc')
                ->first();

            // Only record if not already granted or if being re-granted after withdrawal
            if (! $latest || ! $latest->granted) {
                DB::table('consent_logs')->insert([
                    'user_id' => $user->id,
                    'consent_type' => $type,
                    'granted' => true,
                    'ip_address' => $request->ip(),
                    'user_agent' => substr($request->userAgent() ?? '', 0, 255),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $granted[] = $type;
            }
        }

        // Update user's consent flags
        $updates = [];
        foreach ($granted as $type) {
            $updates[$type.'_consent'] = true;
        }

        if (! empty($updates)) {
            $user->update($updates);
        }

        Log::info("User {$user->id} granted consent for: ".implode(', ', $granted));

        return response()->json([
            'message' => 'Consent granted successfully',
            'granted' => $granted,
            'granted_at' => now()->toIso8601String(),
        ]);
    }

    /**
     * Delete all consent logs for user (right to erasure)
     *
     * GDPR Article 17 - Right to be forgotten.
     * Deletes user's consent history. User can rebuild by re-granting.
     *
     * @response 202 {
     *   "message": "Consent history deleted",
     *   "user_id": 123
     * }
     */
    public function deleteHistory(Request $request): JsonResponse
    {
        $user = $request->user();

        $count = DB::table('consent_logs')
            ->where('user_id', $user->id)
            ->delete();

        Log::info("Deleted {$count} consent logs for user {$user->id}");

        return response()->json([
            'message' => 'Consent history deleted',
            'user_id' => $user->id,
            'records_deleted' => $count,
        ], 202);
    }

    /**
     * Export consent history (SAR - Subject Access Request)
     *
     * GDPR Article 15 - Right of access.
     * Export user's complete consent history in machine-readable format.
     *
     * @response {
     *   "user_id": 123,
     *   "exported_at": "2026-05-13T10:30:00Z",
     *   "total_records": 12,
     *   "consents": [
     *     {
     *       "consent_type": "analytics",
     *       "granted": true,
     *       "recorded_at": "2026-05-13T10:00:00Z",
     *       "ip_address": "192.168.1.1"
     *     }
     *   ]
     * }
     */
    public function exportHistory(Request $request): JsonResponse
    {
        $user = $request->user();

        $logs = DB::table('consent_logs')
            ->where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'user_id' => $user->id,
            'exported_at' => now()->toIso8601String(),
            'total_records' => $logs->count(),
            'consents' => $logs->map(fn ($log) => [
                'consent_type' => $log->consent_type,
                'granted' => (bool) $log->granted,
                'recorded_at' => $log->created_at,
                'ip_address' => $log->ip_address,
            ])->toArray(),
        ]);
    }
}
