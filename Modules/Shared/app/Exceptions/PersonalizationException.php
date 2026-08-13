<?php

declare(strict_types=1);

namespace Modules\Shared\Exceptions;

class PersonalizationException extends BaseException
{
    protected string $errorCode = 'PERSONALIZATION_ERROR';

    public static function userProfileMissing(int $userId, array $context = []): self
    {
        return new self(
            "User profile not found for user {$userId}",
            404,
            null,
            array_merge(['user_id' => $userId], $context)
        );
    }

    public static function segmentationFailed(string $reason = '', array $context = []): self
    {
        return new self(
            "User segmentation failed" . ($reason ? ": {$reason}" : ''),
            500,
            null,
            array_merge(['reason' => $reason], $context)
        );
    }

    public static function recommendationEngineFailed(string $reason = '', array $context = []): self
    {
        return new self(
            "Recommendation engine failed" . ($reason ? ": {$reason}" : ''),
            500,
            null,
            array_merge(['reason' => $reason], $context)
        );
    }

    public static function preferencesNotConfigured(int $userId, array $context = []): self
    {
        return new self(
            "User preferences not configured for user {$userId}",
            400,
            null,
            array_merge(['user_id' => $userId], $context)
        );
    }
}
