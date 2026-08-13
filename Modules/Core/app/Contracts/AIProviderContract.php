<?php

declare(strict_types=1);

namespace Modules\Core\Contracts;

interface AIProviderContract
{
    public function chat(array $messages, array $options = []): string;

    public function embed(string $text): array;

    public function analyze(string $context, string $prompt): string;

    public function getProviderName(): string;
}
