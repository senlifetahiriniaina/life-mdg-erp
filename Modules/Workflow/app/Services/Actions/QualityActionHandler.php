<?php

declare(strict_types=1);

namespace Modules\Workflow\Services\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * QualityActionHandler — Phase 39
 *
 * Handles workflow actions for the Quality module:
 * inspections, production lot quarantine, supplier certifications, ISO compliance updates.
 */
class QualityActionHandler
{
    /**
     * Dispatch an action by its dot-notation suffix.
     *
     * @param  array<string,mixed>  $params
     * @param  array<string,mixed>  $context
     * @return array<string,mixed>
     */
    public function dispatch(string $action, array $params, array $context): array
    {
        return match ($action) {
            'quality.create_inspection'              => $this->createInspection($params, $context),
            'quality.block_production_lot'           => $this->blockProductionLot($params, $context),
            'quality.request_supplier_certification' => $this->requestSupplierCertification($params, $context),
            'quality.update_iso_compliance'          => $this->updateIsoCompliance($params, $context),
            default => ['status' => 'skipped', 'reason' => "Unknown Quality action: {$action}"],
        };
    }

    /**
     * action: quality.create_inspection
     * Trigger a quality inspection for a production lot or received goods.
     *
     * @param  array<string,mixed>  $params   e.g. ['inspection_type' => 'incoming', 'inspector_id' => 5]
     * @param  array<string,mixed>  $context
     * @return array{inspection_id: int|null, status: string}
     */
    public function createInspection(array $params, array $context): array
    {
        $tenantId       = $context['tenant_id'] ?? 1;
        $lotId          = $context['lot_id'] ?? $context['production_lot_id'] ?? null;
        $productId      = $context['product_id'] ?? null;
        $inspectionType = $params['inspection_type'] ?? 'standard'; // incoming | in_process | final | standard
        $inspectorId    = $params['inspector_id'] ?? null;
        $scheduledAt    = $params['scheduled_at'] ?? now()->addHours(4)->toDateTimeString();

        try {
            $inspectionId = DB::table('quality_inspections')->insertGetId([
                'tenant_id'       => $tenantId,
                'lot_id'          => $lotId,
                'product_id'      => $productId,
                'inspection_type' => $inspectionType,
                'inspector_id'    => $inspectorId,
                'scheduled_at'    => $scheduledAt,
                'status'          => 'scheduled',
                'source'          => 'workflow_automation',
                'created_at'      => now(),
                'updated_at'      => now(),
            ]);

            Log::info('WorkflowAction: quality inspection created', [
                'inspection_id' => $inspectionId,
                'lot_id'        => $lotId,
            ]);

            return ['inspection_id' => $inspectionId, 'status' => 'scheduled', 'inspection_type' => $inspectionType];
        } catch (\Throwable $e) {
            Log::warning('WorkflowAction: createInspection skipped', ['error' => $e->getMessage()]);
            return ['inspection_id' => null, 'status' => 'simulated', 'inspection_type' => $inspectionType];
        }
    }

    /**
     * action: quality.block_production_lot
     * Quarantine a production lot pending quality resolution.
     *
     * @param  array<string,mixed>  $params   e.g. ['reason' => 'defect_detected', 'quarantine_location' => 'Q-01']
     * @param  array<string,mixed>  $context
     * @return array{blocked: bool, lot_id: int|null}
     */
    public function blockProductionLot(array $params, array $context): array
    {
        $lotId     = $context['lot_id'] ?? $context['production_lot_id'] ?? null;
        $tenantId  = $context['tenant_id'] ?? 1;
        $reason    = $params['reason'] ?? 'quality_hold';
        $location  = $params['quarantine_location'] ?? 'quarantine';

        if (! $lotId) {
            return ['status' => 'error', 'reason' => 'Missing lot_id in context'];
        }

        try {
            $rows = DB::table('production_lots')
                ->where('id', $lotId)
                ->where('tenant_id', $tenantId)
                ->update([
                    'status'              => 'quarantined',
                    'quarantine_reason'   => $reason,
                    'quarantine_location' => $location,
                    'quarantined_at'      => now(),
                    'updated_at'          => now(),
                ]);

            Log::info('WorkflowAction: production lot blocked', ['lot_id' => $lotId, 'reason' => $reason]);

            return ['blocked' => $rows > 0, 'lot_id' => $lotId, 'reason' => $reason, 'location' => $location];
        } catch (\Throwable $e) {
            Log::warning('WorkflowAction: blockProductionLot skipped', ['error' => $e->getMessage()]);
            return ['blocked' => false, 'lot_id' => $lotId, 'status' => 'simulated'];
        }
    }

    /**
     * action: quality.request_supplier_certification
     * Automatically request a certification document from a supplier.
     *
     * @param  array<string,mixed>  $params   e.g. ['certification_type' => 'ISO_9001', 'deadline_days' => 14]
     * @param  array<string,mixed>  $context
     * @return array{request_id: int|null, status: string}
     */
    public function requestSupplierCertification(array $params, array $context): array
    {
        $supplierId       = $context['supplier_id'] ?? $params['supplier_id'] ?? null;
        $tenantId         = $context['tenant_id'] ?? 1;
        $certificationType = $params['certification_type'] ?? 'ISO_9001';
        $deadlineDays     = (int) ($params['deadline_days'] ?? 30);
        $deadline         = now()->addDays($deadlineDays)->toDateString();

        if (! $supplierId) {
            return ['status' => 'error', 'reason' => 'Missing supplier_id'];
        }

        try {
            $requestId = DB::table('supplier_certification_requests')->insertGetId([
                'tenant_id'          => $tenantId,
                'supplier_id'        => $supplierId,
                'certification_type' => $certificationType,
                'deadline'           => $deadline,
                'status'             => 'requested',
                'source'             => 'workflow_automation',
                'created_at'         => now(),
                'updated_at'         => now(),
            ]);

            return [
                'request_id'         => $requestId,
                'status'             => 'requested',
                'certification_type' => $certificationType,
                'deadline'           => $deadline,
            ];
        } catch (\Throwable $e) {
            Log::warning('WorkflowAction: requestSupplierCertification skipped', ['error' => $e->getMessage()]);
            return ['request_id' => null, 'status' => 'simulated', 'certification_type' => $certificationType];
        }
    }

    /**
     * action: quality.update_iso_compliance
     * Update the ISO compliance record for a product or supplier.
     *
     * @param  array<string,mixed>  $params   e.g. ['iso_standard' => 'ISO_105', 'compliance_status' => 'compliant']
     * @param  array<string,mixed>  $context
     * @return array{updated: bool}
     */
    public function updateIsoCompliance(array $params, array $context): array
    {
        $tenantId         = $context['tenant_id'] ?? 1;
        $subjectId        = $context['product_id'] ?? $context['supplier_id'] ?? null;
        $subjectType      = isset($context['product_id']) ? 'product' : 'supplier';
        $isoStandard      = $params['iso_standard'] ?? 'ISO_9001';
        $complianceStatus = $params['compliance_status'] ?? 'compliant'; // compliant | non_compliant | pending
        $validUntil       = $params['valid_until'] ?? now()->addYear()->toDateString();

        if (! $subjectId) {
            return ['status' => 'error', 'reason' => 'Missing product_id or supplier_id in context'];
        }

        try {
            $existing = DB::table('iso_compliance_records')
                ->where('tenant_id', $tenantId)
                ->where('subject_id', $subjectId)
                ->where('subject_type', $subjectType)
                ->where('iso_standard', $isoStandard)
                ->first();

            if ($existing) {
                DB::table('iso_compliance_records')
                    ->where('id', $existing->id)
                    ->update([
                        'compliance_status' => $complianceStatus,
                        'valid_until'       => $validUntil,
                        'updated_at'        => now(),
                    ]);
            } else {
                DB::table('iso_compliance_records')->insert([
                    'tenant_id'         => $tenantId,
                    'subject_id'        => $subjectId,
                    'subject_type'      => $subjectType,
                    'iso_standard'      => $isoStandard,
                    'compliance_status' => $complianceStatus,
                    'valid_until'       => $validUntil,
                    'source'            => 'workflow_automation',
                    'created_at'        => now(),
                    'updated_at'        => now(),
                ]);
            }

            return [
                'updated'           => true,
                'iso_standard'      => $isoStandard,
                'compliance_status' => $complianceStatus,
            ];
        } catch (\Throwable $e) {
            Log::warning('WorkflowAction: updateIsoCompliance skipped', ['error' => $e->getMessage()]);
            return ['updated' => false, 'iso_standard' => $isoStandard, 'status' => 'simulated'];
        }
    }
}
