<?php

declare(strict_types=1);

namespace Modules\Workflow\Exceptions;

use RuntimeException;

/**
 * Thrown when the DSL parser encounters a syntax or semantic error.
 */
class WorkflowDslParseException extends RuntimeException
{
    private int $lineNumber;

    private string $dslLine;

    public function __construct(string $message, int $lineNumber = 0, string $dslLine = '')
    {
        $this->lineNumber = $lineNumber;
        $this->dslLine    = $dslLine;

        $fullMessage = $lineNumber > 0
            ? "Ligne {$lineNumber}: {$message}" . ($dslLine !== '' ? " (\"$dslLine\")" : '')
            : $message;

        parent::__construct($fullMessage);
    }

    public function getLineNumber(): int
    {
        return $this->lineNumber;
    }

    public function getDslLine(): string
    {
        return $this->dslLine;
    }

    public function toArray(): array
    {
        return [
            'line'    => $this->lineNumber,
            'message' => $this->getMessage(),
            'excerpt' => $this->dslLine,
        ];
    }
}
