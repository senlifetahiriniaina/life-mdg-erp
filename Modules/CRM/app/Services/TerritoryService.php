<?php

declare(strict_types=1);

namespace Modules\CRM\Services;

use Illuminate\Support\Collection;
use Modules\CRM\Models\Contact;
use Modules\CRM\Models\Territory;
use Modules\CRM\Models\TerritoryAssignment;

class TerritoryService
{
    /**
     * Auto-assign a contact to a territory based on rules JSON.
     * Rules format: [{"field": "region", "value": "West"}, ...]
     */
    public function autoAssign(Contact $contact): ?TerritoryAssignment
    {
        $territories = Territory::query()
            ->whereNotNull('rules')
            ->where('is_active', true)
            ->get();

        foreach ($territories as $territory) {
            /** @var array<int,array<string,mixed>> $rules */
            $rules = $territory->rules ?? [];
            if ($this->matchesRules($contact, $rules)) {
                return TerritoryAssignment::updateOrCreate(
                    ['territory_id' => $territory->id, 'contact_id' => $contact->id],
                    ['auto_assigned' => true]
                );
            }
        }

        return null;
    }

    /**
     * Get quota attainment for all territories grouped by team.
     *
     * @return array<int,array<string,mixed>>
     */
    public function getTeamQuotas(): array
    {
        $territories = Territory::query()
            ->with(['assignedTo', 'opportunities'])
            ->where('is_active', true)
            ->get();

        return $territories->map(function (Territory $territory): array {
            return [
                'territory_id' => $territory->id,
                'territory_name' => $territory->name,
                'owner' => $territory->assignedTo?->name ?? 'Unassigned',
                'quota' => (float) $territory->sales_target,
                'ytd_revenue' => $territory->ytdRevenue(),
                'attainment_pct' => $territory->quotaAttainment(),
                'forecast_revenue' => $territory->forecastedRevenue(),
                'contacts_count' => TerritoryAssignment::where('territory_id', $territory->id)->count(),
            ];
        })->toArray();
    }

    /**
     * Rebalance territories by evenly distributing contacts.
     *
     * @return array<string,mixed>
     */
    public function rebalance(): array
    {
        $territories = Territory::where('is_active', true)->get();
        $assignments = TerritoryAssignment::whereNotNull('contact_id')->get();

        if ($territories->isEmpty()) {
            return ['rebalanced' => 0, 'territories' => []];
        }

        $perTerritory = (int) ceil($assignments->count() / $territories->count());
        $rebalanced = 0;

        $chunks = $assignments->chunk($perTerritory);

        foreach ($territories as $index => $territory) {
            /** @var Collection<int, TerritoryAssignment> $chunk */
            $chunk = $chunks->get($index, collect());
            foreach ($chunk as $assignment) {
                if ($assignment->territory_id !== $territory->id) {
                    $assignment->update(['territory_id' => $territory->id, 'auto_assigned' => true]);
                    $rebalanced++;
                }
            }
        }

        return [
            'rebalanced' => $rebalanced,
            'territories' => $this->getTeamQuotas(),
        ];
    }

    /**
     * Coverage summary: how many active territories have at least one
     * assigned account/contact, and which don't (gaps).
     *
     * @return array<string,mixed>
     */
    public function coverage(): array
    {
        $territories = Territory::where('is_active', true)->get();
        $total = $territories->count();

        $assignedIds = TerritoryAssignment::whereIn('territory_id', $territories->pluck('id'))
            ->distinct()
            ->pluck('territory_id');

        $assigned = $assignedIds->count();
        $gaps = $territories->whereNotIn('id', $assignedIds)->values();

        return [
            'total' => $total,
            'assigned' => $assigned,
            'unassigned' => $total - $assigned,
            'percentage' => $total > 0 ? round(($assigned / $total) * 100, 2) : 0.0,
            'gaps' => $gaps->map(fn (Territory $t) => [
                'id' => $t->id,
                'name' => $t->name,
                'code' => $t->code,
            ])->toArray(),
        ];
    }

    /**
     * Check if a contact matches the rules JSON for a territory.
     *
     * @param  array<int,array<string,mixed>>  $rules
     */
    private function matchesRules(Contact $contact, array $rules): bool
    {
        if (empty($rules)) {
            return false;
        }

        foreach ($rules as $rule) {
            $field = (string) ($rule['field'] ?? '');
            $value = $rule['value'] ?? null;

            if ($field === '' || $value === null) {
                continue;
            }

            /** @var mixed $contactValue */
            $contactValue = $contact->getAttribute($field);
            if ($contactValue === null || strtolower((string) $contactValue) !== strtolower((string) $value)) {
                return false;
            }
        }

        return true;
    }
}
