<?php

declare(strict_types=1);

namespace Modules\Helpdesk\Services;

use Illuminate\Support\Collection;
use Modules\AuditLog\Models\AuditLog;
use Modules\Helpdesk\Models\Ticket;

/**
 * AiResponseService - AI-powered canned response generation and personalization
 *
 * Provides intelligent response suggestions based on:
 * - Ticket type and category analysis
 * - Sentiment and emotion analysis
 * - Customer history and preferences
 * - Agent style and effectiveness
 * - Company branding and tone
 * - Knowledge base relevance
 */
class AiResponseService
{
    /**
     * Generate response suggestions for a ticket.
     *
     * @return array<int, array<string, mixed>>
     */
    public function generateResponseSuggestions(Ticket $ticket, int $count = 5): array
    {
        try {
            $suggestions = [];

            // Get relevant suggestions from various sources
            $templateSuggestions = $this->getSuggestionsFromTemplates($ticket, $count);
            $kbSuggestions = $this->getSuggestionsFromKnowledgeBase($ticket, $count);
            $contextualSuggestions = $this->generateContextualSuggestions($ticket, $count);

            // Combine and score all suggestions
            $allSuggestions = array_merge($templateSuggestions, $kbSuggestions, $contextualSuggestions);
            $rankedSuggestions = $this->rankSuggestionsByRelevance($ticket, $allSuggestions);

            // Return top suggestions
            $suggestions = array_slice($rankedSuggestions, 0, $count);

            return $suggestions;
        } catch (\Throwable $e) {
            $this->logError('response_generation_failed', $ticket->id, $e);
            return [];
        }
    }

    /**
     * Rank response suggestions by relevance score.
     *
     * @param array<int, array<string, mixed>> $suggestions
     * @return array<int, array<string, mixed>>
     */
    public function rankSuggestionsByRelevance(Ticket $ticket, array $suggestions): array
    {
        $sentimentService = app(SentimentAnalysisService::class);
        $ticketAnalysis = $sentimentService->analyzeTicketSentiment($ticket);

        foreach ($suggestions as &$suggestion) {
            $relevanceScore = 0.0;

            // Category match
            if (isset($suggestion['category']) && $suggestion['category'] === $ticket->category) {
                $relevanceScore += 0.3;
            }

            // Sentiment appropriateness
            if (isset($suggestion['sentiment']) && $suggestion['sentiment'] === $ticketAnalysis['sentiment']) {
                $relevanceScore += 0.2;
            }

            // Prior usage success
            if (isset($suggestion['success_rate'])) {
                $relevanceScore += $suggestion['success_rate'] * 0.25;
            }

            // Personalization fit
            if (isset($suggestion['personalization_fit'])) {
                $relevanceScore += $suggestion['personalization_fit'] * 0.15;
            }

            // Knowledge base relevance
            if (isset($suggestion['kb_relevance'])) {
                $relevanceScore += $suggestion['kb_relevance'] * 0.1;
            }

            $suggestion['relevance_score'] = round($relevanceScore, 2);
        }

        usort($suggestions, fn ($a, $b) => $b['relevance_score'] <=> $a['relevance_score']);

        return $suggestions;
    }

    /**
     * Provide AI variations of static response templates.
     *
     * @return array<int, string>
     */
    public function generateVariationsOfTemplate(string $template, int $variations = 3): array
    {
        $generatedVariations = [];

        // Parse template structure
        $placeholders = $this->extractTemplatePlaceholders($template);

        for ($i = 0; $i < $variations; $i++) {
            $variation = $template;

            // Replace placeholders with variations
            foreach ($placeholders as $placeholder) {
                $variation = str_replace(
                    "{{$placeholder}}",
                    $this->generatePlaceholderVariation($placeholder, $i),
                    $variation
                );
            }

            $generatedVariations[] = $variation;
        }

        return $generatedVariations;
    }

    /**
     * Personalize response to specific customer context.
     *
     * @param array<string, mixed> $context
     */
    public function personalizeResponse(string $response, Ticket $ticket, array $context = []): string
    {
        // Replace customer name
        if ($ticket->customer) {
            $response = str_replace('[CUSTOMER_NAME]', $ticket->customer->name, $response);
            $response = str_replace('[CUSTOMER_FIRST_NAME]', explode(' ', $ticket->customer->name)[0], $response);
        }

        // Replace company/product context
        if ($ticket->company) {
            $response = str_replace('[COMPANY_NAME]', $ticket->company->name, $response);
        }

        // Replace issue-specific details. Bug fix: `category` is nullable (most tickets never
        // set it — it's a real hd_tickets column but was never added to Ticket::$fillable) and
        // str_replace()'s $replace param is `array|string` (not nullable) — passing null threw
        // `TypeError: str_replace(): Argument #2 ($replace) must be of type array|string, null
        // given` for the very common case of a ticket without a category.
        $response = str_replace('[ISSUE_TYPE]', (string) $ticket->category, $response);
        $response = str_replace('[TICKET_ID]', (string) $ticket->id, $response);

        // Add personalized greeting based on sentiment
        $sentimentService = app(SentimentAnalysisService::class);
        $analysis = $sentimentService->analyzeTicketSentiment($ticket);

        $greetings = [
            'negative' => 'We sincerely understand your concern and will resolve this immediately.',
            'positive' => 'Thank you for your positive feedback!',
            'neutral' => 'Thank you for reaching out to us.',
            'mixed' => 'We appreciate you bringing this to our attention.',
        ];

        $greeting = $greetings[$analysis['sentiment']] ?? $greetings['neutral'];
        $response = $greeting . "\n\n" . $response;

        return $response;
    }

    /**
     * A/B test response variations for effectiveness.
     *
     * @return array<string, mixed>
     */
    public function abTestResponses(Ticket $ticket, array $responseVariations): array
    {
        $testId = "ab_test_" . uniqid();

        $test = [
            'test_id' => $testId,
            'ticket_id' => $ticket->id,
            'variations' => [],
            'created_at' => now(),
        ];

        foreach ($responseVariations as $index => $variation) {
            $test['variations'][] = [
                'variant_id' => "variant_" . ($index + 1),
                'content' => $variation,
                'impressions' => 0,
                'conversions' => 0,
                'conversion_rate' => 0.0,
            ];
        }

        // Store test data for tracking
        $this->storeABTest($test);

        return $test;
    }

    /**
     * Track response performance metrics.
     *
     * @return array<string, mixed>
     */
    public function trackResponsePerformance(Ticket $ticket): array
    {
        // Bug fix: Ticket has no `responses()` relation, only `comments()` (see
        // Modules\Helpdesk\Models\Ticket) — this threw `Error: Call to undefined method` for
        // every caller. Same bug class fixed at PredictiveEscalationService::calculateIssueComplexity()
        // and (pre-existing) SatisfactionPredictionService. Note `TicketComment` has no
        // `satisfaction_score`/`led_to_resolution` columns, so the loop below degrades to
        // zeroed-out metrics rather than throwing — real behavior, not part of this fix.
        $responses = $ticket->comments()->get();

        $metrics = [
            'total_responses' => $responses->count(),
            'average_satisfaction' => 0,
            'resolution_rate' => 0,
            'follow_up_questions' => 0,
            'response_effectiveness' => [],
        ];

        foreach ($responses as $response) {
            // Track satisfaction
            if ($response->satisfaction_score) {
                $metrics['response_effectiveness'][] = [
                    'response_id' => $response->id,
                    'satisfaction' => $response->satisfaction_score,
                    'created_at' => $response->created_at,
                ];
            }

            // Check if response led to resolution
            if ($response->led_to_resolution) {
                $metrics['resolution_rate']++;
            }
        }

        if (count($metrics['response_effectiveness']) > 0) {
            $satisfactions = array_column($metrics['response_effectiveness'], 'satisfaction');
            $metrics['average_satisfaction'] = round(array_sum($satisfactions) / count($satisfactions), 2);
        }

        $metrics['resolution_rate'] = $metrics['total_responses'] > 0
            ? round(($metrics['resolution_rate'] / $metrics['total_responses']) * 100, 2)
            : 0;

        return $metrics;
    }

    /**
     * Learn agent preferences for responses.
     *
     * @return array<string, mixed>
     */
    public function learnAgentPreferences(int $agentId): array
    {
        $agent = \Modules\Core\Models\User::find($agentId);
        if (!$agent) {
            return [];
        }

        $responses = \DB::table('helpdesk_ticket_responses')
            ->where('created_by', $agentId)
            ->get();

        $preferences = [
            'agent_id' => $agentId,
            'total_responses' => $responses->count(),
            'most_used_templates' => [],
            'preferred_length' => 'medium',
            'preferred_tone' => 'professional',
            'response_patterns' => [],
        ];

        // Analyze response patterns
        foreach ($responses as $response) {
            $length = strlen($response->content);
            if ($length < 100) {
                $preferences['response_patterns']['short']++;
            } elseif ($length < 300) {
                $preferences['response_patterns']['medium']++;
            } else {
                $preferences['response_patterns']['long']++;
            }
        }

        // Determine preferred length
        if (($preferences['response_patterns']['short'] ?? 0) > ($preferences['response_patterns']['medium'] ?? 0)) {
            $preferences['preferred_length'] = 'short';
        } elseif (($preferences['response_patterns']['long'] ?? 0) > ($preferences['response_patterns']['medium'] ?? 0)) {
            $preferences['preferred_length'] = 'long';
        }

        return $preferences;
    }

    /**
     * Suggest responses in real-time as agent types.
     *
     * @return array<int, array<string, mixed>>
     */
    public function suggestRealtimeResponses(Ticket $ticket, string $currentText, int $count = 3): array
    {
        // Extract context from typed text
        $keywords = $this->extractKeywords($currentText);
        $detectedCategory = $this->detectCategoryFromText($currentText, $ticket->category);

        // Get contextual suggestions
        $suggestions = $this->generateContextualSuggestions($ticket, $count);

        // Filter by detected category
        $suggestions = array_filter($suggestions, fn ($s) => $s['category'] === $detectedCategory);

        // Score by keyword match
        foreach ($suggestions as &$suggestion) {
            $keywordMatches = count(array_intersect($keywords, explode(' ', strtolower($suggestion['content']))));
            $suggestion['keyword_match_score'] = round($keywordMatches / max(count($keywords), 1), 2);
        }

        usort($suggestions, fn ($a, $b) => $b['keyword_match_score'] <=> $a['keyword_match_score']);

        return array_slice($suggestions, 0, $count);
    }

    /**
     * Support multi-language response generation.
     *
     * @return array<int, array<string, mixed>>
     */
    public function generateMultiLanguageResponses(Ticket $ticket, array $languages = ['en', 'fr', 'es']): array
    {
        $responses = [];

        foreach ($languages as $language) {
            $suggestions = $this->generateResponseSuggestions($ticket);

            foreach ($suggestions as &$suggestion) {
                $suggestion['language'] = $language;
                if ($language !== 'en') {
                    $suggestion['content'] = $this->translateResponse($suggestion['content'], $language);
                }
            }

            $responses[$language] = $suggestions;
        }

        return $responses;
    }

    /**
     * Get batch response suggestions for multiple tickets.
     *
     * @param array<int> $ticketIds
     * @return array<int, array<int, array<string, mixed>>>
     */
    public function batchGenerateSuggestions(array $ticketIds, int $count = 3): array
    {
        $results = [];

        foreach ($ticketIds as $ticketId) {
            try {
                $ticket = Ticket::find($ticketId);
                if ($ticket) {
                    $results[$ticketId] = $this->generateResponseSuggestions($ticket, $count);
                }
            } catch (\Throwable $e) {
                $this->logError('batch_suggestion_failed', $ticketId, $e);
                $results[$ticketId] = [];
            }
        }

        return $results;
    }

    /**
     * Get response analytics and insights.
     *
     * @return array<string, mixed>
     */
    public function getResponseAnalytics(): array
    {
        $responses = \DB::table('helpdesk_ticket_responses')->get();

        $analytics = [
            'total_responses' => $responses->count(),
            'average_satisfaction' => 0,
            'average_response_time' => 0,
            'most_effective_responses' => [],
            'response_categories' => [],
        ];

        if ($responses->count() > 0) {
            $satisfactions = $responses->filter(fn ($r) => $r->satisfaction_score)->pluck('satisfaction_score');
            $analytics['average_satisfaction'] = $satisfactions->count() > 0
                ? round($satisfactions->average(), 2)
                : 0;
        }

        return $analytics;
    }

    // ==================== Private Helper Methods ====================

    /**
     * Get suggestions from response templates.
     *
     * @return array<int, array<string, mixed>>
     */
    private function getSuggestionsFromTemplates(Ticket $ticket, int $count): array
    {
        // In production, query from response_templates table
        return [
            [
                'type' => 'template',
                'content' => 'Thank you for contacting us. We are looking into your issue and will get back to you shortly.',
                'category' => $ticket->category,
                'sentiment' => 'neutral',
                'success_rate' => 0.75,
            ],
            [
                'type' => 'template',
                'content' => 'We sincerely apologize for the inconvenience. Our team is working to resolve this immediately.',
                'category' => $ticket->category,
                'sentiment' => 'negative',
                'success_rate' => 0.82,
            ],
        ];
    }

    /**
     * Get suggestions from knowledge base articles.
     *
     * @return array<int, array<string, mixed>>
     */
    private function getSuggestionsFromKnowledgeBase(Ticket $ticket, int $count): array
    {
        // In production, query KbArticle related to ticket category
        return [
            [
                'type' => 'knowledge_base',
                'content' => 'Please refer to our documentation at [URL] for detailed steps.',
                'category' => $ticket->category,
                'kb_relevance' => 0.85,
                'success_rate' => 0.70,
            ],
        ];
    }

    /**
     * Generate contextual suggestions based on ticket analysis.
     *
     * @return array<int, array<string, mixed>>
     */
    private function generateContextualSuggestions(Ticket $ticket, int $count): array
    {
        $sentimentService = app(SentimentAnalysisService::class);
        $analysis = $sentimentService->analyzeTicketSentiment($ticket);

        $suggestions = [];

        // Generate based on sentiment
        if ($analysis['sentiment'] === 'negative') {
            $suggestions[] = [
                'type' => 'contextual',
                'content' => 'We truly understand your frustration. Here\'s what we\'ll do to make this right...',
                'category' => $ticket->category,
                'sentiment' => 'negative',
                'personalization_fit' => 0.9,
            ];
        } elseif ($analysis['sentiment'] === 'positive') {
            $suggestions[] = [
                'type' => 'contextual',
                'content' => 'Thank you so much for the positive feedback! We\'re glad we could help.',
                'category' => $ticket->category,
                'sentiment' => 'positive',
                'personalization_fit' => 0.85,
            ];
        }

        return $suggestions;
    }

    /**
     * Extract template placeholders.
     *
     * @return array<int, string>
     */
    private function extractTemplatePlaceholders(string $template): array
    {
        $matches = [];
        preg_match_all('/\{\{(\w+)\}\}/', $template, $matches);
        return $matches[1] ?? [];
    }

    /**
     * Generate variation for a placeholder.
     */
    private function generatePlaceholderVariation(string $placeholder, int $variationIndex): string
    {
        $variations = match ($placeholder) {
            'greeting' => ['Hello', 'Hi', 'Greetings', 'Good day'],
            'closing' => ['Best regards', 'Sincerely', 'Warm regards', 'Thank you'],
            'action' => ['will assist', 'will help', 'will support', 'will guide'],
            default => ['[' . $placeholder . ']'],
        };

        return $variations[$variationIndex % count($variations)] ?? $variations[0];
    }

    /**
     * Extract keywords from text.
     *
     * @return array<int, string>
     */
    private function extractKeywords(string $text): array
    {
        $words = str_word_count(strtolower($text), 1);
        $stopwords = ['the', 'a', 'an', 'and', 'or', 'but', 'in', 'on', 'at', 'to', 'for'];

        return array_filter($words, fn ($w) => !in_array($w, $stopwords, true) && strlen($w) > 3);
    }

    /**
     * Detect category from typed text.
     */
    private function detectCategoryFromText(string $text, string $defaultCategory): string
    {
        $categoryKeywords = [
            'billing' => ['invoice', 'payment', 'charge', 'fee', 'refund'],
            'technical' => ['bug', 'error', 'crash', 'issue', 'problem'],
            'account' => ['password', 'login', 'account', 'access'],
        ];

        $textLower = strtolower($text);

        foreach ($categoryKeywords as $category => $keywords) {
            foreach ($keywords as $keyword) {
                if (str_contains($textLower, $keyword)) {
                    return $category;
                }
            }
        }

        return $defaultCategory;
    }

    /**
     * Store A/B test data for tracking.
     *
     * @param array<string, mixed> $test
     */
    private function storeABTest(array $test): void
    {
        // In production, store in database
        // cache()->put("ab_test:" . $test['test_id'], $test, now()->addDays(30));
    }

    /**
     * Translate response to another language.
     */
    private function translateResponse(string $response, string $language): string
    {
        // In production, use translation API
        // For now, return original
        return $response;
    }

    /**
     * Log error for response generation.
     */
    private function logError(string $action, int $ticketId, \Throwable $e): void
    {
        AuditLog::create([
            'action' => $action,
            'model_type' => Ticket::class,
            'model_id' => $ticketId,
            'changes' => ['error' => $e->getMessage()],
            'user_id' => null,
        ]);
    }
}
