<?php

declare(strict_types=1);

namespace Modules\Core\Contracts;

interface AIProviderContract
{
    public function chat(array $messages, array $options = []): string;

    public function embed(string $text): array;

    public function analyze(string $context, string $prompt): string;

    public function getProviderName(): string;

    /**
     * Whether this provider has the configuration it needs to make real
     * calls (API key, base URL, ...). Callers use this to decide whether to
     * attempt a live call or fall back to static content — never to guard
     * against exceptions, which chat()/embed()/analyze() can still throw.
     */
    public function isConfigured(): bool;
}
