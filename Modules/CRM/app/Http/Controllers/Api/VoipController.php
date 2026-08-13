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
     * @response 200
     */
    public function webhook(Request $request): JsonResponse
    {
        $callSid = $request->input('CallSid', '');
        $status = $request->input('CallStatus', 'unknown');

        if ($callSid) {
            CallLog::where('phone_number', $request->input('To', ''))
                ->where('status', 'initiated')
                ->latest()
                ->first()
                ?->update(['status' => $this->mapTwilioStatus($status)]);
        }

        return response()->json(['received' => true]);
    }

    /**
     * List call logs (paginated).
     *
     * @response 200 {"data": [], "total": 0}
     */
    public function callLogs(Request $request): JsonResponse
    {
        $logs = CallLog::with(['contact', 'user'])
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
     * @response 200 {"id": 1, "phone_number": "+1234567890"}
     */
    public function showCallLog(CallLog $callLog): JsonResponse
    {
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
