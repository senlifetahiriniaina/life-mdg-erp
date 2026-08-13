<?php

declare(strict_types=1);

namespace Modules\Helpdesk\Services\AI;

use Modules\Core\Services\AI\AIService;

class HelpdeskAIService
{
    public function __construct(private readonly AIService $ai) {}

    public function categorizeTicket(string $subject, string $description): array
    {
        $response = $this->ai->ask(
            'Analyze this support ticket and suggest the best category, priority, and team assignment. Return valid JSON only: {"type":"bug|feature|question|billing|other","priority":"low|medium|high|urgent","suggested_team":"...","tags":["..."],"confidence":"high|medium|low"}',
            ['subject' => $subject, 'description' => $description],
            'Helpdesk'
        );
        $decoded = json_decode($response, true);

        return $decoded ?? ['type' => 'question', 'priority' => 'medium', 'raw' => $response];
    }

    public function suggestResponse(string $subject, string $description, array $previousComments = []): string
    {
        return $this->ai->ask(
            'Draft a helpful, empathetic, and professional support response for this ticket. Be concise (under 200 words), address the issue directly, and include next steps.',
            [
                'subject' => $subject,
                'description' => $description,
                'previous_context' => json_encode($previousComments),
            ],
            'Helpdesk'
        );
    }

    public function summarizeTicket(string $subject, string $description, array $comments): string
    {
        return $this->ai->ask(
            'Summarize this support ticket thread in 2-3 sentences: the issue, what was tried, and current status.',
            [
                'subject' => $subject,
                'description' => $description,
                'comments' => json_encode($comments),
            ],
            'Helpdesk'
        );
    }

    public function predictEscalation(int $ticketId, array $ticketData): array
    {
        $prompt = 'Predict if this support ticket is at risk of escalation based on tone, wait time, and history. Return JSON with: escalation_risk (low/medium/high/critical), risk_score (int 0-100), risk_factors (array), recommended_actions (array of {action, urgency}), suggested_assignee_level (string).';
        $result = $this->ai->ask($prompt, ['ticket_data' => json_encode($ticketData)], 'Helpdesk', 'en');

        return ['prediction' => $result, 'ticket_id' => $ticketId];
    }

    public function kbChatbotResponse(string $userQuery, ?int $categoryId = null): array
    {
        $prompt = 'Answer this customer support query using knowledge base articles. Return JSON with: answer (string), confidence (float 0-1), kb_article_ids (array of relevant article IDs), follow_up_questions (array), escalate_to_agent (bool).';
        $context = $categoryId ? ['category_id' => (string) $categoryId, 'query' => $userQuery] : ['query' => $userQuery];
        $result = $this->ai->ask($prompt, $context, 'Helpdesk', 'en');

        return ['response' => $result, 'query' => $userQuery];
    }
}
