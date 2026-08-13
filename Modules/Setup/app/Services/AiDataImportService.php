<?php

declare(strict_types=1);

namespace Modules\Setup\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Modules\Setup\Jobs\ImportDataJob;

/**
 * AiDataImportService
 *
 * AI-assisted data import pipeline for Phase 40.
 *
 * Responsibilities:
 *  1. analyzeFile()     — detect file structure + suggest column mappings via Claude
 *  2. validateMapping() — validate mapping + return data quality report
 *  3. executeImport()   — dispatch ImportDataJob to queue, return job_id
 *  4. getImportStatus() — poll job progress from cache
 *
 * AI Assisted First: uses claude-opus-4-7 for complex column-mapping tasks and
 * claude-sonnet-4-6 for quick validation passes.
 * Prompt caching: system prompt + target schema = ephemeral cached block.
 * Graceful fallback: regex heuristic mapping when ANTHROPIC_API_KEY is absent.
 */
class AiDataImportService
{
    /** Claude model for complex mapping analysis */
    private const MODEL_OPUS = 'claude-opus-4-7';

    /** Claude model for quick validation */
    private const MODEL_SONNET = 'claude-sonnet-4-6';

    private const API_URL = 'https://api.anthropic.com/v1/messages';

    /** Supported target entity schemas with their required / optional fields */
    private const ENTITY_SCHEMAS = [
        'contacts' => [
            'required' => ['full_name'],
            'optional' => ['email', 'phone', 'company', 'country', 'address'],
        ],
        'products' => [
            'required' => ['name'],
            'optional' => ['sku', 'category', 'price', 'cost', 'unit', 'description'],
        ],
        'suppliers' => [
            'required' => ['name'],
            'optional' => ['email', 'phone', 'country', 'currency', 'payment_terms'],
        ],
        'employees' => [
            'required' => ['full_name'],
            'optional' => ['email', 'department', 'position', 'hire_date', 'salary'],
        ],
        'invoices' => [
            'required' => ['number', 'date', 'client_name', 'amount'],
            'optional' => ['currency', 'status'],
        ],
        'stock' => [
            'required' => ['product_sku', 'quantity'],
            'optional' => ['warehouse', 'unit_cost'],
        ],
    ];

    /** Heuristic keyword → target field mapping (fallback) */
    private const HEURISTIC_MAP = [
        'contacts' => [
            'nom'         => 'full_name',
            'name'        => 'full_name',
            'prénom'      => 'full_name',
            'prenom'      => 'full_name',
            'email'       => 'email',
            'mail'        => 'email',
            'courriel'    => 'email',
            'tel'         => 'phone',
            'phone'       => 'phone',
            'mobile'      => 'phone',
            'société'     => 'company',
            'societe'     => 'company',
            'company'     => 'company',
            'entreprise'  => 'company',
            'pays'        => 'country',
            'country'     => 'country',
            'adresse'     => 'address',
            'address'     => 'address',
        ],
        'products' => [
            'nom'         => 'name',
            'name'        => 'name',
            'produit'     => 'name',
            'product'     => 'name',
            'sku'         => 'sku',
            'référence'   => 'sku',
            'reference'   => 'sku',
            'ref'         => 'sku',
            'categorie'   => 'category',
            'category'    => 'category',
            'prix'        => 'price',
            'price'       => 'price',
            'coût'        => 'cost',
            'cout'        => 'cost',
            'cost'        => 'cost',
            'unité'       => 'unit',
            'unite'       => 'unit',
            'unit'        => 'unit',
            'description' => 'description',
        ],
        'suppliers' => [
            'nom'            => 'name',
            'name'           => 'name',
            'fournisseur'    => 'name',
            'supplier'       => 'name',
            'email'          => 'email',
            'mail'           => 'email',
            'tel'            => 'phone',
            'phone'          => 'phone',
            'pays'           => 'country',
            'country'        => 'country',
            'devise'         => 'currency',
            'currency'       => 'currency',
            'délai'          => 'payment_terms',
            'delai'          => 'payment_terms',
            'payment_terms'  => 'payment_terms',
        ],
        'employees' => [
            'nom'            => 'full_name',
            'name'           => 'full_name',
            'employé'        => 'full_name',
            'employee'       => 'full_name',
            'email'          => 'email',
            'département'    => 'department',
            'departement'    => 'department',
            'department'     => 'department',
            'poste'          => 'position',
            'position'       => 'position',
            'titre'          => 'position',
            'date'           => 'hire_date',
            'embauche'       => 'hire_date',
            'hire_date'      => 'hire_date',
            'salaire'        => 'salary',
            'salary'         => 'salary',
        ],
        'invoices' => [
            'numéro'         => 'number',
            'numero'         => 'number',
            'number'         => 'number',
            'facture'        => 'number',
            'date'           => 'date',
            'client'         => 'client_name',
            'client_name'    => 'client_name',
            'montant'        => 'amount',
            'amount'         => 'amount',
            'devise'         => 'currency',
            'currency'       => 'currency',
            'statut'         => 'status',
            'status'         => 'status',
            'état'           => 'status',
        ],
        'stock' => [
            'sku'            => 'product_sku',
            'product_sku'    => 'product_sku',
            'référence'      => 'product_sku',
            'reference'      => 'product_sku',
            'quantité'       => 'quantity',
            'quantite'       => 'quantity',
            'quantity'       => 'quantity',
            'qty'            => 'quantity',
            'entrepôt'       => 'warehouse',
            'entrepot'       => 'warehouse',
            'warehouse'      => 'warehouse',
            'coût'           => 'unit_cost',
            'cout'           => 'unit_cost',
            'unit_cost'      => 'unit_cost',
        ],
    ];

    // -------------------------------------------------------------------------
    // Public API
    // -------------------------------------------------------------------------

    /**
     * Analyse an uploaded file, detect entity type and suggest column mappings.
     *
     * @return array{
     *   detected_entity: string,
     *   columns: list<array{source: string, target: string, confidence: float}>,
     *   sample_rows: list<array<string,mixed>>,
     *   warnings: list<string>,
     *   estimated_rows: int,
     * }
     */
    public function analyzeFile(string $filePath, string $tenantId): array
    {
        $rawData = $this->readFilePreview($filePath, 20);

        if (empty($rawData['headers'])) {
            return $this->emptyAnalysisResult();
        }

        $detectedEntity = $this->detectEntityHeuristic($rawData['headers']);
        $warnings       = [];

        $apiKey = (string) config('services.anthropic.key', '');

        if ($apiKey !== '') {
            try {
                $result = $this->callClaudeForMapping(
                    $rawData['headers'],
                    $rawData['sample_rows'],
                    $apiKey,
                );
                $detectedEntity = $result['detected_entity'] ?? $detectedEntity;
                $columns        = $result['columns']         ?? [];
                $warnings       = $result['warnings']        ?? [];
            } catch (\Throwable $e) {
                Log::warning('AiDataImportService: Claude API error during analyzeFile', [
                    'error' => $e->getMessage(),
                ]);
                $columns  = $this->buildHeuristicMappings($rawData['headers'], $detectedEntity);
                $warnings = ['Suggestions IA indisponibles — mappings heuristiques appliqués.'];
            }
        } else {
            $columns  = $this->buildHeuristicMappings($rawData['headers'], $detectedEntity);
            $warnings = ['Clé API Anthropic non configurée — mappings heuristiques appliqués.'];
        }

        // Detect ambiguous columns
        $ambiguous = $this->detectAmbiguousColumns($rawData['headers']);
        foreach ($ambiguous as $col => $hint) {
            $warnings[] = "Colonne \"{$col}\" ambiguë — {$hint}";
        }

        return [
            'detected_entity' => $detectedEntity,
            'columns'         => $columns,
            'sample_rows'     => array_slice($rawData['sample_rows'], 0, 5),
            'warnings'        => $warnings,
            'estimated_rows'  => $rawData['row_count'],
        ];
    }

    /**
     * Validate a column mapping and return a data-quality report.
     *
     * @param  list<array{source: string, target: string}> $mapping
     * @return array{
     *   valid: bool,
     *   errors: list<string>,
     *   warnings: list<string>,
     *   duplicates: int,
     *   missing_required: list<string>,
     *   sample_transformed: list<array<string,mixed>>,
     * }
     */
    public function validateMapping(string $filePath, array $mapping): array
    {
        $rawData = $this->readFilePreview($filePath, 5);

        // Determine entity from mapping targets
        $targetFields = array_column($mapping, 'target');
        $entity       = $this->inferEntityFromTargets($targetFields);
        $schema       = self::ENTITY_SCHEMAS[$entity] ?? ['required' => [], 'optional' => []];

        $errors          = [];
        $warnings        = [];
        $missingRequired = [];

        // Check required fields are mapped
        foreach ($schema['required'] as $required) {
            if (! in_array($required, $targetFields, true)) {
                $missingRequired[] = $required;
                $errors[]          = "Champ obligatoire manquant : \"{$required}\"";
            }
        }

        // Check for duplicate target mappings
        $targetCounts = array_count_values($targetFields);
        $duplicates   = 0;
        foreach ($targetCounts as $field => $count) {
            if ($count > 1) {
                $duplicates++;
                $warnings[] = "Champ cible \"{$field}\" mappé {$count} fois.";
            }
        }

        // Build sample transformed rows
        $sampleTransformed = [];
        foreach ($rawData['sample_rows'] as $row) {
            $transformed = [];
            foreach ($mapping as $map) {
                $source = $map['source'] ?? '';
                $target = $map['target'] ?? '';
                if ($target === '' || $target === 'ignore') {
                    continue;
                }
                $transformed[$target] = $row[$source] ?? null;
            }
            $sampleTransformed[] = $transformed;
        }

        // AI validation (sonnet for speed)
        $apiKey = (string) config('services.anthropic.key', '');
        if ($apiKey !== '' && ! empty($sampleTransformed)) {
            try {
                $aiWarnings = $this->callClaudeForValidation($mapping, $sampleTransformed, $entity, $apiKey);
                $warnings   = array_merge($warnings, $aiWarnings);
            } catch (\Throwable $e) {
                Log::warning('AiDataImportService: Claude API error during validateMapping', [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return [
            'valid'              => empty($errors),
            'errors'             => $errors,
            'warnings'           => $warnings,
            'duplicates'         => $duplicates,
            'missing_required'   => $missingRequired,
            'sample_transformed' => $sampleTransformed,
        ];
    }

    /**
     * Execute the import asynchronously via a queued job.
     *
     * @param  list<array{source: string, target: string}> $mapping
     */
    public function executeImport(string $filePath, array $mapping, string $tenantId): string
    {
        $jobId = (string) Str::uuid();

        // Initialise progress in cache (TTL 24h)
        Cache::put("import_job:{$jobId}", [
            'status'    => 'queued',
            'progress'  => 0,
            'imported'  => 0,
            'skipped'   => 0,
            'errors'    => [],
        ], now()->addHours(24));

        ImportDataJob::dispatch($jobId, $filePath, $mapping, $tenantId);

        return $jobId;
    }

    /**
     * Retrieve the current status of an import job.
     *
     * @return array{
     *   status: string,
     *   progress: int,
     *   imported: int,
     *   skipped: int,
     *   errors: list<string>,
     * }
     */
    public function getImportStatus(string $jobId): array
    {
        $state = Cache::get("import_job:{$jobId}");

        if ($state === null) {
            return [
                'status'   => 'not_found',
                'progress' => 0,
                'imported' => 0,
                'skipped'  => 0,
                'errors'   => [],
            ];
        }

        return $state;
    }

    /**
     * Return the list of importable entity types with their field schemas.
     */
    public function getTemplates(): array
    {
        $templates = [];
        foreach (self::ENTITY_SCHEMAS as $entity => $schema) {
            $templates[] = [
                'entity'   => $entity,
                'label'    => $this->entityLabel($entity),
                'required' => $schema['required'],
                'optional' => $schema['optional'],
            ];
        }
        return $templates;
    }

    // -------------------------------------------------------------------------
    // File reading
    // -------------------------------------------------------------------------

    /**
     * Read the first $maxRows rows from a CSV / Excel / PDF file.
     *
     * @return array{headers: list<string>, sample_rows: list<array<string,mixed>>, row_count: int}
     */
    private function readFilePreview(string $filePath, int $maxRows): array
    {
        $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

        return match ($ext) {
            'xlsx', 'xls' => $this->readExcelPreview($filePath, $maxRows),
            'pdf'         => $this->readPdfPreview($filePath, $maxRows),
            default       => $this->readCsvPreview($filePath, $maxRows),
        };
    }

    private function readCsvPreview(string $filePath, int $maxRows): array
    {
        if (! file_exists($filePath)) {
            return ['headers' => [], 'sample_rows' => [], 'row_count' => 0];
        }

        $handle = fopen($filePath, 'r');
        if ($handle === false) {
            return ['headers' => [], 'sample_rows' => [], 'row_count' => 0];
        }

        // Detect delimiter
        $firstLine = fgets($handle);
        rewind($handle);
        $delimiter = str_contains((string) $firstLine, ';') ? ';' : ',';

        $headers    = [];
        $sampleRows = [];
        $rowCount   = 0;
        $lineNum    = 0;

        while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
            if ($lineNum === 0) {
                $headers = array_map('trim', $row);
            } elseif ($lineNum <= $maxRows) {
                $sampleRows[] = array_combine($headers, array_pad($row, count($headers), null));
            }
            $rowCount++;
            $lineNum++;
        }
        fclose($handle);

        return [
            'headers'     => $headers,
            'sample_rows' => $sampleRows,
            'row_count'   => max(0, $rowCount - 1), // subtract header row
        ];
    }

    private function readExcelPreview(string $filePath, int $maxRows): array
    {
        if (class_exists('\PhpOffice\PhpSpreadsheet\IOFactory')) {
            try {
                $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($filePath);
                $sheet       = $spreadsheet->getActiveSheet();
                $highestRow  = $sheet->getHighestDataRow();
                $highestCol  = $sheet->getHighestDataColumn();

                $headers = [];
                foreach (range('A', $highestCol) as $col) {
                    $headers[] = (string) $sheet->getCell("{$col}1")->getValue();
                }
                $headers = array_filter($headers);
                $headers = array_values($headers);

                $sampleRows = [];
                for ($row = 2; $row <= min($maxRows + 1, $highestRow); $row++) {
                    $rowData = [];
                    foreach ($headers as $idx => $header) {
                        $col           = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($idx + 1);
                        $rowData[$header] = $sheet->getCell("{$col}{$row}")->getFormattedValue();
                    }
                    $sampleRows[] = $rowData;
                }

                return [
                    'headers'     => $headers,
                    'sample_rows' => $sampleRows,
                    'row_count'   => max(0, $highestRow - 1),
                ];
            } catch (\Throwable) {
                // fallthrough to CSV fallback
            }
        }

        // Fallback: treat as CSV
        return $this->readCsvPreview($filePath, $maxRows);
    }

    private function readPdfPreview(string $filePath, int $maxRows): array
    {
        // Basic PDF text extraction — works for text-based PDFs
        // For production use Smalot\PdfParser or pdftotext CLI
        if (class_exists('\Smalot\PdfParser\Parser')) {
            try {
                $parser  = new \Smalot\PdfParser\Parser();
                $pdf     = $parser->parseFile($filePath);
                $text    = $pdf->getText();
                $lines   = array_filter(explode("\n", $text));
                $lines   = array_values($lines);
                $headers = str_getcsv($lines[0] ?? '', "\t");

                $sampleRows = [];
                for ($i = 1; $i < min($maxRows + 1, count($lines)); $i++) {
                    $row = str_getcsv($lines[$i], "\t");
                    $sampleRows[] = array_combine($headers, array_pad($row, count($headers), null));
                }

                return [
                    'headers'     => $headers,
                    'sample_rows' => $sampleRows,
                    'row_count'   => count($lines) - 1,
                ];
            } catch (\Throwable) {
                // fallthrough
            }
        }

        return [
            'headers'     => ['Contenu PDF'],
            'sample_rows' => [],
            'row_count'   => 0,
        ];
    }

    // -------------------------------------------------------------------------
    // AI calls
    // -------------------------------------------------------------------------

    /**
     * Call Claude Opus for column mapping analysis.
     */
    private function callClaudeForMapping(array $headers, array $sampleRows, string $apiKey): array
    {
        $schemaJson = json_encode(self::ENTITY_SCHEMAS, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        $systemPrompt = <<<PROMPT
Tu es un assistant expert en import de données ERP. Tu dois analyser les colonnes d'un fichier et les mapper vers les entités WideHalo ERP.

Schémas cibles disponibles :
{$schemaJson}

Réponds UNIQUEMENT avec un objet JSON valide de la forme suivante :
{
  "detected_entity": "<contacts|products|suppliers|employees|invoices|stock>",
  "columns": [
    {"source": "<nom colonne source>", "target": "<champ cible>", "confidence": <0.0-1.0>}
  ],
  "warnings": ["<avertissement si colonne ambiguë>"]
}

Règles :
- detected_entity = l'entité qui correspond le mieux à l'ensemble des colonnes
- Pour chaque colonne source, propose le champ cible le plus probable
- Si tu ne peux pas mapper une colonne, mets target = "ignore" et confidence = 0.0
- confidence entre 0 et 1 (1 = certitude absolue)
- warnings : signale les colonnes ambiguës ou les risques de qualité des données
- Réponds en français
PROMPT;

        $userMessage = sprintf(
            "Colonnes du fichier : %s\n\nExemples de données (5 premières lignes) :\n%s",
            implode(', ', $headers),
            json_encode(array_slice($sampleRows, 0, 5), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
        );

        $response = Http::withHeaders([
            'x-api-key'         => $apiKey,
            'anthropic-version' => '2023-06-01',
        ])->post(self::API_URL, [
            'model'      => self::MODEL_OPUS,
            'max_tokens' => 1500,
            'system'     => [
                [
                    'type'          => 'text',
                    'text'          => $systemPrompt,
                    'cache_control' => ['type' => 'ephemeral'],
                ],
            ],
            'messages' => [
                ['role' => 'user', 'content' => $userMessage],
            ],
        ]);

        if ($response->failed()) {
            throw new \RuntimeException('Claude API error: ' . $response->body());
        }

        $content = $response->json('content.0.text', '');
        $decoded = json_decode((string) $content, true);

        if (! is_array($decoded)) {
            throw new \RuntimeException('Claude returned invalid JSON: ' . $content);
        }

        return $decoded;
    }

    /**
     * Call Claude Sonnet for quick data-quality validation.
     *
     * @return list<string> List of warning messages
     */
    private function callClaudeForValidation(
        array $mapping,
        array $sampleTransformed,
        string $entity,
        string $apiKey,
    ): array {
        $systemPrompt = "Tu es un expert en qualité des données ERP. Analyse ce mapping et ces exemples de données transformées, puis retourne une liste JSON de warnings (en français) sur la qualité des données. Réponds UNIQUEMENT avec un tableau JSON de chaînes.";

        $userMessage = sprintf(
            "Entité cible : %s\nMapping : %s\nExemples transformés : %s",
            $entity,
            json_encode($mapping, JSON_UNESCAPED_UNICODE),
            json_encode($sampleTransformed, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
        );

        $response = Http::withHeaders([
            'x-api-key'         => $apiKey,
            'anthropic-version' => '2023-06-01',
        ])->post(self::API_URL, [
            'model'      => self::MODEL_SONNET,
            'max_tokens' => 600,
            'system'     => [
                [
                    'type'          => 'text',
                    'text'          => $systemPrompt,
                    'cache_control' => ['type' => 'ephemeral'],
                ],
            ],
            'messages' => [
                ['role' => 'user', 'content' => $userMessage],
            ],
        ]);

        if ($response->failed()) {
            return [];
        }

        $content = $response->json('content.0.text', '[]');
        $decoded = json_decode((string) $content, true);

        return is_array($decoded) ? $decoded : [];
    }

    // -------------------------------------------------------------------------
    // Heuristics
    // -------------------------------------------------------------------------

    private function detectEntityHeuristic(array $headers): string
    {
        $normalized = array_map(fn($h) => strtolower(trim($h)), $headers);
        $scores     = [];

        foreach (self::ENTITY_SCHEMAS as $entity => $schema) {
            $heuristics = self::HEURISTIC_MAP[$entity] ?? [];
            $score      = 0;
            foreach ($normalized as $header) {
                if (isset($heuristics[$header])) {
                    $score++;
                }
            }
            $scores[$entity] = $score;
        }

        arsort($scores);

        return (string) array_key_first($scores) ?: 'contacts';
    }

    /**
     * @return list<array{source: string, target: string, confidence: float}>
     */
    private function buildHeuristicMappings(array $headers, string $entity): array
    {
        $heuristics = self::HEURISTIC_MAP[$entity] ?? [];
        $columns    = [];

        foreach ($headers as $header) {
            $normalized = strtolower(trim($header));
            $target     = $heuristics[$normalized] ?? 'ignore';
            $confidence = $target !== 'ignore' ? 0.70 : 0.0;

            $columns[] = [
                'source'     => $header,
                'target'     => $target,
                'confidence' => $confidence,
            ];
        }

        return $columns;
    }

    /**
     * Detect columns that might be ambiguous (e.g. "TEL" could be mobile or landline).
     *
     * @return array<string, string>
     */
    private function detectAmbiguousColumns(array $headers): array
    {
        $ambiguous   = [];
        $ambiguousCandidates = [
            'tel'    => 'mobile ou fixe ?',
            'phone'  => 'mobile ou fixe ?',
            'date'   => 'date de création, naissance ou contrat ?',
            'status' => 'statut actif/inactif ou statut de paiement ?',
            'ref'    => 'référence produit ou numéro client ?',
            'nom'    => 'nom de famille ou nom complet ?',
            'name'   => 'full name or last name?',
        ];

        foreach ($headers as $header) {
            $norm = strtolower(trim($header));
            if (isset($ambiguousCandidates[$norm])) {
                $ambiguous[$header] = $ambiguousCandidates[$norm];
            }
        }

        return $ambiguous;
    }

    private function inferEntityFromTargets(array $targets): string
    {
        foreach (self::ENTITY_SCHEMAS as $entity => $schema) {
            $allFields = array_merge($schema['required'], $schema['optional']);
            $matches   = count(array_intersect($targets, $allFields));
            if ($matches >= count($schema['required'])) {
                return $entity;
            }
        }
        return 'contacts';
    }

    private function emptyAnalysisResult(): array
    {
        return [
            'detected_entity' => 'contacts',
            'columns'         => [],
            'sample_rows'     => [],
            'warnings'        => ['Impossible de lire le fichier ou fichier vide.'],
            'estimated_rows'  => 0,
        ];
    }

    private function entityLabel(string $entity): string
    {
        return match ($entity) {
            'contacts'  => 'Contacts',
            'products'  => 'Produits',
            'suppliers' => 'Fournisseurs',
            'employees' => 'Employés',
            'invoices'  => 'Factures',
            'stock'     => 'Stock',
            default     => ucfirst($entity),
        };
    }
}
