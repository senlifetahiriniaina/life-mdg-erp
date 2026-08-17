<?php

declare(strict_types=1);

namespace Modules\Helpdesk\Services;

use Illuminate\Support\Collection;
use Modules\AuditLog\Models\AuditLog;
use Modules\Helpdesk\Models\Ticket;

/**
 * SentimentAnalysisService - Advanced sentiment analysis for customer service tickets
 *
 * Provides comprehensive sentiment analysis including:
 * - Multi-language support with automatic language detection
 * - Emotion detection (anger, frustration, satisfaction, confusion, gratitude, fear)
 * - Sentiment scoring with confidence levels
 * - Historical sentiment tracking and evolution
 * - Ticket routing based on sentiment
 * - Real-time and batch analysis capabilities
 */
class SentimentAnalysisService
{
    private const SENTIMENT_POSITIVE = 'positive';
    private const SENTIMENT_NEGATIVE = 'negative';
    private const SENTIMENT_NEUTRAL = 'neutral';
    private const SENTIMENT_MIXED = 'mixed';

    private const EMOTIONS = ['anger', 'frustration', 'satisfaction', 'confusion', 'gratitude', 'fear'];
    private const CONFIDENCE_THRESHOLD = 0.65;

    /**
     * Analyze sentiment of a ticket message.
     *
     * @return array<string, mixed>
     */
    public function analyzeTicketSentiment(Ticket $ticket): array
    {
        try {
            $text = $ticket->description . ' ' . $ticket->subject;

            return [
                'ticket_id' => $ticket->id,
                'sentiment' => $this->detectSentiment($text),
                'emotions' => $this->detectEmotions($text),
                'score' => $this->calculateSentimentScore($text),
                'confidence' => $this->calculateConfidence($text),
                'language' => $this->detectLanguage($text),
                'analyzed_at' => now(),
            ];
        } catch (\Throwable $e) {
            $this->logError('sentiment_analysis_failed', $ticket->id, $e);
            return [
                'ticket_id' => $ticket->id,
                'sentiment' => self::SENTIMENT_NEUTRAL,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Analyze sentiment with multi-language support.
     *
     * @return array<string, mixed>
     */
    public function analyzeMultiLanguageSentiment(string $text, ?string $language = null): array
    {
        $detectedLanguage = $language ?? $this->detectLanguage($text);
        $translatedText = $this->translateToEnglish($text, $detectedLanguage);

        return [
            'original_language' => $detectedLanguage,
            'sentiment' => $this->detectSentiment($translatedText),
            'emotions' => $this->detectEmotions($translatedText),
            'score' => $this->calculateSentimentScore($translatedText),
            'confidence' => $this->calculateConfidence($translatedText),
        ];
    }

    /**
     * Detect emotion categories in text.
     *
     * @return array<string, float>
     */
    public function detectEmotions(string $text): array
    {
        $emotions = [];

        foreach (self::EMOTIONS as $emotion) {
            $score = $this->emotionScore($text, $emotion);
            if ($score > 0) {
                $emotions[$emotion] = $score;
            }
        }

        arsort($emotions);
        return $emotions;
    }

    /**
     * Calculate comprehensive sentiment score (0-100).
     */
    public function calculateSentimentScore(string $text): int
    {
        $words = str_word_count(strtolower($text), 1);
        $positiveScore = 0;
        $negativeScore = 0;

        foreach ($words as $word) {
            $positiveScore += $this->wordSentimentScore($word, true);
            $negativeScore += $this->wordSentimentScore($word, false);
        }

        $total = $positiveScore + $negativeScore;
        // $positiveScore/$negativeScore are promoted to float as soon as any word scores,
        // so `$total === 0` (strict int comparison) never matches a genuinely-zero float
        // total and falls through to a division by zero — compare loosely instead.
        if ($total == 0) {
            return 50;
        }

        return (int) (($positiveScore / $total) * 100);
    }

    /**
     * Calculate confidence level for sentiment prediction.
     */
    public function calculateConfidence(string $text): float
    {
        $wordCount = str_word_count($text);
        $sentimentWords = $this->countSentimentWords($text);
        $baseConfidence = min(($sentimentWords / max($wordCount, 1)) * 100, 100);

        return round($baseConfidence / 100, 2);
    }

    /**
     * Track sentiment evolution over conversation timeline.
     *
     * @return array<int, array<string, mixed>>
     */
    public function trackSentimentEvolution(Ticket $ticket): array
    {
        $timeline = [];

        $messages = $ticket->messages()
            ->orderBy('created_at', 'asc')
            ->get();

        foreach ($messages as $message) {
            $sentiment = $this->detectSentiment($message->content);
            $score = $this->calculateSentimentScore($message->content);

            $timeline[] = [
                'timestamp' => $message->created_at,
                'sentiment' => $sentiment,
                'score' => $score,
                'message_id' => $message->id,
                'author_type' => $message->author_type,
            ];
        }

        return $timeline;
    }

    /**
     * Route tickets based on sentiment analysis.
     *
     * @return array<string, mixed>
     */
    public function routeTicketBySentiment(Ticket $ticket): array
    {
        $analysis = $this->analyzeTicketSentiment($ticket);
        $sentiment = $analysis['sentiment'];
        $score = $analysis['score'];
        $emotions = $analysis['emotions'];

        $routingDecision = match ($sentiment) {
            self::SENTIMENT_NEGATIVE => $this->routeNegativeSentiment($ticket, $emotions),
            self::SENTIMENT_POSITIVE => $this->routePositiveSentiment($ticket),
            self::SENTIMENT_MIXED => $this->routeMixedSentiment($ticket, $score),
            default => $this->routeNeutralSentiment($ticket),
        };

        $this->logAudit('ticket_routed_by_sentiment', $ticket->id, $routingDecision);

        return $routingDecision;
    }

    /**
     * Track sentiment improvement over conversation timeline.
     *
     * @return array<string, mixed>
     */
    public function trackSentimentImprovement(Ticket $ticket): array
    {
        $evolution = $this->trackSentimentEvolution($ticket);

        if (count($evolution) < 2) {
            return ['improvement_detected' => false, 'change' => 0];
        }

        $firstScore = $evolution[0]['score'];
        $lastScore = $evolution[count($evolution) - 1]['score'];
        $improvement = $lastScore - $firstScore;

        return [
            'improvement_detected' => $improvement > 10,
            'change' => $improvement,
            'initial_sentiment' => $evolution[0]['sentiment'],
            'final_sentiment' => $evolution[count($evolution) - 1]['sentiment'],
            'messages_analyzed' => count($evolution),
            'timeline' => $evolution,
        ];
    }

    /**
     * Perform batch sentiment analysis for multiple tickets.
     *
     * @param array<int> $ticketIds
     * @return array<int, array<string, mixed>>
     */
    public function batchAnalyzeSentiment(array $ticketIds): array
    {
        $results = [];

        foreach ($ticketIds as $ticketId) {
            try {
                $ticket = Ticket::find($ticketId);
                if ($ticket) {
                    $results[$ticketId] = $this->analyzeTicketSentiment($ticket);
                }
            } catch (\Throwable $e) {
                $this->logError('batch_sentiment_analysis_failed', $ticketId, $e);
                $results[$ticketId] = ['error' => $e->getMessage()];
            }
        }

        return $results;
    }

    /**
     * Get real-time sentiment analysis for incoming messages.
     *
     * @return array<string, mixed>
     */
    public function analyzeRealtimeSentiment(string $message): array
    {
        return [
            'sentiment' => $this->detectSentiment($message),
            'emotions' => $this->detectEmotions($message),
            'score' => $this->calculateSentimentScore($message),
            'confidence' => $this->calculateConfidence($message),
            'language' => $this->detectLanguage($message),
            'should_escalate' => $this->shouldEscalateBasedOnSentiment(
                $this->calculateSentimentScore($message),
                $this->detectEmotions($message)
            ),
        ];
    }

    /**
     * Get sentiment statistics for tickets within date range.
     *
     * @return array<string, mixed>
     */
    public function getSentimentStatistics(\DateTimeInterface $from, \DateTimeInterface $to): array
    {
        $tickets = Ticket::whereBetween('created_at', [$from, $to])->get();

        $sentiments = [
            self::SENTIMENT_POSITIVE => 0,
            self::SENTIMENT_NEGATIVE => 0,
            self::SENTIMENT_NEUTRAL => 0,
            self::SENTIMENT_MIXED => 0,
        ];

        $scores = [];
        $emotionCounts = array_fill_keys(self::EMOTIONS, 0);

        foreach ($tickets as $ticket) {
            $analysis = $this->analyzeTicketSentiment($ticket);
            $sentiments[$analysis['sentiment']]++;
            $scores[] = $analysis['score'];

            foreach (self::EMOTIONS as $emotion) {
                if (isset($analysis['emotions'][$emotion])) {
                    $emotionCounts[$emotion]++;
                }
            }
        }

        return [
            'sentiment_distribution' => $sentiments,
            'average_score' => count($scores) > 0 ? (int) array_sum($scores) / count($scores) : 0,
            'min_score' => count($scores) > 0 ? min($scores) : 0,
            'max_score' => count($scores) > 0 ? max($scores) : 0,
            'emotion_frequency' => $emotionCounts,
            'total_tickets' => count($tickets),
        ];
    }

    /**
     * Analyze customer satisfaction indicators from sentiment.
     *
     * @return array<string, mixed>
     */
    public function analyzeSatisfactionIndicators(Ticket $ticket): array
    {
        $analysis = $this->analyzeTicketSentiment($ticket);
        $improvement = $this->trackSentimentImprovement($ticket);

        return [
            'overall_sentiment' => $analysis['sentiment'],
            'sentiment_score' => $analysis['score'],
            'emotions_detected' => $analysis['emotions'],
            'improvement_tracked' => $improvement['improvement_detected'],
            'sentiment_change' => $improvement['change'],
            'satisfaction_level' => $this->inferSatisfactionLevel($analysis, $improvement),
        ];
    }

    // ==================== Private Helper Methods ====================

    /**
     * Detect primary sentiment of text.
     */
    private function detectSentiment(string $text): string
    {
        $score = $this->calculateSentimentScore($text);

        if ($score >= 70) {
            return self::SENTIMENT_POSITIVE;
        } elseif ($score <= 30) {
            return self::SENTIMENT_NEGATIVE;
        } elseif ($score >= 40 && $score <= 60) {
            return self::SENTIMENT_NEUTRAL;
        }

        return self::SENTIMENT_MIXED;
    }

    /**
     * Calculate emotion score for a specific emotion.
     */
    private function emotionScore(string $text, string $emotion): float
    {
        $emotionKeywords = $this->getEmotionKeywords($emotion);
        $words = str_word_count(strtolower($text), 1);
        $matches = array_intersect($words, $emotionKeywords);

        return count($matches) > 0 ? round(count($matches) / count($words), 2) : 0.0;
    }

    /**
     * Get sentiment score for a word.
     */
    private function wordSentimentScore(string $word, bool $positive = true): float
    {
        $sentimentLexicon = $this->getSentimentLexicon($positive);
        return isset($sentimentLexicon[$word]) ? $sentimentLexicon[$word] : 0.0;
    }

    /**
     * Count sentiment words in text.
     */
    private function countSentimentWords(string $text): int
    {
        $words = str_word_count(strtolower($text), 1);
        $lexicon = array_merge($this->getSentimentLexicon(true), $this->getSentimentLexicon(false));
        $matches = array_intersect($words, array_keys($lexicon));

        return count($matches);
    }

    /**
     * Get emotion keywords for a specific emotion.
     *
     * @return array<int, string>
     */
    private function getEmotionKeywords(string $emotion): array
    {
        return match ($emotion) {
            'anger' => ['angry', 'furious', 'outraged', 'infuriated', 'mad', 'furious'],
            'frustration' => ['frustrated', 'annoyed', 'irritated', 'exasperated', 'agitated'],
            'satisfaction' => ['satisfied', 'pleased', 'happy', 'content', 'delighted'],
            'confusion' => ['confused', 'unclear', 'puzzled', 'bewildered', 'lost'],
            'gratitude' => ['thank', 'grateful', 'appreciate', 'thanks', 'appreciation'],
            'fear' => ['afraid', 'scared', 'worried', 'anxious', 'concerned'],
            default => [],
        };
    }

    /**
     * Get sentiment lexicon (positive or negative words).
     *
     * @return array<string, float>
     */
    private function getSentimentLexicon(bool $positive = true): array
    {
        if ($positive) {
            return [
                'good' => 1.0, 'great' => 1.0, 'excellent' => 1.0, 'amazing' => 1.0,
                'wonderful' => 1.0, 'fantastic' => 1.0, 'brilliant' => 1.0, 'love' => 1.0,
                'best' => 0.9, 'happy' => 0.9, 'pleased' => 0.9, 'satisfied' => 0.9,
                'helpful' => 0.8, 'useful' => 0.8, 'nice' => 0.8, 'kind' => 0.8,
            ];
        }

        return [
            'bad' => 1.0, 'terrible' => 1.0, 'awful' => 1.0, 'horrible' => 1.0,
            'poor' => 1.0, 'worst' => 1.0, 'hate' => 1.0, 'useless' => 1.0,
            'angry' => 0.9, 'frustrated' => 0.9, 'disappointed' => 0.9, 'unhappy' => 0.9,
            'annoyed' => 0.8, 'irritated' => 0.8, 'problem' => 0.7, 'issue' => 0.6,
        ];
    }

    /**
     * Detect language of text.
     */
    private function detectLanguage(string $text): string
    {
        // Simple implementation - in production use a proper language detection library
        $frenchWords = ['bonjour', 'merci', 'oui', 'non', 'le', 'la', 'de'];
        $spanishWords = ['hola', 'gracias', 'si', 'no', 'el', 'la', 'de'];
        $germanWords = ['hallo', 'danke', 'ja', 'nein', 'der', 'die', 'das'];

        $words = str_word_count(strtolower($text), 1);
        $frenchCount = count(array_intersect($words, $frenchWords));
        $spanishCount = count(array_intersect($words, $spanishWords));
        $germanCount = count(array_intersect($words, $germanWords));

        if ($frenchCount > $spanishCount && $frenchCount > $germanCount) {
            return 'fr';
        } elseif ($spanishCount > $germanCount) {
            return 'es';
        } elseif ($germanCount > 0) {
            return 'de';
        }

        return 'en';
    }

    /**
     * Translate text to English from detected language.
     */
    private function translateToEnglish(string $text, string $language): string
    {
        // In production, use a translation API (Google Translate, AWS Translate, etc.)
        // For now, return original text
        return $text;
    }

    /**
     * Route negative sentiment tickets (escalation).
     *
     * @param array<string, float> $emotions
     * @return array<string, mixed>
     */
    private function routeNegativeSentiment(Ticket $ticket, array $emotions): array
    {
        $escalationLevel = 'L2';

        if (isset($emotions['anger']) && $emotions['anger'] > 0.7) {
            $escalationLevel = 'L3';
        }

        return [
            'ticket_id' => $ticket->id,
            'action' => 'escalate',
            'escalation_level' => $escalationLevel,
            'priority' => 'high',
            'reason' => 'Negative sentiment detected',
        ];
    }

    /**
     * Route positive sentiment tickets (reward).
     *
     * @return array<string, mixed>
     */
    private function routePositiveSentiment(Ticket $ticket): array
    {
        return [
            'ticket_id' => $ticket->id,
            'action' => 'reward_agent',
            'priority' => 'normal',
            'reason' => 'Positive sentiment detected',
        ];
    }

    /**
     * Route mixed sentiment tickets (balanced handling).
     *
     * @return array<string, mixed>
     */
    private function routeMixedSentiment(Ticket $ticket, int $score): array
    {
        $escalate = $score < 45;

        return [
            'ticket_id' => $ticket->id,
            'action' => $escalate ? 'escalate' : 'continue',
            'escalation_level' => $escalate ? 'L2' : null,
            'priority' => $score < 40 ? 'high' : 'normal',
        ];
    }

    /**
     * Route neutral sentiment tickets (standard handling).
     *
     * @return array<string, mixed>
     */
    private function routeNeutralSentiment(Ticket $ticket): array
    {
        return [
            'ticket_id' => $ticket->id,
            'action' => 'continue',
            'priority' => 'normal',
            'reason' => 'Neutral sentiment',
        ];
    }

    /**
     * Determine if ticket should be escalated based on sentiment.
     *
     * @param array<string, float> $emotions
     */
    private function shouldEscalateBasedOnSentiment(int $score, array $emotions): bool
    {
        if ($score < 30) {
            return true;
        }

        $negativeEmotions = ['anger', 'frustration', 'fear'];
        foreach ($negativeEmotions as $emotion) {
            if (isset($emotions[$emotion]) && $emotions[$emotion] > 0.6) {
                return true;
            }
        }

        return false;
    }

    /**
     * Infer overall satisfaction level from sentiment analysis.
     *
     * @param array<string, mixed> $analysis
     * @param array<string, mixed> $improvement
     */
    private function inferSatisfactionLevel(array $analysis, array $improvement): string
    {
        $score = $analysis['score'];
        $improved = $improvement['improvement_detected'];

        if ($score >= 70 && $improved) {
            return 'very_satisfied';
        } elseif ($score >= 60) {
            return 'satisfied';
        } elseif ($score >= 40) {
            return 'neutral';
        } elseif ($improved) {
            return 'improving';
        }

        return 'dissatisfied';
    }

    /**
     * Log error for sentiment analysis.
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

    /**
     * Log audit trail for sentiment analysis.
     *
     * @param array<string, mixed> $data
     */
    private function logAudit(string $action, int $ticketId, array $data): void
    {
        AuditLog::create([
            'action' => $action,
            'model_type' => Ticket::class,
            'model_id' => $ticketId,
            'changes' => $data,
            'user_id' => null,
        ]);
    }
}
