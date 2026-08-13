<?php

declare(strict_types=1);

namespace Modules\Shared\Exceptions;

class SentimentException extends BaseException
{
    protected string $errorCode = 'SENTIMENT_ERROR';

    public static function analysisFailed(string $reason = '', array $context = []): self
    {
        return new self(
            "Sentiment analysis failed" . ($reason ? ": {$reason}" : ''),
            500,
            null,
            array_merge(['reason' => $reason], $context)
        );
    }

    public static function invalidText(string $reason = '', array $context = []): self
    {
        return new self(
            "Invalid text for sentiment analysis" . ($reason ? ": {$reason}" : ''),
            400,
            null,
            array_merge(['reason' => $reason], $context)
        );
    }

    public static function apiUnavailable(string $reason = '', array $context = []): self
    {
        return new self(
            "Sentiment analysis API unavailable" . ($reason ? ": {$reason}" : ''),
            503,
            null,
            array_merge(['reason' => $reason], $context)
        );
    }

    public static function languageNotSupported(string $language, array $context = []): self
    {
        return new self(
            "Language '{$language}' not supported for sentiment analysis",
            400,
            null,
            array_merge(['language' => $language], $context)
        );
    }
}
