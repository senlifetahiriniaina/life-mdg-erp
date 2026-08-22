<?php

declare(strict_types=1);

namespace Modules\Strategy\Services;

use Modules\Strategy\Models\StrategyObjective;
use Modules\Strategy\Models\StrategyObjectiveLink;

/**
 * Builds the full OKR alignment cascade map with RAG status and linked ratio data.
 */
class AlignmentCascadeService
{
    public function __construct(
        private readonly StrategyRatioService $ratioService,
    ) {}

    /**
     * Get the full cascade map for a tenant.
     *
     * Returns the OKR tree with linked ratio data and aggregate stats.
     *
     * Chantier 32.27 (audit 14 couches — layer 6, sécurité approfondie):
     * $tenantId was already threaded in from CascadeController but never
     * actually applied to this query — confirmed empirically (2 real
     * companies, 2 real objectives) that every company's cascade map showed
     * every OTHER company's real strategic objectives mixed in, a live
     * cross-tenant leak on the one page this module's own Chantier 8.5ars
     * changelog entry called "already real and complete". StrategyObjective
     * has no tenant_id column of its own — filtered via its plan's
     * tenant_id, the same relation objectiveInTenant() uses elsewhere in
     * this module.
     */
    public function getCascadeMap(string $tenantId = 'default'): array
    {
        $objectives = StrategyObjective::with(['keyResults', 'links', 'plan'])
            ->whereHas('plan', fn ($q) => $q->where('tenant_id', $tenantId))
            ->get()
            ->all();

        // Pre-load all ratios keyed by "Module:key" for quick lookup
        $allRatios = $this->buildRatioIndex($tenantId);

        $nodes = array_map(
            fn (StrategyObjective $obj) => $this->buildNode($obj, $allRatios),
            $objectives
        );

        $tree  = $this->buildTree($nodes);
        $stats = $this->computeStats($nodes);

        return [
            'nodes' => $tree,
            'stats' => $stats,
        ];
    }

    // ─── Private helpers ──────────────────────────────────────────────────────

    /**
     * Build a flat node array from a StrategyObjective model.
     *
     * @param  array<string, array<int, array<string, mixed>>>  $ratioIndex
     * @return array<string, mixed>
     */
    private function buildNode(StrategyObjective $obj, array $ratioIndex): array
    {
        $progress = (float) ($obj->progress ?? 0);
        $rag      = $this->computeRag($obj);
        $status   = $this->computeStatus($obj);

        $linkedRatios = $this->resolveLinkedRatios($obj, $ratioIndex);

        return [
            'id'            => $obj->id,
            'title'         => $obj->title ?? '',
            'level'         => $obj->level ?? 'company',
            'status'        => $status,
            'rag'           => $rag,
            'progress'      => $progress,
            'parent_id'     => $obj->parent_id,
            'children'      => [],   // populated by buildTree()
            'linked_ratios' => $linkedRatios,
        ];
    }

    /**
     * Compute RAG colour from objective progress and status field.
     */
    private function computeRag(StrategyObjective $obj): string
    {
        // Status field takes precedence when unambiguous
        $statusField = $obj->status ?? '';
        if ($statusField === 'on_track') {
            return 'green';
        }
        if ($statusField === 'at_risk') {
            return 'amber';
        }
        if (in_array($statusField, ['behind', 'cancelled'], true)) {
            return 'red';
        }

        // Fall back to progress-based thresholds
        $progress = (float) ($obj->progress ?? 0);
        if ($progress >= 70) {
            return 'green';
        }
        if ($progress >= 40) {
            return 'amber';
        }
        return 'red';
    }

    /**
     * Map objective status to canonical status string.
     */
    private function computeStatus(StrategyObjective $obj): string
    {
        $statusField = $obj->status ?? '';

        return match ($statusField) {
            'on_track'                     => 'on_track',
            'at_risk'                      => 'at_risk',
            'behind', 'cancelled'          => 'behind',
            'draft', 'not_started', ''     => 'not_started',
            default                        => $this->statusFromProgress((float) ($obj->progress ?? 0)),
        };
    }

    private function statusFromProgress(float $progress): string
    {
        if ($progress >= 70) {
            return 'on_track';
        }
        if ($progress >= 40) {
            return 'at_risk';
        }
        if ($progress > 0) {
            return 'behind';
        }
        return 'not_started';
    }

    /**
     * Resolve linked ratio data for an objective.
     *
     * Strategy:
     *  1. Load explicit links via StrategyObjectiveLink (linkable_type contains module/ratio key).
     *  2. If none, infer from key results' data_source_module + data_source_key.
     *
     * @param  array<string, array<int, array<string, mixed>>>  $ratioIndex
     * @return array<int, array<string, mixed>>
     */
    private function resolveLinkedRatios(StrategyObjective $obj, array $ratioIndex): array
    {
        $result = [];

        // --- Strategy 1: explicit objective links ---
        if ($obj->relationLoaded('links') && $obj->links->isNotEmpty()) {
            foreach ($obj->links as $link) {
                // linkable_type format: "Strategy/Ratio" or "Module:ratio_key"
                $key = str_replace('/', ':', $link->linkable_type ?? '');
                if (isset($ratioIndex[$key])) {
                    $ratio    = $ratioIndex[$key];
                    $result[] = [
                        'name'   => $ratio['name'],
                        'value'  => $ratio['current_value'] ?? null,
                        'unit'   => $ratio['unit'] ?? '',
                        'status' => $ratio['status'] ?? 'amber',
                    ];
                }
            }
            if ($result !== []) {
                return $result;
            }
        }

        // --- Strategy 2: key results' data source ---
        if ($obj->relationLoaded('keyResults')) {
            foreach ($obj->keyResults as $kr) {
                $module = $kr->data_source_module ?? '';
                $key    = $kr->data_source_key    ?? '';
                if ($module === '' || $key === '') {
                    continue;
                }
                $ratioKey = "{$module}:{$key}";
                if (isset($ratioIndex[$ratioKey])) {
                    $ratio    = $ratioIndex[$ratioKey];
                    $result[] = [
                        'name'   => $ratio['name'],
                        'value'  => $ratio['current_value'] ?? null,
                        'unit'   => $ratio['unit'] ?? '',
                        'status' => $ratio['status'] ?? 'amber',
                    ];
                }
            }
        }

        return $result;
    }

    /**
     * Build a ratio index keyed by "Module:ratio_key" for O(1) lookups.
     *
     * @return array<string, array<string, mixed>>
     */
    private function buildRatioIndex(string $tenantId): array
    {
        $index = [];
        $all   = $this->ratioService->allRatiosWithStatus($tenantId);

        foreach ($all as $module => $ratios) {
            foreach ($ratios as $ratio) {
                $key         = "{$module}:{$ratio['key']}";
                $index[$key] = $ratio;
            }
        }

        return $index;
    }

    /**
     * Build recursive tree from flat node list.
     * Roots are nodes with parent_id = null.
     *
     * @param  array<int, array<string, mixed>>  $nodes
     * @return array<int, array<string, mixed>>
     */
    private function buildTree(array $nodes, ?int $parentId = null): array
    {
        $tree = [];

        foreach ($nodes as $node) {
            if ($node['parent_id'] === $parentId) {
                $node['children'] = $this->buildTree($nodes, (int) $node['id']);
                $tree[]           = $node;
            }
        }

        return $tree;
    }

    /**
     * Compute aggregate stats from flat node list.
     *
     * @param  array<int, array<string, mixed>>  $nodes
     * @return array{total: int, on_track: int, at_risk: int, behind: int}
     */
    private function computeStats(array $nodes): array
    {
        $stats = [
            'total'     => count($nodes),
            'on_track'  => 0,
            'at_risk'   => 0,
            'behind'    => 0,
        ];

        foreach ($nodes as $node) {
            match ($node['status']) {
                'on_track'   => $stats['on_track']++,
                'at_risk'    => $stats['at_risk']++,
                'behind'     => $stats['behind']++,
                default      => null,
            };
        }

        return $stats;
    }
}
