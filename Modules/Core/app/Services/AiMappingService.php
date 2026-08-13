<?php

declare(strict_types=1);

namespace Modules\Core\Services;

use Modules\Core\Services\AI\AIService;

class AiMappingService
{
    public function __construct(private readonly AIService $aiService)
    {
    }

    /**
     * Suggest column mapping from CSV headers to entity fields using AI.
     *
     * @param  list<string>  $headers
     * @return array{column_mapping: array<string, string>, confidence: float}
     */
    public function suggestMapping(array $headers, string $targetEntity): array
    {
        $fields  = $this->getEntityFields($targetEntity);
        $prompt  = "Map these CSV column headers to {$targetEntity} fields. "
            . 'Headers: ' . implode(', ', $headers) . '. '
            . 'Available fields: ' . implode(', ', $fields) . '. '
            . 'Return a JSON object mapping each header name to the best matching field name, or null if no match. '
            . 'Also include a "confidence" key with a float between 0 and 1 representing your overall confidence.';

        try {
            $response = $this->aiService->ask($prompt, [], 'Core', 'en');

            // Extract JSON from the response
            $jsonMatch = [];
            if (preg_match('/\{[\s\S]*\}/m', $response, $jsonMatch)) {
                /** @var array<string, mixed>|null $decoded */
                $decoded = json_decode($jsonMatch[0], true);

                if (is_array($decoded)) {
                    $confidence = (float) ($decoded['confidence'] ?? 0.7);
                    unset($decoded['confidence']);

                    /** @var array<string, string> $mapping */
                    $mapping = [];
                    foreach ($decoded as $header => $field) {
                        if ($field === null || is_string($field)) {
                            $mapping[$header] = $field ?? '';
                        }
                    }

                    return [
                        'column_mapping' => $mapping,
                        'confidence'     => $confidence,
                    ];
                }
            }
        } catch (\Throwable) {
            // Fall through to heuristic mapping
        }

        // Heuristic fallback: try to match by similarity
        $mapping = $this->heuristicMapping($headers, $fields);

        return [
            'column_mapping' => $mapping,
            'confidence'     => 0.5,
        ];
    }

    /**
     * Get available fields for a given entity.
     *
     * @return list<string>
     */
    public function getEntityFields(string $entity): array
    {
        return match ($entity) {
            'contact'  => ['first_name', 'last_name', 'email', 'phone', 'job_title', 'company', 'status'],
            'lead'     => ['title', 'status', 'source', 'score', 'email', 'phone', 'owner_email'],
            'product'  => ['name', 'sku', 'price', 'stock', 'category', 'description'],
            'employee' => ['first_name', 'last_name', 'email', 'department', 'job_title', 'hire_date', 'salary'],
            'supplier' => ['name', 'email', 'phone', 'country', 'currency', 'payment_terms'],
            'invoice'  => ['number', 'customer_email', 'amount', 'tax', 'currency', 'date', 'due_date'],
            default    => [],
        };
    }

    /**
     * Validate a column mapping against entity required fields.
     *
     * @param  array<string, string>  $mapping
     * @return array{valid: bool, missing_required: list<string>, warnings: list<string>}
     */
    public function validateMapping(array $mapping, string $entity): array
    {
        $required = $this->getRequiredFields($entity);
        $mapped   = array_filter(array_values($mapping));

        $missing  = [];
        foreach ($required as $field) {
            if (! in_array($field, $mapped, true)) {
                $missing[] = $field;
            }
        }

        $warnings = [];
        $allFields = $this->getEntityFields($entity);
        foreach ($mapping as $header => $field) {
            if ($field !== '' && ! in_array($field, $allFields, true)) {
                $warnings[] = "Field '{$field}' mapped from '{$header}' is not a known field for {$entity}.";
            }
        }

        return [
            'valid'            => empty($missing),
            'missing_required' => $missing,
            'warnings'         => $warnings,
        ];
    }

    /**
     * @return list<string>
     */
    private function getRequiredFields(string $entity): array
    {
        return match ($entity) {
            'contact'  => ['first_name', 'last_name'],
            'lead'     => ['title'],
            'product'  => ['name', 'sku'],
            'employee' => ['first_name', 'last_name', 'email'],
            'supplier' => ['name'],
            'invoice'  => ['number', 'customer_email', 'amount'],
            default    => [],
        };
    }

    /**
     * Heuristic mapping: normalise and compare strings.
     *
     * @param  list<string>  $headers
     * @param  list<string>  $fields
     * @return array<string, string>
     */
    private function heuristicMapping(array $headers, array $fields): array
    {
        $mapping = [];

        foreach ($headers as $header) {
            $normalised = strtolower(str_replace([' ', '-', '.'], '_', $header));
            $bestMatch  = '';

            foreach ($fields as $field) {
                if ($normalised === $field || str_contains($normalised, $field) || str_contains($field, $normalised)) {
                    $bestMatch = $field;
                    break;
                }
            }

            $mapping[$header] = $bestMatch;
        }

        return $mapping;
    }
}
