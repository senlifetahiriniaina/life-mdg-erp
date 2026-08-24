<?php

declare(strict_types=1);

namespace Modules\Accounting\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Modules\Core\Services\AI\AnthropicProvider;

/**
 * Chantier 32 (volet C) — capture d'une facture fournisseur par
 * scan/photo/PDF, via Claude vision (le choix explicite de l'utilisateur,
 * confirmé par checkpoint AskUserQuestion — jamais un simple aperçu
 * automatique, toujours suivi d'une validation humaine avant tout
 * enregistrement).
 *
 * Appelle `AnthropicProvider` directement plutôt que via
 * `AIService::forModule()` : ce dernier peut résoudre vers OpenAI/DeepSeek
 * selon la configuration (`AI_DEFAULT_PROVIDER`), qui n'acceptent pas le
 * même format de bloc de contenu multimodal (image/document en base64)
 * que l'API Anthropic — et l'utilisateur a explicitement demandé Claude
 * pour cette extraction, pas "le fournisseur IA actif, quel qu'il soit".
 *
 * Repli fallback-first obligatoire (principe déjà établi partout dans
 * cette app) : si Anthropic n'est pas configuré ou que l'appel échoue,
 * retourne un résultat vide avec `enabled:false` plutôt qu'une erreur —
 * l'utilisateur peut toujours saisir manuellement dans le formulaire de
 * révision.
 */
class SupplierInvoiceScanService
{
    private const MAX_SIZE_BYTES = 10 * 1024 * 1024; // 10 Mo

    private const ALLOWED_MIME_TYPES = [
        'image/jpeg' => 'image',
        'image/png' => 'image',
        'image/webp' => 'image',
        'application/pdf' => 'document',
    ];

    public function __construct(private readonly AnthropicProvider $anthropic) {}

    public function isSupportedFile(UploadedFile $file): bool
    {
        return isset(self::ALLOWED_MIME_TYPES[$file->getMimeType()]) && $file->getSize() <= self::MAX_SIZE_BYTES;
    }

    /**
     * @return array{
     *   enabled: bool,
     *   fields: array{number: ?string, partner_name: ?string, invoice_date: ?string, due_date: ?string, currency: ?string, subtotal: ?float, tax_amount: ?float, total: ?float, lines: list<array{description: ?string, quantity: ?float, unit_price: ?float}>},
     * }
     */
    public function extract(UploadedFile $file): array
    {
        $empty = [
            'enabled' => false,
            'fields' => [
                'number' => null, 'partner_name' => null, 'invoice_date' => null, 'due_date' => null,
                'currency' => null, 'subtotal' => null, 'tax_amount' => null, 'total' => null, 'lines' => [],
            ],
        ];

        if (! $this->anthropic->isConfigured()) {
            return $empty;
        }

        try {
            $mimeType = $file->getMimeType();
            $blockType = self::ALLOWED_MIME_TYPES[$mimeType] ?? 'image';
            $base64 = base64_encode(file_get_contents($file->getRealPath()));

            $contentBlock = $blockType === 'document'
                ? ['type' => 'document', 'source' => ['type' => 'base64', 'media_type' => $mimeType, 'data' => $base64]]
                : ['type' => 'image', 'source' => ['type' => 'base64', 'media_type' => $mimeType, 'data' => $base64]];

            $prompt = <<<'PROMPT'
Tu es un assistant comptable. Extrait les informations de cette facture fournisseur et réponds UNIQUEMENT avec un objet JSON valide, sans texte avant/après, au format exact suivant :
{"number": string|null, "partner_name": string|null, "invoice_date": "YYYY-MM-DD"|null, "due_date": "YYYY-MM-DD"|null, "currency": string|null (code ISO 3 lettres), "subtotal": number|null, "tax_amount": number|null, "total": number|null, "lines": [{"description": string, "quantity": number|null, "unit_price": number|null}]}
Si un champ n'est pas visible sur le document, mets null plutôt que d'inventer une valeur.
PROMPT;

            $response = $this->anthropic->chat([
                ['role' => 'user', 'content' => [$contentBlock, ['type' => 'text', 'text' => $prompt]]],
            ], ['max_tokens' => 1500]);

            $fields = $this->parseJsonResponse($response);

            return $fields === null ? $empty : ['enabled' => true, 'fields' => $fields];
        } catch (\Throwable $e) {
            Log::warning('SupplierInvoiceScanService::extract() failed', ['error' => $e->getMessage()]);

            return $empty;
        }
    }

    private function parseJsonResponse(string $response): ?array
    {
        // Claude occasionally wraps the JSON in a ```json fence despite the
        // prompt's instruction not to — stripped defensively rather than
        // trusting the model's own formatting discipline.
        $trimmed = trim($response);
        $trimmed = preg_replace('/^```(?:json)?\s*|\s*```$/', '', $trimmed) ?? $trimmed;

        $decoded = json_decode($trimmed, true);
        if (! is_array($decoded)) {
            return null;
        }

        return [
            'number' => $decoded['number'] ?? null,
            'partner_name' => $decoded['partner_name'] ?? null,
            'invoice_date' => $decoded['invoice_date'] ?? null,
            'due_date' => $decoded['due_date'] ?? null,
            'currency' => $decoded['currency'] ?? null,
            'subtotal' => isset($decoded['subtotal']) ? (float) $decoded['subtotal'] : null,
            'tax_amount' => isset($decoded['tax_amount']) ? (float) $decoded['tax_amount'] : null,
            'total' => isset($decoded['total']) ? (float) $decoded['total'] : null,
            'lines' => array_map(fn (array $l) => [
                'description' => $l['description'] ?? null,
                'quantity' => isset($l['quantity']) ? (float) $l['quantity'] : null,
                'unit_price' => isset($l['unit_price']) ? (float) $l['unit_price'] : null,
            ], $decoded['lines'] ?? []),
        ];
    }
}
