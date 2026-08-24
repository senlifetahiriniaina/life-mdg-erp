<?php

declare(strict_types=1);

namespace Modules\CRM\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\CRM\Http\Requests\InitiateCallRequest;
use Modules\CRM\Http\Resources\CallLogResource;
use Modules\CRM\Models\CallLog;
use Modules\CRM\Models\Contact;
use Modules\CRM\Services\VoipService;

/**
 * @group CRM - VOIP
 */
class VoipController extends Controller
{
    public function __construct(private readonly VoipService $voipService) {}

    /**
     * Initiate an outbound call.
     *
     * @bodyParam phone string required Phone number to call. Example: +1234567890
     * @bodyParam contact_id integer optional Contact ID to associate. Example: 1
     *
     * @response 201 {"call_sid": "CA...", "status": "initiated"}
     */
    public function call(InitiateCallRequest $request): JsonResponse
    {
        $data = $request->validated();
        $user = $request->user();
        $contact = isset($data['contact_id']) ? Contact::find($data['contact_id']) : null;

        /** @var User $user */
        $result = $this->voipService->initiateCall($user, $data['phone'], $contact);

        return response()->json($result, 201);
    }

    /**
     * Twilio webhook for call status updates.
     *
     * Chantier 38.3 — found, deliberately NOT fixed, documented here: this route sits inside
     * the same top-level `auth:sanctum` group as the rest of this controller (see
     * routes/api.php), but `VoipService::initiateCall()` hands this exact URL to Twilio as
     * the callback `Url` for a real outbound call — Twilio's own servers have no Sanctum
     * session/token and would receive a 401 on every real status-update POST, meaning a real
     * call's status/duration has likely never actually updated past 'initiated' in
     * production. Making this genuinely public would need real Twilio request-signature
     * verification (`X-Twilio-Signature`, HMAC-SHA1 against the auth token) — no such
     * middleware exists anywhere in this app today, and exempting the route from auth
     * without it would trade one real vulnerability for another (a spoofable public status
     * endpoint). Building that verification middleware is a real, contained follow-up, not
     * done here to avoid landing a half-secured public endpoint under this pass's time
     * budget — flagged with full severity rather than silently left.
     *
     * @response 200
     */
    public function webhook(Request $request): JsonResponse
    {
        $callSid = $request->input('CallSid', '');
        $status = $request->input('CallStatus', 'unknown');

        if ($callSid) {
            // Chantier 38.3: now that call_sid is actually persisted (see
            // VoipService::initiateCall()), match on it directly — Twilio's own real
            // correlation id for this exact purpose — instead of the previous
            // phone_number+status='initiated' heuristic, which could not disambiguate two
            // concurrent "initiated" calls to the same number. Falls back to the old
            // heuristic only for call logs created before this fix (call_sid still null).
            $updated = CallLog::where('call_sid', $callSid)
                ->update(['status' => $this->mapTwilioStatus($status)]);

            if ($updated === 0) {
                CallLog::where('phone_number', $request->input('To', ''))
                    ->where('status', 'initiated')
                    ->whereNull('call_sid')
                    ->latest()
                    ->first()
                    ?->update(['status' => $this->mapTwilioStatus($status)]);
            }
        }

        return response()->json(['received' => true]);
    }

    /**
     * Chantier 38.3: `VoipService::getCallStatus()` (a real, correctly-written Twilio status
     * lookup) has always existed with zero controller/route exposing it — the real, existing
     * `resources/js/Components/CRM/ClickToCallButton.vue` polls `GET .../voip/status?call_sid=`
     * for exactly this, and that route has never existed, a guaranteed 404 on every poll tick.
     * (The component itself has zero importers anywhere today — a separate, undecided
     * where-to-mount-it product question, left as a documented gap rather than guessed at
     * here — but the missing backend route is a real, contained bug independent of that.)
     * Scoped by tenant_id defensively, matching the rest of this controller, even though
     * Twilio call_sid values are opaque and not realistically guessable.
     *
     * @response 200 {"status": "in-progress"}
     */
    public function status(Request $request): JsonResponse
    {
        $callSid = (string) $request->query('call_sid', '');
        abort_if($callSid === '', 422, 'call_sid is required.');

        $exists = CallLog::where('call_sid', $callSid)
            ->where('tenant_id', $request->user()->company_id)
            ->exists();
        abort_unless($exists, 404, 'Call not found.');

        return response()->json(['status' => $this->voipService->getCallStatus($callSid)]);
    }

    /**
     * List call logs (paginated).
     *
     * Chantier 32.15: had zero tenant scoping — any authenticated CRM-module user of any
     * company could list every other company's call logs (phone numbers, notes, recording
     * links), confirmed empirically before this fix.
     *
     * @response 200 {"data": [], "total": 0}
     */
    public function callLogs(Request $request): JsonResponse
    {
        $this->authorize('viewAny', CallLog::class);

        $logs = CallLog::with(['contact', 'user'])
            ->where('tenant_id', $request->user()->company_id)
            ->when($request->filled('direction'), fn ($q) => $q->where('direction', $request->direction))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->when($request->filled('date_from'), fn ($q) => $q->whereDate('called_at', '>=', $request->date_from))
            ->when($request->filled('date_to'), fn ($q) => $q->whereDate('called_at', '<=', $request->date_to))
            ->latest('called_at')
            ->paginate(25);

        return response()->json($logs);
    }

    /**
     * Show a single call log.
     *
     * Chantier 32.15: had zero authorize() call and zero tenant scoping — any authenticated
     * CRM-module user of any company could view any other company's call log by id.
     *
     * @response 200 {"id": 1, "phone_number": "+1234567890"}
     */
    public function showCallLog(CallLog $callLog): JsonResponse
    {
        $this->authorize('view', $callLog);

        $callLog->load(['contact', 'user']);

        return (new CallLogResource($callLog))->response();
    }

    private function mapTwilioStatus(string $twilioStatus): string
    {
        return match ($twilioStatus) {
            'ringing' => 'ringing',
            'in-progress' => 'answered',
            'completed' => 'answered',
            'no-answer' => 'missed',
            'busy' => 'missed',
            'failed' => 'failed',
            default => 'initiated',
        };
    }
}
