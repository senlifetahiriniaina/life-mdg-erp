<?php

declare(strict_types=1);

namespace Modules\Shared\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SentimentAnalysisService extends BaseService
{
    private const SENTIMENT_POSITIVE = 'positive';
    private const SENTIMENT_NEGATIVE = 'negative';
    private const SENTIMENT_NEUTRAL = 'neutral';
    private const SENTIMENT_MIXED = 'mixed';

    private const EMOTIONS = ['anger', 'frustration', 'satisfaction', 'confusion', 'gratitude', 'fear', 'joy', 'sadness', 'surprise'];
    private const CONFIDENCE_THRESHOLD = 0.65;
    private const CACHE_TTL = 3600;

    private ?string $apiEndpoint;
    private bool $useApi;

    public function __construct(int $companyId, ?string $apiEndpoint = null, bool $useApi = false)
    {
        parent::__construct($companyId);
        $this->apiEndpoint = $apiEndpoint ?? config('sentiment.api_endpoint');
        $this->useApi = $useApi;
    }

    /**
     * Analyze sentiment of text with fallback to API or local implementation
     */
    public function analyze(string $text): array
    {
        if (empty($text)) {
            return [
                'sentiment' => self::SENTIMENT_NEUTRAL,
                'score' => 0,
                'confidence' => 0.0,
                'emotions' => [],
                'language' => 'en',
            ];
        }

        $cacheKey = 'sentiment:' . hash('sha256', $text);
        $cached = Cache::get($cacheKey);
        if ($cached) {
            return $cached;
        }

        $result = $this->useApi && $this->apiEndpoint
            ? $this->analyzeViaApi($text)
            : $this->analyzeLocally($text);

        Cache::put($cacheKey, $result, self::CACHE_TTL);
        return $result;
    }

    /**
     * Batch analyze multiple texts
     */
    public function batchAnalyze(array $texts): array
    {
        return array_map(fn ($text) => $this->analyze($text), $texts);
    }

    /**
     * Detect emotions in text
     */
    public function detectEmotions(string $text): array
    {
        if (empty($text)) {
            return [];
        }

        $emotions = [];
        foreach (self::EMOTIONS as $emotion) {
            $score = $this->emotionScore($text, $emotion);
            if ($score > 0) {
                $emotions[$emotion] = round($score, 2);
            }
        }

        arsort($emotions);
        return $emotions;
    }

    /**
     * Calculate sentiment score (0-100 scale)
     */
    public function calculateScore(string $text): int
    {
        if (empty($text)) {
            return 50;
        }

        $words = str_word_count(strtolower($text), 1);
        $positiveScore = 0;
        $negativeScore = 0;

        foreach ($words as $word) {
            $positiveScore += $this->wordSentimentScore($word, true);
            $negativeScore += $this->wordSentimentScore($word, false);
        }

        $total = $positiveScore + $negativeScore;
        if ($total == 0) {
            return 50;
        }

        return (int) (($positiveScore / $total) * 100);
    }

    /**
     * Calculate confidence level (0-1 scale)
     */
    public function calculateConfidence(string $text): float
    {
        if (empty($text)) {
            return 0.0;
        }

        $wordCount = str_word_count($text);
        $sentimentWords = $this->countSentimentWords($text);
        $baseConfidence = min(($sentimentWords / max($wordCount, 1)) * 100, 100);

        return round($baseConfidence / 100, 2);
    }

    /**
     * Detect primary sentiment
     */
    public function detectSentiment(string $text): string
    {
        $score = $this->calculateScore($text);

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
     * Detect language of text
     */
    public function detectLanguage(string $text): string
    {
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
     * Should escalate based on sentiment
     */
    public function shouldEscalate(string $text): bool
    {
        $score = $this->calculateScore($text);
        if ($score < 30) {
            return true;
        }

        $emotions = $this->detectEmotions($text);
        $negativeEmotions = ['anger', 'frustration', 'fear'];
        foreach ($negativeEmotions as $emotion) {
            if (isset($emotions[$emotion]) && $emotions[$emotion] > 0.6) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get sentiment summary for multiple texts
     */
    public function getSummary(array $texts): array
    {
        $analyses = array_map(fn ($text) => $this->analyze($text), $texts);

        $sentiments = [
            self::SENTIMENT_POSITIVE => 0,
            self::SENTIMENT_NEGATIVE => 0,
            self::SENTIMENT_NEUTRAL => 0,
            self::SENTIMENT_MIXED => 0,
        ];

        $scores = [];
        $emotionCounts = array_fill_keys(self::EMOTIONS, 0);

        foreach ($analyses as $analysis) {
            $sentiments[$analysis['sentiment']]++;
            $scores[] = $analysis['score'];

            foreach ($analysis['emotions'] as $emotion => $score) {
                if ($score > 0) {
                    $emotionCounts[$emotion]++;
                }
            }
        }

        return [
            'sentiment_distribution' => $sentiments,
            'average_score' => count($scores) > 0 ? (int) (array_sum($scores) / count($scores)) : 0,
            'min_score' => count($scores) > 0 ? min($scores) : 0,
            'max_score' => count($scores) > 0 ? max($scores) : 0,
            'emotion_frequency' => $emotionCounts,
            'total_texts' => count($texts),
        ];
    }

    // ==================== Private Helper Methods ====================

    /**
     * Analyze via API
     */
    private function analyzeViaApi(string $text): array
    {
        try {
            $response = Http::timeout(10)->post($this->apiEndpoint, ['text' => $text]);

            if ($response->successful()) {
                $data = $response->json() ?? [];
                return [
                    'sentiment' => $data['sentiment'] ?? self::SENTIMENT_NEUTRAL,
                    'score' => (int) ($data['score'] ?? 50),
                    'confidence' => (float) ($data['confidence'] ?? 0.5),
                    'emotions' => $data['emotions'] ?? [],
                    'language' => $data['language'] ?? 'en',
                ];
            }

            Log::warning('[Sentiment] API analysis failed', ['status' => $response->status()]);
        } catch (\Throwable $e) {
            Log::error('[Sentiment] API call failed', ['error' => $e->getMessage()]);
        }

        return $this->analyzeLocally($text);
    }

    /**
     * Analyze locally
     */
    private function analyzeLocally(string $text): array
    {
        $score = $this->calculateScore($text);
        $sentiment = $this->detectSentiment($text);
        $emotions = $this->detectEmotions($text);
        $language = $this->detectLanguage($text);
        $confidence = $this->calculateConfidence($text);

        return [
            'sentiment' => $sentiment,
            'score' => $score,
            'confidence' => $confidence,
            'emotions' => $emotions,
            'language' => $language,
        ];
    }

    /**
     * Calculate emotion score for specific emotion
     */
    private function emotionScore(string $text, string $emotion): float
    {
        $emotionKeywords = $this->getEmotionKeywords($emotion);
        $words = str_word_count(strtolower($text), 1);
        $matches = array_intersect($words, $emotionKeywords);

        return count($matches) > 0 ? round(count($matches) / count($words), 2) : 0.0;
    }

    /**
     * Get sentiment score for a word
     */
    private function wordSentimentScore(string $word, bool $positive = true): float
    {
        $sentimentLexicon = $this->getSentimentLexicon($positive);
        return isset($sentimentLexicon[$word]) ? $sentimentLexicon[$word] : 0.0;
    }

    /**
     * Count sentiment words in text
     */
    private function countSentimentWords(string $text): int
    {
        $words = str_word_count(strtolower($text), 1);
        $lexicon = array_merge($this->getSentimentLexicon(true), $this->getSentimentLexicon(false));
        $matches = array_intersect($words, array_keys($lexicon));

        return count($matches);
    }

    /**
     * Get emotion keywords
     */
    private function getEmotionKeywords(string $emotion): array
    {
        return match ($emotion) {
            'anger' => ['angry', 'furious', 'outraged', 'infuriated', 'mad'],
            'frustration' => ['frustrated', 'annoyed', 'irritated', 'exasperated', 'agitated'],
            'satisfaction' => ['satisfied', 'pleased', 'happy', 'content', 'delighted'],
            'confusion' => ['confused', 'unclear', 'puzzled', 'bewildered', 'lost'],
            'gratitude' => ['thank', 'grateful', 'appreciate', 'thanks', 'appreciation'],
            'fear' => ['afraid', 'scared', 'worried', 'anxious', 'concerned'],
            'joy' => ['happy', 'joy', 'excited', 'amazing', 'wonderful'],
            'sadness' => ['sad', 'disappointed', 'unhappy', 'upset', 'miserable'],
            'surprise' => ['surprised', 'shocked', 'amazed', 'astonished', 'unexpected'],
            default => [],
        };
    }

    /**
     * Get sentiment lexicon
     */
    private function getSentimentLexicon(bool $positive = true): array
    {
        if ($positive) {
            return [
                'good' => 1.0, 'great' => 1.0, 'excellent' => 1.0, 'amazing' => 1.0,
                'wonderful' => 1.0, 'fantastic' => 1.0, 'brilliant' => 1.0, 'love' => 1.0,
                'best' => 0.9, 'happy' => 0.9, 'pleased' => 0.9, 'satisfied' => 0.9,
                'helpful' => 0.8, 'useful' => 0.8, 'nice' => 0.8, 'kind' => 0.8,
                'perfect' => 0.95, 'awesome' => 0.9, 'incredible' => 0.9, 'beautiful' => 0.85,
            ];
        }

        return [
            'bad' => 1.0, 'terrible' => 1.0, 'awful' => 1.0, 'horrible' => 1.0,
            'poor' => 1.0, 'worst' => 1.0, 'hate' => 1.0, 'useless' => 1.0,
            'angry' => 0.9, 'frustrated' => 0.9, 'disappointed' => 0.9, 'unhappy' => 0.9,
            'annoyed' => 0.8, 'irritated' => 0.8, 'problem' => 0.7, 'issue' => 0.6,
            'terrible' => 0.95, 'disgusting' => 0.9, 'pathetic' => 0.85, 'ugly' => 0.8,
        ];
    }
}
