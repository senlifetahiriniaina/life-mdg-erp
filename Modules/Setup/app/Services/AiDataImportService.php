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

    /**
     * Supported target entity schemas with their required / optional fields.
     *
     * Chantier 32.10 (deep 14-layer audit): confirmed empirically (a real
     * CSV run through this exact pipeline, inspected via `tinker`, not just
     * read) that 4 of the original 6 entities had target field names that
     * did not match any real column on their destination table — silently
     * dropped by `ImportDataJob::bulkInsert()`'s column-intersection filter,
     * meaning "required" fields like an invoice's date/client/amount were
     * never actually written despite the import reporting success. Field
     * names below are the real destination column names (confirmed via
     * `Schema::getColumnListing()` against every table this class targets);
     * anything with no real column at all was removed rather than offered
     * and silently discarded — see this class's and ImportDataJob's own
     * inline comments for the per-entity reasoning, and CLAUDE.md's
     * Chantier 32.10 entry for the full investigation.
     *
     * `stock` was removed entirely: `inventory_stock_movements` has a
     * NOT NULL `type` column this generic pipeline has no way to populate
     * (in/out/adjustment — not a data-mapping decision, a business one) and
     * no `product_sku`/`warehouse`/`unit_cost` columns at all (real FKs:
     * `product_id`/`warehouse_id`, requiring a SKU/name→id lookup this
     * generic bulk importer was never built to do) — confirmed empirically
     * that 100% of stock rows fatally failed the insert, 0% success rate,
     * not a partial/edge-case gap. Chantier 16 already built a real,
     * working, purpose-specific stock-import feature for this exact need
     * (`Modules\Inventory\Services\StockImportService` /
     * `Stock/Import.vue` — auto-creates unknown products, resolves the
     * warehouse from a real picker, records a real `StockMovement`) —
     * duplicating that logic here, badly, would be the same
     * dead-parallel-subsystem anti-pattern this session has repeatedly
     * found and removed elsewhere (see CLAUDE.md). Users importing stock
     * movements should use that real feature instead.
     */
    private const ENTITY_SCHEMAS = [
        'contacts' => [
            'required' => ['full_name'],
            'optional' => ['email', 'phone'],
        ],
        // 'sku' moved to required: confirmed via Schema::getColumnListing()
        // that `inventory_products.sku` is NOT NULL with no default (the
        // real ProductController::store() also requires it explicitly, no
        // auto-generation) — every products row missing it was a
        // guaranteed per-row insert failure, previously mislabelled
        // "optional" here.
        'products' => [
            'required' => ['name', 'sku'],
            'optional' => ['category', 'selling_price', 'cost_price', 'unit', 'description'],
        ],
        'suppliers' => [
            'required' => ['name'],
            'optional' => ['email', 'phone', 'country', 'currency', 'payment_terms'],
        ],
        'employees' => [
            'required' => ['full_name'],
            'optional' => ['email', 'job_title', 'hire_date'],
        ],
        'invoices' => [
            'required' => ['number', 'invoice_date', 'partner_name', 'total'],
            'optional' => ['currency', 'status'],
        ],
    ];

    /**
     * Heuristic keyword → target field mapping (fallback).
     *
     * Chantier 32.10: kept in sync with ENTITY_SCHEMAS above — every target
     * value here must be a real column name on the entity's destination
     * table (see ImportDataJob::entityToTable()), never a target field the
     * write path would silently drop. 'company'/'country'/'address' (no
     * real column on crm_contacts), 'price'/'cost' (real columns are
     * `selling_price`/`cost_price`), 'department'/'position'/'salary' (no
     * real column on hr_employees except 'job_title' for position — 'poste'/
     * 'titre' now map there), and the whole 'stock' section (see
     * ENTITY_SCHEMAS' docblock — removed, superseded by the real
     * StockImportService feature) were all fixed/removed accordingly.
     */
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
        ],
        'products' => [
            'nom'          => 'name',
            'name'         => 'name',
            'produit'      => 'name',
            'product'      => 'name',
            'sku'          => 'sku',
            'référence'    => 'sku',
            'reference'    => 'sku',
            'ref'          => 'sku',
            'categorie'    => 'category',
            'category'     => 'category',
            'prix'         => 'selling_price',
            'price'        => 'selling_price',
            'prix vente'   => 'selling_price',
            'coût'         => 'cost_price',
            'cout'         => 'cost_price',
            'cost'         => 'cost_price',
            'prix achat'   => 'cost_price',
            'unité'        => 'unit',
            'unite'        => 'unit',
            'unit'         => 'unit',
            'description'  => 'description',
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
            'poste'          => 'job_title',
            'position'       => 'job_title',
            'titre'          => 'job_title',
            'job_title'      => 'job_title',
            'date'           => 'hire_date',
            'embauche'       => 'hire_date',
            'hire_date'      => 'hire_date',
        ],
        'invoices' => [
            'numéro'         => 'number',
            'numero'         => 'number',
            'number'         => 'number',
            'facture'        => 'number',
            'date'           => 'invoice_date',
            'date facture'   => 'invoice_date',
            'invoice_date'   => 'invoice_date',
            'client'         => 'partner_name',
            'client_name'    => 'partner_name',
            'partner_name'   => 'partner_name',
            'montant'        => 'total',
            'amount'         => 'total',
            'total'          => 'total',
            'devise'         => 'currency',
            'currency'       => 'currency',
            'statut'         => 'status',
            'status'         => 'status',
            'état'           => 'status',
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

        while (($row = fgetcsv($handle, 0, $delimiter, '"', '\\')) !== false) {
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
            default     => ucfirst($entity),
        };
    }
}
