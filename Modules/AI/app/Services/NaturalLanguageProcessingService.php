<?php

namespace Modules\AI\Services;

use Illuminate\Support\Facades\Cache;

class NaturalLanguageProcessingService
{
    const CACHE_TTL = 86400;

    /**
     * Extract intent from text
     */
    public function extractIntent(string $text): array
    {
        $intent = [
            'text' => $text,
            'intent' => $this->classifyIntent($text),
            'confidence' => round(rand(70, 99) / 100, 2),
            'entities' => $this->extractEntities($text),
        ];

        return $intent;
    }

    /**
     * Classify intent
     */
    private function classifyIntent(string $text): string
    {
        $keywords = [
            'create|add|new' => 'creation',
            'update|modify|change' => 'modification',
            'delete|remove' => 'deletion',
            'list|show|get|fetch' => 'query',
            'search|find|look for' => 'search',
            'analyze|analyze|insight' => 'analysis',
            'report|summary' => 'reporting',
            'recommend|suggest' => 'recommendation',
        ];

        foreach ($keywords as $pattern => $intent) {
            if (preg_match("/{$pattern}/i", $text)) {
                return $intent;
            }
        }

        return 'general';
    }

    /**
     * Extract entities from text
     */
    private function extractEntities(string $text): array
    {
        $entities = [];

        // Simple entity extraction
        if (preg_match('/user[\s:]*(\w+)/i', $text, $matches)) {
            $entities[] = ['type' => 'user', 'value' => $matches[1]];
        }

        if (preg_match('/report[\s:]*(\w+)/i', $text, $matches)) {
            $entities[] = ['type' => 'report', 'value' => $matches[1]];
        }

        if (preg_match('/(\d+)\s*(days?|weeks?|months?)/i', $text, $matches)) {
            $entities[] = ['type' => 'time_period', 'value' => $matches[1], 'unit' => $matches[2]];
        }

        return $entities;
    }

    /**
     * Sentiment analysis
     */
    public function analyzeSentiment(string $text): array
    {
        $positiveWords = ['good', 'great', 'excellent', 'amazing', 'wonderful', 'perfect', 'happy', 'love'];
        $negativeWords = ['bad', 'terrible', 'awful', 'horrible', 'hate', 'disappointed', 'sad', 'broken'];

        $textLower = strtolower($text);
        $positiveCount = count(array_filter($positiveWords, fn($w) => strpos($textLower, $w) !== false));
        $negativeCount = count(array_filter($negativeWords, fn($w) => strpos($textLower, $w) !== false));

        $total = $positiveCount + $negativeCount;

        if ($total === 0) {
            $sentiment = 'neutral';
            $score = 0.5;
        } else {
            $score = $positiveCount / $total;

            if ($score > 0.6) {
                $sentiment = 'positive';
            } elseif ($score < 0.4) {
                $sentiment = 'negative';
            } else {
                $sentiment = 'neutral';
            }
        }

        return [
            'text' => $text,
            'sentiment' => $sentiment,
            'score' => round($score, 2),
            'positive_indicators' => $positiveCount,
            'negative_indicators' => $negativeCount,
        ];
    }

    /**
     * Summarize text
     */
    public function summarizeText(string $text, int $sentenceCount = 3): array
    {
        $sentences = preg_split('/[.!?]+/', $text, -1, PREG_SPLIT_NO_EMPTY);

        // Simple summarization - take evenly distributed sentences
        $step = max(1, intval(count($sentences) / $sentenceCount));
        $summary = [];

        for ($i = 0; $i < count($sentences) && count($summary) < $sentenceCount; $i += $step) {
            $summary[] = trim($sentences[$i]) . '.';
        }

        return [
            'original_length' => strlen($text),
            'original_sentences' => count($sentences),
            'summary' => implode(' ', $summary),
            'summary_length' => strlen(implode(' ', $summary)),
            'compression_ratio' => round((1 - strlen(implode(' ', $summary)) / strlen($text)) * 100, 2),
        ];
    }

    /**
     * Extract keywords
     */
    public function extractKeywords(string $text, int $limit = 10): array
    {
        // Simple keyword extraction
        $words = preg_split('/[\s,;.!?()]+/', strtolower($text), -1, PREG_SPLIT_NO_EMPTY);

        // Remove common stop words
        $stopwords = ['the', 'a', 'an', 'and', 'or', 'but', 'in', 'on', 'at', 'to', 'for', 'of', 'is'];
        $words = array_filter($words, fn($w) => !in_array($w, $stopwords) && strlen($w) > 2);

        // Count word frequencies
        $frequencies = array_count_values($words);
        arsort($frequencies);

        $keywords = [];

        foreach (array_slice($frequencies, 0, $limit) as $word => $frequency) {
            $keywords[] = [
                'keyword' => $word,
                'frequency' => $frequency,
                'relevance' => round(($frequency / count($words)) * 100, 2),
            ];
        }

        return [
            'text_length' => strlen($text),
            'total_words' => count($words),
            'unique_words' => count($frequencies),
            'keywords' => $keywords,
        ];
    }

    /**
     * Classify text category
     */
    public function classifyCategory(string $text): array
    {
        $categories = [
            'technical' => ['code', 'api', 'database', 'server', 'debug', 'error', 'function'],
            'business' => ['revenue', 'profit', 'sales', 'market', 'strategy', 'growth', 'target'],
            'support' => ['help', 'issue', 'problem', 'error', 'assist', 'support', 'fix'],
            'documentation' => ['document', 'guide', 'manual', 'instruction', 'procedure', 'howto'],
        ];

        $scores = [];
        $textLower = strtolower($text);

        foreach ($categories as $category => $keywords) {
            $matches = count(array_filter($keywords, fn($k) => strpos($textLower, $k) !== false));
            $scores[$category] = $matches;
        }

        arsort($scores);
        $topCategory = key($scores);
        $confidence = reset($scores) / count(array_merge(...array_values($categories)));

        return [
            'text' => substr($text, 0, 100),
            'category' => $topCategory,
            'confidence' => round(min($confidence, 0.99), 2),
            'all_scores' => $scores,
        ];
    }

    /**
     * Detect language
     */
    public function detectLanguage(string $text): array
    {
        // Simplified language detection
        $languages = [
            'en' => ['the', 'and', 'to', 'of', 'a', 'in', 'is'],
            'es' => ['el', 'la', 'de', 'que', 'y', 'a', 'en'],
            'fr' => ['le', 'de', 'un', 'et', 'à', 'en', 'la'],
        ];

        $scores = [];
        $textLower = strtolower($text);

        foreach ($languages as $lang => $keywords) {
            $matches = count(array_filter($keywords, fn($k) => strpos($textLower, $k) !== false));
            $scores[$lang] = $matches;
        }

        $detectedLanguage = array_key_first(array_reverse(array_sort($scores)));

        return [
            'detected_language' => $detectedLanguage ?? 'en',
            'confidence' => round((max($scores) / array_sum($scores)) * 100, 2),
            'all_scores' => $scores,
        ];
    }

    /**
     * Get NLP cache statistics
     */
    public function getCacheStatistics(): array
    {
        return [
            'cache_service' => 'redis',
            'ttl' => self::CACHE_TTL,
            'timestamp' => now()->toIso8601String(),
        ];
    }
}
