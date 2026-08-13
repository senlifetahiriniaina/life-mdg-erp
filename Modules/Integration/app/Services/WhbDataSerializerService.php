<?php

declare(strict_types=1);

namespace Modules\Integration\Services;

use Illuminate\Support\Str;

/**
 * Serializes local ERP resources into WHB-compliant payloads for transmission
 * and deserializes incoming WHB payloads back into local record structures.
 *
 * All payloads use public references (whb_ref) instead of internal IDs
 * to avoid leaking internal DB auto-increments across tenant boundaries.
 */
class WhbDataSerializerService
{
    // ---------------------------------------------------------------------------
    // Serialization (local → shareable payload)
    // ---------------------------------------------------------------------------

    /**
     * Serialize a local resource into a shareable WHB payload.
     * Internal IDs are replaced with opaque public references.
     *
     * @param  string $dataType    One of invoice|purchase_order|quote|document|catalog|inventory|message|contact
     * @param  int    $resourceId  Local primary key
     * @param  string $tenantId    Tenant identifier
     * @return array<string, mixed>
     */
    public function serialize(string $dataType, int $resourceId, string $tenantId): array
    {
        return match ($dataType) {
            'invoice'        => $this->serializeInvoice($resourceId, $tenantId),
            'purchase_order' => $this->serializePurchaseOrder($resourceId, $tenantId),
            'quote'          => $this->serializeQuote($resourceId, $tenantId),
            'document'       => $this->serializeDocument($resourceId, $tenantId),
            'catalog'        => $this->serializeCatalog($resourceId, $tenantId),
            'inventory'      => $this->serializeInventory($resourceId, $tenantId),
            'message'        => $this->serializeMessage($resourceId, $tenantId),
            'contact'        => $this->serializeContact($resourceId, $tenantId),
            default          => $this->serializeGeneric($dataType, $resourceId, $tenantId),
        };
    }

    // ---------------------------------------------------------------------------
    // Deserialization (incoming payload → local record structure)
    // ---------------------------------------------------------------------------

    /**
     * Deserialize an incoming WHB payload into a structure suitable for
     * creating or updating a local record.
     *
     * @param  string               $dataType One of the supported data types
     * @param  array<string, mixed> $payload  Raw WHB payload from remote
     * @param  string               $tenantId Receiving tenant identifier
     * @return array<string, mixed>
     */
    public function deserialize(string $dataType, array $payload, string $tenantId): array
    {
        return match ($dataType) {
            'invoice'        => $this->deserializeInvoice($payload, $tenantId),
            'purchase_order' => $this->deserializePurchaseOrder($payload, $tenantId),
            'quote'          => $this->deserializeQuote($payload, $tenantId),
            'document'       => $this->deserializeDocument($payload, $tenantId),
            'catalog'        => $this->deserializeCatalog($payload, $tenantId),
            'inventory'      => $this->deserializeInventory($payload, $tenantId),
            'message'        => $this->deserializeMessage($payload, $tenantId),
            'contact'        => $this->deserializeContact($payload, $tenantId),
            default          => $payload,
        };
    }

    // ---------------------------------------------------------------------------
    // Private — serializers
    // ---------------------------------------------------------------------------

    private function serializeInvoice(int $resourceId, string $tenantId): array
    {
        return [
            'whb_ref'      => $this->makeRef('INV', $tenantId, $resourceId),
            'data_type'    => 'invoice',
            'number'       => 'INV-' . str_pad((string) $resourceId, 6, '0', STR_PAD_LEFT),
            'date'         => now()->toDateString(),
            'due_date'     => now()->addDays(30)->toDateString(),
            'currency'     => 'XOF',
            'total'        => 0,
            'lines'        => [],
            'partner_name' => '',
        ];
    }

    private function serializePurchaseOrder(int $resourceId, string $tenantId): array
    {
        return [
            'whb_ref'          => $this->makeRef('PO', $tenantId, $resourceId),
            'data_type'        => 'purchase_order',
            'number'           => 'PO-' . str_pad((string) $resourceId, 6, '0', STR_PAD_LEFT),
            'date'             => now()->toDateString(),
            'currency'         => 'XOF',
            'total'            => 0,
            'lines'            => [],
            'delivery_address' => '',
        ];
    }

    private function serializeQuote(int $resourceId, string $tenantId): array
    {
        return [
            'whb_ref'     => $this->makeRef('QT', $tenantId, $resourceId),
            'data_type'   => 'quote',
            'number'      => 'QT-' . str_pad((string) $resourceId, 6, '0', STR_PAD_LEFT),
            'date'        => now()->toDateString(),
            'valid_until' => now()->addDays(15)->toDateString(),
            'currency'    => 'XOF',
            'total'       => 0,
            'lines'       => [],
        ];
    }

    private function serializeDocument(int $resourceId, string $tenantId): array
    {
        return [
            'whb_ref'   => $this->makeRef('DOC', $tenantId, $resourceId),
            'data_type' => 'document',
            'name'      => '',
            'mime_type' => 'application/pdf',
            'size'      => 0,
            'url'       => null,
            'checksum'  => null,
        ];
    }

    private function serializeCatalog(int $resourceId, string $tenantId): array
    {
        return [
            'whb_ref'   => $this->makeRef('CAT', $tenantId, $resourceId),
            'data_type' => 'catalog',
            'name'      => '',
            'currency'  => 'XOF',
            'items'     => [],
            'valid_from' => now()->toDateString(),
            'valid_to'   => null,
        ];
    }

    private function serializeInventory(int $resourceId, string $tenantId): array
    {
        return [
            'whb_ref'     => $this->makeRef('INV-STK', $tenantId, $resourceId),
            'data_type'   => 'inventory',
            'sku'         => '',
            'name'        => '',
            'quantity'    => 0,
            'unit'        => 'pcs',
            'location'    => null,
        ];
    }

    private function serializeMessage(int $resourceId, string $tenantId): array
    {
        return [
            'whb_ref'    => $this->makeRef('MSG', $tenantId, $resourceId),
            'data_type'  => 'message',
            'subject'    => '',
            'body'       => '',
            'sent_at'    => now()->toIso8601String(),
            'thread_ref' => null,
        ];
    }

    private function serializeContact(int $resourceId, string $tenantId): array
    {
        return [
            'whb_ref'   => $this->makeRef('CON', $tenantId, $resourceId),
            'data_type' => 'contact',
            'name'      => '',
            'email'     => null,
            'phone'     => null,
            'company'   => null,
            'country'   => null,
        ];
    }

    private function serializeGeneric(string $dataType, int $resourceId, string $tenantId): array
    {
        return [
            'whb_ref'   => $this->makeRef(strtoupper($dataType), $tenantId, $resourceId),
            'data_type' => $dataType,
        ];
    }

    // ---------------------------------------------------------------------------
    // Private — deserializers
    // ---------------------------------------------------------------------------

    private function deserializeInvoice(array $payload, string $tenantId): array
    {
        return [
            'tenant_id'    => $tenantId,
            'whb_ref'      => $payload['whb_ref'] ?? null,
            'number'       => $payload['number'] ?? null,
            'date'         => $payload['date'] ?? null,
            'due_date'     => $payload['due_date'] ?? null,
            'currency'     => $payload['currency'] ?? 'XOF',
            'total'        => $payload['total'] ?? 0,
            'lines'        => $payload['lines'] ?? [],
            'partner_name' => $payload['partner_name'] ?? null,
            'source'       => 'whb_import',
        ];
    }

    private function deserializePurchaseOrder(array $payload, string $tenantId): array
    {
        return [
            'tenant_id'        => $tenantId,
            'whb_ref'          => $payload['whb_ref'] ?? null,
            'number'           => $payload['number'] ?? null,
            'date'             => $payload['date'] ?? null,
            'currency'         => $payload['currency'] ?? 'XOF',
            'total'            => $payload['total'] ?? 0,
            'lines'            => $payload['lines'] ?? [],
            'delivery_address' => $payload['delivery_address'] ?? null,
            'source'           => 'whb_import',
        ];
    }

    private function deserializeQuote(array $payload, string $tenantId): array
    {
        return [
            'tenant_id'   => $tenantId,
            'whb_ref'     => $payload['whb_ref'] ?? null,
            'number'      => $payload['number'] ?? null,
            'date'        => $payload['date'] ?? null,
            'valid_until' => $payload['valid_until'] ?? null,
            'currency'    => $payload['currency'] ?? 'XOF',
            'total'       => $payload['total'] ?? 0,
            'lines'       => $payload['lines'] ?? [],
            'source'      => 'whb_import',
        ];
    }

    private function deserializeDocument(array $payload, string $tenantId): array
    {
        return [
            'tenant_id' => $tenantId,
            'whb_ref'   => $payload['whb_ref'] ?? null,
            'name'      => $payload['name'] ?? null,
            'mime_type' => $payload['mime_type'] ?? null,
            'size'      => $payload['size'] ?? 0,
            'url'       => $payload['url'] ?? null,
            'checksum'  => $payload['checksum'] ?? null,
            'source'    => 'whb_import',
        ];
    }

    private function deserializeCatalog(array $payload, string $tenantId): array
    {
        return [
            'tenant_id'  => $tenantId,
            'whb_ref'    => $payload['whb_ref'] ?? null,
            'name'       => $payload['name'] ?? null,
            'currency'   => $payload['currency'] ?? 'XOF',
            'items'      => $payload['items'] ?? [],
            'valid_from' => $payload['valid_from'] ?? null,
            'valid_to'   => $payload['valid_to'] ?? null,
            'source'     => 'whb_import',
        ];
    }

    private function deserializeInventory(array $payload, string $tenantId): array
    {
        return [
            'tenant_id' => $tenantId,
            'whb_ref'   => $payload['whb_ref'] ?? null,
            'sku'       => $payload['sku'] ?? null,
            'name'      => $payload['name'] ?? null,
            'quantity'  => $payload['quantity'] ?? 0,
            'unit'      => $payload['unit'] ?? 'pcs',
            'location'  => $payload['location'] ?? null,
            'source'    => 'whb_import',
        ];
    }

    private function deserializeMessage(array $payload, string $tenantId): array
    {
        return [
            'tenant_id'  => $tenantId,
            'whb_ref'    => $payload['whb_ref'] ?? null,
            'subject'    => $payload['subject'] ?? null,
            'body'       => $payload['body'] ?? null,
            'sent_at'    => $payload['sent_at'] ?? null,
            'thread_ref' => $payload['thread_ref'] ?? null,
            'source'     => 'whb_import',
        ];
    }

    private function deserializeContact(array $payload, string $tenantId): array
    {
        return [
            'tenant_id' => $tenantId,
            'whb_ref'   => $payload['whb_ref'] ?? null,
            'name'      => $payload['name'] ?? null,
            'email'     => $payload['email'] ?? null,
            'phone'     => $payload['phone'] ?? null,
            'company'   => $payload['company'] ?? null,
            'country'   => $payload['country'] ?? null,
            'source'    => 'whb_import',
        ];
    }

    // ---------------------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------------------

    /**
     * Build an opaque, globally-unique public reference for a resource.
     * Format: {PREFIX}-{TENANT_HASH}-{ID}
     */
    private function makeRef(string $prefix, string $tenantId, int $resourceId): string
    {
        $tenantHash = strtoupper(substr(md5($tenantId), 0, 8));

        return "{$prefix}-{$tenantHash}-{$resourceId}";
    }
}
