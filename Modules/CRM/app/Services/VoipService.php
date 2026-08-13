<?php

declare(strict_types=1);

namespace Modules\CRM\Services;

use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Modules\AI\Services\AiContextualAssistantService;
use Modules\CRM\Models\CallLog;
use Modules\CRM\Models\CallRecording;
use Modules\CRM\Models\Contact;

class VoipService
{
    private string $accountSid;

    private string $authToken;

    private string $fromNumber;

    public function __construct()
    {
        $this->accountSid = (string) config('services.twilio.account_sid', '');
        $this->authToken = (string) config('services.twilio.auth_token', '');
        $this->fromNumber = (string) config('services.twilio.from_number', '');
    }

    /**
     * Initiate an outbound call via Twilio.
     *
     * @return array{call_sid: string, status: string}
     */
    public function initiateCall(User $agent, string $to, ?Contact $contact = null): array
    {
        $response = Http::withBasicAuth($this->accountSid, $this->authToken)
            ->asForm()
            ->post("https://api.twilio.com/2010-04-01/Accounts/{$this->accountSid}/Calls.json", [
                'To' => $to,
                'From' => $this->fromNumber,
                'Url' => route('crm.voip.webhook'),
            ]);

        $data = $response->json();

        $this->recordCallLog([
            'contact_id' => $contact?->id,
            'lead_id' => null,
            'user_id' => $agent->id,
            'direction' => 'outbound',
            'status' => 'initiated',
            'phone_number' => $to,
            'called_at' => now(),
        ]);

        return [
            'call_sid' => $data['sid'] ?? '',
            'status' => $data['status'] ?? 'initiated',
        ];
    }

    public function getCallStatus(string $callSid): string
    {
        $response = Http::withBasicAuth($this->accountSid, $this->authToken)
            ->get("https://api.twilio.com/2010-04-01/Accounts/{$this->accountSid}/Calls/{$callSid}.json");

        return $response->json()['status'] ?? 'unknown';
    }

    /**
     * @param  array<string,mixed>  $data
     */
    public function recordCallLog(array $data): CallLog
    {
        return CallLog::create($data);
    }

    /**
     * Start recording for an existing call log entry.
     *
     * Creates a CallRecording row with status=recording and triggers the
     * Twilio Recordings API if credentials are present.
     */
    public function startRecording(int $callId): CallRecording
    {
        $callLog = CallLog::findOrFail($callId);

        $recording = CallRecording::create([
            'call_id'    => $callId,
            'status'     => CallRecording::STATUS_RECORDING,
            'company_id' => $callLog->company_id ?? 0,
        ]);

        if ($this->accountSid && $this->authToken && $callLog->call_sid ?? null) {
            Http::withBasicAuth($this->accountSid, $this->authToken)
                ->asForm()
                ->post("https://api.twilio.com/2010-04-01/Accounts/{$this->accountSid}/Calls/{$callLog->call_sid}/Recordings.json");
        }

        return $recording;
    }

    /**
     * Stop an active recording and mark it as processing.
     */
    public function stopRecording(int $callId): CallRecording
    {
        $recording = CallRecording::where('call_id', $callId)
            ->where('status', CallRecording::STATUS_RECORDING)
            ->latest()
            ->firstOrFail();

        $callLog = CallLog::findOrFail($callId);

        if ($this->accountSid && $this->authToken && $callLog->call_sid ?? null) {
            /** @var \Illuminate\Http\Client\Response $response */
            $response = Http::withBasicAuth($this->accountSid, $this->authToken)
                ->get("https://api.twilio.com/2010-04-01/Accounts/{$this->accountSid}/Calls/{$callLog->call_sid}/Recordings.json");

            $recordings = $response->json()['recordings'] ?? [];
            if (! empty($recordings)) {
                $twilioRecording = $recordings[0];
                $recording->recording_url    = "https://api.twilio.com{$twilioRecording['uri']}";
                $recording->duration_seconds = (int) ($twilioRecording['duration'] ?? 0);
            }
        }

        $recording->status = CallRecording::STATUS_PROCESSING;
        $recording->save();

        return $recording;
    }

    /**
     * Generate an AI summary for a call recording.
     *
     * Calls AiContextualAssistantService with the transcript as context.
     * Falls back gracefully when the AI service is unavailable.
     *
     * @return array{summary: string, action_items: string[], sentiment: string, next_step_suggestion: string, enabled: bool}
     */
    public function generateAiSummary(int $callId): array
    {
        $recording = CallRecording::where('call_id', $callId)->latest()->firstOrFail();

        $context = [
            'transcript' => $recording->transcript_text ?? '',
        ];

        try {
            /** @var AiContextualAssistantService $aiService */
            $aiService = app(AiContextualAssistantService::class);
            $guidance  = $aiService->getGuidance(
                module:   'CRM',
                action:   'summarize_call',
                context:  $context,
                locale:   'fr',
                userRole: 'sales_rep',
            );

            // Map guidance shape → call summary shape
            $summary = [
                'enabled'              => $guidance['enabled'] ?? false,
                'summary'              => $guidance['what_to_do'] ?? '',
                'action_items'         => $guidance['next_actions'] ?? [],
                'sentiment'            => $guidance['tips'][0]      ?? 'neutral',
                'next_step_suggestion' => $guidance['how_to_do'][0] ?? '',
            ];
        } catch (\Throwable $e) {
            Log::warning('CRM: AI call summary failed, using fallback', ['error' => $e->getMessage()]);
            $summary = $this->fallbackSummary();
        }

        $recording->ai_summary = $summary;
        $recording->save();

        return $summary;
    }

    /**
     * Update outcome and notes on a call log.
     */
    public function saveCallLog(int $callId, string $outcome, string $notes): CallLog
    {
        $callLog = CallLog::findOrFail($callId);
        $callLog->update([
            'status' => $outcome,
            'notes'  => $notes,
        ]);

        return $callLog->fresh();
    }

    /**
     * Static fallback when AI service is unavailable.
     *
     * @return array{summary: string, action_items: string[], sentiment: string, next_step_suggestion: string, enabled: bool}
     */
    private function fallbackSummary(): array
    {
        return [
            'enabled'              => false,
            'summary'              => 'Résumé non disponible — service IA indisponible.',
            'action_items'         => [],
            'sentiment'            => 'neutral',
            'next_step_suggestion' => 'Veuillez saisir manuellement les notes de l\'appel.',
        ];
    }
}
