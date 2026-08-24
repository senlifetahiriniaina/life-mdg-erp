<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Chantier 32.5 — deep 14-layer audit of Modules\API.
 *
 * Drops api_webhooks, the table backing `Modules\API\Models\ApiWebhook` /
 * `WebhookController` / `WebhookPolicy`, all deleted alongside this
 * migration. Confirmed dead/broken, not merely unwired:
 *
 * - Zero delivery mechanism anywhere: `recordSuccess()`/`recordFailure()`
 *   on the model had zero real callers, and `WebhookController::test()`
 *   never actually made an HTTP request — it returned a canned
 *   `{"message":"Test webhook dispatched"}` regardless of whether anything
 *   was dispatched at all (nothing was).
 * - A confirmed, active security bug: `index()`/`show()` (via a raw
 *   `DB::table('api_webhooks')->get()`) returned the plaintext HMAC
 *   `secret` column to the client on every list/create response —
 *   `ApiWebhook::$hidden` marks it hidden, but that only applies to the
 *   Eloquent model, never to a raw query-builder result.
 * - Confirmed to be a full duplicate of a real, live, superior webhook
 *   system this app already has at the plain (non-`Modules\`) root
 *   namespace: `App\Models\Webhook`/`WebhookDelivery`,
 *   `App\Http\Controllers\Api\WebhookController` (routed at
 *   `/api/v1/webhooks`, apiResource + redeliver + availableEvents),
 *   `App\Services\WebhookService::dispatch()` — real HMAC-SHA256 signing
 *   (`X-WideHalo-Signature` header, matching the convention already
 *   documented in `docs/09-RBAC-SECURITE/STANDARDS-SECURITE-MADAGASCAR.md`
 *   §3.3 for `IntegrationService`/`HttpActionHandler`), real delivery-
 *   history persistence, real redelivery, a real curated cross-module
 *   event catalogue. That system's own automatic trigger
 *   (`App\Traits\DispatchesWithWebhooks`) is itself adopted by zero models
 *   anywhere — a separate, real gap, entirely outside `Modules\API`'s
 *   boundary and NOT touched by this migration/chantier — but its schema
 *   and delivery mechanism are correct and worth consolidating on, unlike
 *   `api_webhooks`'s.
 *
 * Same dead-parallel-subsystem pattern already found and deleted
 * repeatedly this session (TerritoryManagementController, the Logistics
 * wh_/lgx_ subtree, Core's ApprovalService/WorkflowService in Chantier
 * 32.1, etc.) — consolidating on the real, live alternative rather than
 * fixing an insecure, non-functional duplicate in place.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('api_webhooks');
    }

    public function down(): void
    {
        // Deliberately no-op: the dropped table backed dead/insecure code
        // that has been deleted alongside this migration — there is
        // nothing left to recreate the schema for.
    }
};
