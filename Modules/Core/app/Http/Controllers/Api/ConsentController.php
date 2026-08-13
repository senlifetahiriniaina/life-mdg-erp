<?php

namespace Modules\Core\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ConsentLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @group GDPR & Privacy
 *
 * Manage user consent for cookies, marketing, and analytics.
 */
class ConsentController extends Controller
{
    /**
     * Record user consent
     *
     * @bodyParam consent_type string required One of: cookies, marketing, analytics, third_party
     * @bodyParam granted boolean required Whether consent is granted
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'consent_type' => ['required', 'in:cookies,marketing,analytics,third_party'],
            'granted' => ['required', 'boolean'],
        ]);

        $user = $request->user();
        $consentLog = ConsentLog::recordConsent(
            userId: $user?->id,
            consentType: $validated['consent_type'],
            granted: $validated['granted']
        );

        return response()->json([
            'message' => 'Consent recorded successfully',
            'consent' => $consentLog,
        ], 201);
    }

    /**
     * Get user's consent status
     *
     * @queryParam consent_type string Filter by consent type (optional)
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = ConsentLog::query();

        if ($user) {
            $query->where('user_id', $user->id);
        }

        if ($request->has('consent_type')) {
            $query->where('consent_type', $request->input('consent_type'));
        }

        $consents = $query
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->latest()
            ->get();

        return response()->json(['consents' => $consents]);
    }

    /**
     * Withdraw consent
     *
     * @bodyParam consent_type string required Consent type to withdraw (cookies, marketing, analytics, third_party)
     */
    public function withdraw(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user, 401, 'Unauthenticated');

        $validated = $request->validate([
            'consent_type' => ['required', 'in:cookies,marketing,analytics,third_party'],
        ]);

        ConsentLog::withdrawConsent(
            consentType: $validated['consent_type'],
            userId: $user->id
        );

        return response()->json([
            'message' => 'Consent withdrawn successfully',
        ]);
    }

    /**
     * Check if user has granted consent
     *
     * @queryParam consent_type string required Consent type to check (cookies, marketing, analytics, third_party)
     */
    public function check(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'consent_type' => ['required', 'in:cookies,marketing,analytics,third_party'],
        ]);

        $user = $request->user();
        $hasConsent = ConsentLog::hasConsent(
            consentType: $validated['consent_type'],
            userId: $user?->id
        );

        return response()->json([
            'consent_type' => $validated['consent_type'],
            'granted' => $hasConsent,
        ]);
    }

    /**
     * Record bulk consent (for cookie banner)
     *
     * @bodyParam consents array required Array of {consent_type, granted}
     */
    public function recordBulk(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'consents' => ['required', 'array'],
            'consents.*.consent_type' => ['required', 'in:cookies,marketing,analytics,third_party'],
            'consents.*.granted' => ['required', 'boolean'],
        ]);

        $user = $request->user();
        $recorded = [];

        foreach ($validated['consents'] as $consent) {
            $record = ConsentLog::recordConsent(
                userId: $user?->id,
                consentType: $consent['consent_type'],
                granted: $consent['granted']
            );
            $recorded[] = $record;
        }

        return response()->json([
            'message' => 'Consents recorded successfully',
            'consents' => $recorded,
        ], 201);
    }
}
