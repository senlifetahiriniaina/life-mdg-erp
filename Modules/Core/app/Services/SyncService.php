<?php

declare(strict_types=1);

namespace Modules\Core\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Core\Models\SyncQueue;

class SyncService
{
    /**
     * Apply a batch of offline mutations from a client.
     */
    public function push(int $userId, array $mutations): array
    {
        $results = ['applied' => 0, 'failed' => 0, 'conflicts' => []];

        foreach ($mutations as $mutation) {
            try {
                $this->applyMutation($userId, $mutation);
                $results['applied']++;
            } catch (\Exception $e) {
                Log::warning('Sync mutation failed', ['mutation' => $mutation, 'error' => $e->getMessage()]);
                $results['failed']++;
                $results['conflicts'][] = [
                    'id' => $mutation['id'] ?? null,
                    'error' => $e->getMessage(),
                ];
            }
        }

        return $results;
    }

    /**
     * Pull changes since the client's last sync timestamp.
     */
    public function pull(int $userId, ?string $lastSyncAt = null): array
    {
        $since = $lastSyncAt ? Carbon::parse($lastSyncAt) : Carbon::now()->subDays(7);

        return [
            'timestamp' => now()->toISOString(),
            'changes' => $this->getChangesSince($userId, $since),
        ];
    }

    private function applyMutation(int $userId, array $mutation): void
    {
        $entityType = $mutation['entity_type'] ?? throw new \InvalidArgumentException('entity_type is required');
        $operation = $mutation['operation'] ?? throw new \InvalidArgumentException('operation is required');
        $payload = $mutation['payload'] ?? [];
        $clientTimestamp = Carbon::parse($mutation['client_timestamp'] ?? now());

        // Record in sync queue for audit
        SyncQueue::create([
            'id' => $mutation['id'] ?? \Illuminate\Support\Str::uuid(),
            'user_id' => $userId,
            'entity_type' => $entityType,
            'entity_id' => $mutation['entity_id'] ?? null,
            'operation' => $operation,
            'payload' => $payload,
            'status' => 'processing',
            'client_timestamp' => $clientTimestamp,
        ]);

        // Resolve the model class
        $modelClass = $this->resolveModel($entityType);
        if (! class_exists($modelClass)) {
            throw new \RuntimeException("Unknown entity type: {$entityType}");
        }

        // Strip fields the client must never control directly
        $safePayload = $this->sanitizePayload($entityType, $payload);

        DB::transaction(function () use ($userId, $modelClass, $entityType, $operation, $safePayload, $mutation) {
            match ($operation) {
                'create' => $modelClass::create($safePayload),
                'update' => $this->authorizedUpdate($userId, $modelClass, $entityType, $mutation['entity_id'], $safePayload),
                'delete' => $this->authorizedDelete($userId, $modelClass, $entityType, $mutation['entity_id']),
                default  => throw new \InvalidArgumentException("Unknown operation: {$operation}"),
            };
        });

        SyncQueue::where('id', $mutation['id'])->update(['status' => 'applied']);
    }

    // Fields that must never be overwritten by a client-supplied payload
    private const BLOCKED_FIELDS = [
        'id', 'user_id', 'created_by', 'updated_by', 'tenant_id',
        'created_at', 'updated_at', 'deleted_at',
    ];

    private function sanitizePayload(string $entityType, array $payload): array
    {
        return array_diff_key($payload, array_flip(self::BLOCKED_FIELDS));
    }

    private function authorizedUpdate(int $userId, string $modelClass, string $entityType, mixed $entityId, array $payload): void
    {
        $ownerField = $this->ownerField($entityType);
        $record = $modelClass::findOrFail($entityId);

        if ($ownerField && (int) $record->{$ownerField} !== $userId) {
            throw new \RuntimeException("Unauthorized update on {$entityType}#{$entityId}");
        }

        $record->update($payload);
    }

    private function authorizedDelete(int $userId, string $modelClass, string $entityType, mixed $entityId): void
    {
        $ownerField = $this->ownerField($entityType);
        $record = $modelClass::findOrFail($entityId);

        if ($ownerField && (int) $record->{$ownerField} !== $userId) {
            throw new \RuntimeException("Unauthorized delete on {$entityType}#{$entityId}");
        }

        $record->delete();
    }

    // Returns the ownership column for each entity type, or null if no per-user ownership applies
    private function ownerField(string $entityType): ?string
    {
        return match ($entityType) {
            'crm_contact', 'crm_lead', 'crm_opportunity' => 'owner_id',
            'helpdesk_ticket'                             => 'reporter_id',
            'project_task'                                => 'assigned_to',
            default                                       => null,
        };
    }

    private function getChangesSince(int $userId, Carbon $since): array
    {
        // Returns recently applied sync records for the client to reconcile
        return SyncQueue::where('user_id', $userId)
            ->where('status', 'applied')
            ->where('updated_at', '>=', $since)
            ->orderBy('updated_at')
            ->limit(500)
            ->get(['entity_type', 'entity_id', 'operation', 'payload', 'updated_at'])
            ->toArray();
    }

    private function resolveModel(string $entityType): string
    {
        // Map entity_type slugs to model classes
        $map = [
            'crm_contact' => \Modules\CRM\Models\Contact::class,
            'crm_lead' => \Modules\CRM\Models\Lead::class,
            'crm_opportunity' => \Modules\CRM\Models\Opportunity::class,
            'inventory_product' => \Modules\Inventory\Models\Product::class,
            'inventory_movement' => \Modules\Inventory\Models\StockMovement::class,
            'hr_employee' => \Modules\HR\Models\Employee::class,
            'pos_order' => \Modules\POS\Models\PosOrder::class,
            'pos_order_item' => \Modules\POS\Models\PosOrderItem::class,
            'helpdesk_ticket' => \Modules\Helpdesk\Models\Ticket::class,
            'project_task' => \Modules\Projects\Models\Task::class,
        ];

        return $map[$entityType] ?? throw new \InvalidArgumentException("Unknown entity type: {$entityType}");
    }
}
