<?php

declare(strict_types=1);

namespace Modules\Core\Services;

use Illuminate\Support\Facades\Cache;

/**
 * SimpleModeService — Simplicity First
 *
 * Controls which form fields and wizard steps are visible to a user depending
 * on whether they are in "simple mode" (default for new users) or "expert
 * mode".  Expert fields are shown only on explicit opt-in so that non-technical
 * users are never confronted with ERP jargon.
 */
class SimpleModeService
{
    /** Cache key prefix used to store per-user mode preference. */
    private const CACHE_PREFIX = 'simple_mode_user_';

    /** Default TTL in seconds (30 days). */
    private const CACHE_TTL = 2_592_000;

    /**
     * Fields that are considered "advanced" and hidden in simple mode,
     * keyed by lowercase module name.
     *
     * @var array<string, list<string>>
     */
    private const ADVANCED_FIELDS = [
        'sales' => [
            'discount_percent',
            'tax_rate',
            'shipping_address',
            'opportunity_id',
            'payment_terms',
            'external_reference',
            'cost_price',
        ],
        'achats' => [
            'quality_inspection_id',
            'three_way_match',
            'blanket_order_id',
            'lead_time_days',
            'incoterms',
        ],
        'hr' => [
            'contract_type_details',
            'probation_end_date',
            'secondary_bank',
            'iban',
            'social_security_number',
            'tax_withholding_rate',
        ],
        'accounting' => [
            'reconciliation_id',
            'cost_center',
            'analytical_axis',
            'journal_entry_ref',
            'exchange_rate_override',
            'deferred_revenue_schedule',
        ],
        'inventory' => [
            'reorder_point_formula',
            'safety_stock_formula',
            'abc_class',
            'shelf_life_days',
            'lot_tracking',
            'serial_tracking',
        ],
        'crm' => [
            'lead_source_detail',
            'campaign_id',
            'probability_override',
            'lifetime_value',
            'referral_code',
        ],
        'pos' => [
            'fiscal_receipt_number',
            'cash_drawer_id',
            'shift_id',
            'tip_amount',
        ],
    ];

    /**
     * Wizard steps that are skipped in simple mode, keyed by lowercase module.
     *
     * @var array<string, list<string>>
     */
    private const SKIPPED_STEPS = [
        'sales' => [
            'advanced_pricing',
            'opportunity_link',
            'custom_fields',
        ],
        'achats' => [
            'quality_inspection',
            'three_way_match_config',
            'blanket_order',
        ],
        'hr' => [
            'secondary_bank_details',
            'tax_withholding_config',
            'advanced_contract',
        ],
        'accounting' => [
            'analytical_axes',
            'cost_centers',
            'reconciliation',
        ],
        'inventory' => [
            'formula_config',
            'abc_analysis',
            'traceability',
        ],
        'setup' => [
            'advanced_mapping',
            'custom_transformations',
            'data_quality_rules',
        ],
    ];

    // ─── Field-level API ──────────────────────────────────────────────────────

    /**
     * Return all advanced (hidden-in-simple-mode) field names for a module.
     *
     * @return list<string>
     */
    public function hiddenFields(string $module): array
    {
        return self::ADVANCED_FIELDS[strtolower($module)] ?? [];
    }

    /**
     * Return the wizard steps that are skipped in simple mode for a module.
     *
     * @return list<string>
     */
    public function skippedSteps(string $module): array
    {
        return self::SKIPPED_STEPS[strtolower($module)] ?? [];
    }

    /**
     * Check whether a specific field is considered advanced for the module.
     * Non-advanced fields are always shown in both modes.
     */
    public function isAdvanced(string $module, string $field): bool
    {
        return in_array($field, $this->hiddenFields($module), true);
    }

    // ─── User mode persistence ────────────────────────────────────────────────

    /**
     * Return true when the user is in simple mode.
     * Defaults to true (simple mode) for users who have never toggled.
     */
    public function isSimpleMode(int $userId): bool
    {
        return (bool) Cache::get($this->cacheKey($userId), true);
    }

    /**
     * Persist the user's mode preference.
     *
     * @param bool $simpleMode true = simple mode, false = expert mode
     */
    public function setMode(int $userId, bool $simpleMode): void
    {
        Cache::put($this->cacheKey($userId), $simpleMode, self::CACHE_TTL);
    }

    /**
     * Return the visible (non-hidden) fields for a module in the given mode.
     * When $simpleMode is true the advanced fields are excluded.
     *
     * @param  list<string> $allFields
     * @return list<string>
     */
    public function visibleFields(string $module, array $allFields, bool $simpleMode): array
    {
        if (! $simpleMode) {
            return $allFields;
        }

        $hidden = $this->hiddenFields($module);

        return array_values(
            array_filter($allFields, fn (string $f) => ! in_array($f, $hidden, true))
        );
    }

    // ─── Internals ────────────────────────────────────────────────────────────

    private function cacheKey(int $userId): string
    {
        return self::CACHE_PREFIX . $userId;
    }
}
