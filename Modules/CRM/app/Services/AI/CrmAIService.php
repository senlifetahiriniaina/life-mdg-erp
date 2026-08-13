<?php

declare(strict_types=1);

namespace Modules\CRM\Services\AI;

use Modules\Core\Services\AI\AIService;

class CrmAIService
{
    public function __construct(private readonly AIService $ai) {}

    public function scoreLeads(array $leadsData): array
    {
        $json = json_encode($leadsData);
        $response = $this->ai->ask(
            'Score these leads 0-100 based on engagement signals, budget fit, company size, and stage. Return valid JSON only: {"scores":[{"id":1,"score":85,"reason":"High engagement, matches ICP"}]}',
            ['leads' => $json],
            'CRM'
        );
        $decoded = json_decode($response, true);

        return $decoded ?? ['scores' => [], 'raw' => $response];
    }

    public function suggestNextAction(array $opportunityData): string
    {
        return $this->ai->ask(
            'Based on this sales opportunity data, suggest the single most impactful next action to advance the deal. Be specific and actionable in 2-3 sentences.',
            $opportunityData,
            'CRM'
        );
    }

    public function draftFollowUp(array $contactData, string $context): string
    {
        return $this->ai->ask(
            'Draft a professional follow-up email for this contact using the provided contact data and context. Keep it concise (under 150 words), personalized, and with a clear call-to-action.',
            array_merge($contactData, ['context' => $context]),
            'CRM'
        );
    }

    public function detectDuplicates(array $contactData): array
    {
        $prompt = 'Analyze this contact data and identify potential duplicate patterns. Return JSON with: is_duplicate (bool), confidence (float 0-1), reasons (array of strings), suggested_merge_fields (array).';
        $result = $this->ai->ask($prompt, ['contact_data' => json_encode($contactData)], 'CRM', 'en');

        return ['analysis' => $result, 'contact' => $contactData];
    }

    public function draftProspectingEmail(int $contactId, string $context = ''): array
    {
        $prompt = 'Draft a professional B2B prospecting email for this contact. Return JSON with: subject (string), body (string), tone (string), call_to_action (string).';
        $result = $this->ai->ask($prompt, ['context' => $context], 'CRM', 'en');

        return ['email_draft' => $result, 'contact_id' => $contactId];
    }

    public function analyzeConversationSentiment(int $contactId, string $conversationText): array
    {
        $prompt = 'Analyze the sentiment of this CRM conversation. Return JSON with: overall_sentiment (positive/neutral/negative), score (float -1 to 1), key_themes (array), recommended_action (string).';
        $result = $this->ai->ask($prompt, ['conversation' => $conversationText], 'CRM', 'en');

        return ['sentiment' => $result, 'contact_id' => $contactId];
    }

    public function transcribeCallToActivity(string $transcription, int $contactId): array
    {
        $prompt = 'Convert this call transcription into a CRM activity summary. Return JSON with: summary (string), duration_minutes (int), next_steps (array), deal_stage_impact (string), key_commitments (array).';
        $result = $this->ai->ask($prompt, ['transcription' => $transcription], 'CRM', 'en');

        return ['activity' => $result, 'contact_id' => $contactId];
    }
}
