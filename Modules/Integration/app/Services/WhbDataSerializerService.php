<?php

declare(strict_types=1);

namespace Modules\Integration\Services;

use Illuminate\Support\Str;
use Modules\Accounting\Models\Invoice;
use Modules\Achats\Models\PurchaseOrder;
use Modules\CRM\Models\Contact;
use Modules\CRM\Models\Quote;
use Modules\Inventory\Models\Product;
use Modules\Inventory\Models\Stock;
use RuntimeException;

/**
 * Serializes local ERP resources into WHB-compliant payloads for transmission
 * and deserializes incoming WHB payloads back into local record structures.
 *
 * All payloads use public references (whb_ref) instead of internal IDs
 * to avoid leaking internal DB auto-increments across tenant boundaries.
 *
 * Chantier 32.6: confirmed empirically that every serialize*() method here
 * was a pure stub — $resourceId/$tenantId were only ever used to build the
 * whb_ref string, the real record was NEVER fetched, so `total`/`lines`/
 * every real field was always hardcoded to 0/empty regardless of which
 * invoice/PO/quote/contact/product was referenced. This is not a
 * hypothetical gap: WhbPartnerService::sendData() (the module's real,
 * routed "send this business record to a connected partner" feature) calls
 * serialize() directly, so every WHB exchange this app has ever sent has
 * transmitted empty placeholder data. Fixed for the 5 data types that map
 * cleanly to one real, unambiguous Eloquent model already used elsewhere in
 * this exact WHB data_type enum. `document`/`catalog`/`message` have no
 * real single-model backing anywhere in this app's scope (no generic
 * Documents module, no catalog concept, and Messaging's Message model is a
 * different domain — internal team chat, not partner-to-partner exchange)
 * — left as honest documented stubs rather than guessed at, matching this
 * session's established precedent for a genuinely absent data source (e.g.
 * CRM's EinsteinForecastingService::forecastByProduct()).
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

    /**
     * Accounting's acc_invoices table has no tenant/company column of any
     * kind anywhere in this app (confirmed via Schema::hasColumn) — this
     * module treats Accounting as one shared ledger everywhere else too
     * (see FinancialReportService/JournalEntryApiController), so unlike
     * purchase_order/contact below, there is no real per-tenant boundary
     * to enforce here; $tenantId is accepted for signature/ref-format
     * compatibility only, same documented tradeoff already used by
     * OhadaReportService's own $tenantId parameter.
     */
    private function serializeInvoice(int $resourceId, string $tenantId): array
    {
        /** @var Invoice|null $invoice */
        $invoice = Invoice::with('lineItems')->find($resourceId);

        if (! $invoice) {
            throw new RuntimeException("Invoice #{$resourceId} not found.");
        }

        return [
            'whb_ref'      => $this->makeRef('INV', $tenantId, $resourceId),
            'data_type'    => 'invoice',
            'number'       => $invoice->number ?? ('INV-' . str_pad((string) $resourceId, 6, '0', STR_PAD_LEFT)),
            'date'         => optional($invoice->invoice_date)->toDateString(),
            'due_date'     => optional($invoice->due_date)->toDateString(),
            'currency'     => $invoice->currency ?? 'MGA',
            'total'        => (float) $invoice->total,
            'lines'        => $invoice->lineItems->map(fn ($line) => [
                'description' => $line->description,
                'quantity'    => (float) $line->quantity,
                'unit_price'  => (float) $line->unit_price,
                'total'       => (float) $line->total,
            ])->all(),
            'partner_name' => $invoice->customer_name ?? $invoice->partner_name ?? '',
        ];
    }

    private function serializePurchaseOrder(int $resourceId, string $tenantId): array
    {
        /** @var PurchaseOrder|null $order */
        $order = PurchaseOrder::with('lines')->find($resourceId);

        if (! $order) {
            throw new RuntimeException("Purchase order #{$resourceId} not found.");
        }

        $this->assertSameTenant($order->company_id, $tenantId, 'purchase_order');

        return [
            'whb_ref'          => $this->makeRef('PO', $tenantId, $resourceId),
            'data_type'        => 'purchase_order',
            'number'           => $order->po_number ?? ('PO-' . str_pad((string) $resourceId, 6, '0', STR_PAD_LEFT)),
            'date'             => optional($order->order_date)->toDateString(),
            'currency'         => $order->currency ?? 'MGA',
            'total'            => (float) $order->total,
            'lines'            => $order->lines->map(fn ($line) => [
                'description' => $line->description,
                'quantity'    => (float) $line->quantity,
                'unit_price'  => (float) $line->unit_price,
                'total'       => (float) $line->line_total,
            ])->all(),
            'delivery_address' => '',
        ];
    }

    private function serializeQuote(int $resourceId, string $tenantId): array
    {
        /** @var Quote|null $quote */
        $quote = Quote::with('lines')->find($resourceId);

        if (! $quote) {
            throw new RuntimeException("Quote #{$resourceId} not found.");
        }

        return [
            'whb_ref'     => $this->makeRef('QT', $tenantId, $resourceId),
            'data_type'   => 'quote',
            'number'      => $quote->reference ?? ('QT-' . str_pad((string) $resourceId, 6, '0', STR_PAD_LEFT)),
            'date'        => optional($quote->created_at)->toDateString(),
            'valid_until' => optional($quote->valid_until)->toDateString(),
            'currency'    => 'MGA',
            'total'       => (float) $quote->total,
            'lines'       => $quote->lines->map(fn ($line) => [
                'description' => $line->description,
                'quantity'    => (float) $line->quantity,
                'unit_price'  => (float) $line->unit_price,
                'total'       => (float) $line->line_total,
            ])->all(),
        ];
    }

    /**
     * No generic Documents concept exists anywhere in this app's real scope
     * (Modules/Documents was deliberately excluded — see CLAUDE.md's Scope
     * section) — an honest, unimplemented stub rather than a guess.
     */
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

    /**
     * No "catalog" concept exists anywhere in this app (no priced product
     * bundle/list model distinct from a plain Inventory Product) — an
     * honest, unimplemented stub rather than a guess.
     */
    private function serializeCatalog(int $resourceId, string $tenantId): array
    {
        return [
            'whb_ref'   => $this->makeRef('CAT', $tenantId, $resourceId),
            'data_type' => 'catalog',
            'name'      => '',
            'currency'  => 'MGA',
            'items'     => [],
            'valid_from' => now()->toDateString(),
            'valid_to'   => null,
        ];
    }

    private function serializeInventory(int $resourceId, string $tenantId): array
    {
        /** @var Product|null $product */
        $product = Product::find($resourceId);

        if (! $product) {
            throw new RuntimeException("Product #{$resourceId} not found.");
        }

        $quantity = (float) Stock::where('product_id', $resourceId)->sum('quantity');

        return [
            'whb_ref'     => $this->makeRef('INV-STK', $tenantId, $resourceId),
            'data_type'   => 'inventory',
            'sku'         => $product->sku ?? '',
            'name'        => $product->name ?? '',
            'quantity'    => $quantity,
            'unit'        => $product->unit ?? 'pcs',
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
        /** @var Contact|null $contact */
        $contact = Contact::find($resourceId);

        if (! $contact) {
            throw new RuntimeException("Contact #{$resourceId} not found.");
        }

        $this->assertSameTenant($contact->company_id, $tenantId, 'contact');

        return [
            'whb_ref'   => $this->makeRef('CON', $tenantId, $resourceId),
            'data_type' => 'contact',
            'name'      => trim($contact->first_name . ' ' . $contact->last_name),
            'email'     => $contact->email,
            'phone'     => $contact->phone ?? $contact->mobile,
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
     * Chantier 32.6 IDOR guard: before this fix, serialize() never actually
     * fetched a real record at all, so there was no cross-tenant exposure
     * risk to guard against — now that it does, a resourceId belonging to
     * another tenant must never leave via WHB. Only applied where a real,
     * confirmed-populated tenant/company scoping column exists on the
     * target model (purchase_order's achats_purchase_orders.company_id,
     * contact's crm_contacts.company_id) — invoice/quote/inventory have no
     * such column anywhere in this app (documented per-method above), so
     * nothing to guard there without inventing a scoping mechanism that
     * doesn't exist in those modules today.
     *
     * Note: crm_contacts.company_id is semantically the CRM "customer
     * company" FK (Contact::company() belongsTo Modules\CRM\Models\Company)
     * — but Modules\CRM\Http\Controllers\Api\ContactController::index()'s
     * own real, already-shipped tenant-scoping query already compares this
     * exact column directly against $user->company_id (the real ERP tenant
     * boundary), so this check matches CRM's own current, tested behavior
     * rather than inventing a different convention here. The underlying
     * column-name collision is a pre-existing CRM issue, out of this
     * module's boundary to resolve — flagged in CLAUDE.md for a future CRM
     * chantier rather than silently worked around.
     */
    private function assertSameTenant(int|string|null $resourceTenantId, string $tenantId, string $dataType): void
    {
        if ((string) ($resourceTenantId ?? '') !== $tenantId) {
            throw new RuntimeException("Not permitted to send this '{$dataType}' — it does not belong to your tenant.");
        }
    }

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
