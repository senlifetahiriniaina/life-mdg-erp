<?php

declare(strict_types=1);

namespace Modules\Workflow\Services\Actions;

use Illuminate\Support\Facades\Log;

/**
 * DataTransformHandler — Phase 39
 *
 * Handles data transformation workflow actions:
 * field mapping, array filtering, currency formatting, date formatting,
 * object merging, JSONPath extraction.
 *
 * These actions are pure functions — no DB writes, no side effects.
 */
class DataTransformHandler
{
    /** @var array<string,string> ISO 4217 currency symbols */
    private const CURRENCY_SYMBOLS = [
        'XOF' => 'XOF', 'XAF' => 'XAF', 'EUR' => '€',
        'USD' => '$',   'GBP' => '£',   'GHS' => '₵',
        'NGN' => '₦',   'KES' => 'KSh', 'MGA' => 'Ar',
        'CNY' => '¥',   'JPY' => '¥',   'INR' => '₹',
    ];

    /** @var array<string,string> Date locale patterns */
    private const DATE_LOCALES = [
        'fr' => 'd/m/Y',
        'en' => 'm/d/Y',
        'ar' => 'Y/m/d',
    ];

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
            'transform.map_fields'       => $this->mapFields($params, $context),
            'transform.filter_array'     => $this->filterArray($params, $context),
            'transform.format_currency'  => $this->formatCurrency($params, $context),
            'transform.format_date'      => $this->formatDate($params, $context),
            'transform.merge_objects'    => $this->mergeObjects($params, $context),
            'transform.extract_value'    => $this->extractValue($params, $context),
            default => ['status' => 'skipped', 'reason' => "Unknown Transform action: {$action}"],
        };
    }

    /**
     * action: transform.map_fields
     * Rename/remap JSON keys from source to target names.
     *
     * @param  array<string,mixed>  $params   e.g. ['mapping' => {'source_key': 'target_key', ...}, 'data_key' => 'items']
     * @param  array<string,mixed>  $context
     * @return array{mapped: array<string,mixed>, status: string}
     */
    public function mapFields(array $params, array $context): array
    {
        $mapping = $params['mapping'] ?? [];
        if (is_string($mapping)) {
            $mapping = json_decode($mapping, true) ?? [];
        }

        $dataKey = $params['data_key'] ?? null;
        $data    = $dataKey ? ($context[$dataKey] ?? $context) : $context;

        if (! is_array($data)) {
            return ['status' => 'error', 'reason' => 'Data must be an array'];
        }

        $mapped = [];
        foreach ($data as $key => $value) {
            $newKey = $mapping[$key] ?? $key;
            $mapped[$newKey] = $value;
        }

        return ['mapped' => $mapped, 'status' => 'success', 'fields_remapped' => count($mapping)];
    }

    /**
     * action: transform.filter_array
     * Filter array items by a simple key=value condition.
     *
     * @param  array<string,mixed>  $params   e.g. ['array_key' => 'items', 'filter_key' => 'status', 'filter_value' => 'active']
     * @param  array<string,mixed>  $context
     * @return array{filtered: array<mixed>, count: int, status: string}
     */
    public function filterArray(array $params, array $context): array
    {
        $arrayKey   = $params['array_key'] ?? 'items';
        $filterKey  = $params['filter_key'] ?? null;
        $filterValue = $params['filter_value'] ?? null;
        $operator   = $params['operator'] ?? '=='; // ==, !=, >, <, >=, <=

        $items = $context[$arrayKey] ?? [];

        if (! is_array($items)) {
            return ['status' => 'error', 'reason' => "Context key '{$arrayKey}' is not an array"];
        }

        if (! $filterKey) {
            return ['filtered' => $items, 'count' => count($items), 'status' => 'success'];
        }

        $filtered = array_values(array_filter($items, function ($item) use ($filterKey, $filterValue, $operator) {
            if (! is_array($item)) {
                return false;
            }
            $itemValue = $item[$filterKey] ?? null;
            return match ($operator) {
                '!='  => $itemValue != $filterValue,
                '>'   => (float) $itemValue > (float) $filterValue,
                '<'   => (float) $itemValue < (float) $filterValue,
                '>='  => (float) $itemValue >= (float) $filterValue,
                '<='  => (float) $itemValue <= (float) $filterValue,
                default => $itemValue == $filterValue,
            };
        }));

        return ['filtered' => $filtered, 'count' => count($filtered), 'status' => 'success'];
    }

    /**
     * action: transform.format_currency
     * Format an amount with its currency symbol (XOF, EUR, USD, etc.).
     *
     * @param  array<string,mixed>  $params   e.g. ['amount_key' => 'total_amount', 'currency' => 'XOF', 'locale' => 'fr']
     * @param  array<string,mixed>  $context
     * @return array{formatted: string, amount: float, currency: string, status: string}
     */
    public function formatCurrency(array $params, array $context): array
    {
        $amountKey = $params['amount_key'] ?? 'amount';
        $amount    = (float) ($params['amount'] ?? $context[$amountKey] ?? 0);
        $currency  = $params['currency'] ?? ($context['currency'] ?? 'XOF');
        $locale    = $params['locale'] ?? ($context['locale'] ?? 'fr');

        $symbol    = self::CURRENCY_SYMBOLS[$currency] ?? $currency;

        // XOF/XAF: no decimal places (integer currency)
        if (in_array($currency, ['XOF', 'XAF'], true)) {
            $formatted = number_format($amount, 0, ',', ' ') . ' ' . $symbol;
        } else {
            $formatted = $locale === 'fr'
                ? number_format($amount, 2, ',', ' ') . ' ' . $symbol
                : $symbol . number_format($amount, 2, '.', ',');
        }

        return ['formatted' => $formatted, 'amount' => $amount, 'currency' => $currency, 'status' => 'success'];
    }

    /**
     * action: transform.format_date
     * Format a date for a given locale (fr/en/ar).
     *
     * @param  array<string,mixed>  $params   e.g. ['date_key' => 'created_at', 'locale' => 'fr', 'include_time' => true]
     * @param  array<string,mixed>  $context
     * @return array{formatted_date: string, status: string}
     */
    public function formatDate(array $params, array $context): array
    {
        $dateKey     = $params['date_key'] ?? 'created_at';
        $rawDate     = $params['date'] ?? ($context[$dateKey] ?? null);
        $locale      = $params['locale'] ?? ($context['locale'] ?? 'fr');
        $includeTime = (bool) ($params['include_time'] ?? false);

        if (! $rawDate) {
            return ['formatted_date' => '', 'status' => 'error', 'reason' => "Missing date in key '{$dateKey}'"];
        }

        try {
            $timestamp   = is_numeric($rawDate) ? (int) $rawDate : strtotime($rawDate);
            $datePattern = self::DATE_LOCALES[$locale] ?? self::DATE_LOCALES['fr'];
            $pattern     = $includeTime ? $datePattern . ' H:i' : $datePattern;
            $formatted   = date($pattern, $timestamp);
        } catch (\Throwable) {
            $formatted = (string) $rawDate;
        }

        return ['formatted_date' => $formatted, 'locale' => $locale, 'status' => 'success'];
    }

    /**
     * action: transform.merge_objects
     * Merge two context sub-objects into one, second takes precedence.
     *
     * @param  array<string,mixed>  $params   e.g. ['base_key' => 'order', 'override_key' => 'updates', 'output_key' => 'merged_order']
     * @param  array<string,mixed>  $context
     * @return array{merged: array<string,mixed>, status: string}
     */
    public function mergeObjects(array $params, array $context): array
    {
        $baseKey     = $params['base_key'] ?? null;
        $overrideKey = $params['override_key'] ?? null;

        $base     = $baseKey ? ($context[$baseKey] ?? []) : $context;
        $override = $overrideKey ? ($context[$overrideKey] ?? []) : ($params['override'] ?? []);

        if (is_string($override)) {
            $override = json_decode($override, true) ?? [];
        }

        if (! is_array($base) || ! is_array($override)) {
            return ['status' => 'error', 'reason' => 'Both base and override must be arrays'];
        }

        $merged = array_merge($base, $override);

        return ['merged' => $merged, 'keys_merged' => count($override), 'status' => 'success'];
    }

    /**
     * action: transform.extract_value
     * Extract a value from the context using a simple dot-notation or JSONPath-like path.
     *
     * Supports: "$.order.items[0].amount" or simple dot notation "order.items.0.amount"
     *
     * @param  array<string,mixed>  $params   e.g. ['path' => '$.order.items[0].amount', 'output_key' => 'first_item_amount']
     * @param  array<string,mixed>  $context
     * @return array{value: mixed, path: string, found: bool, status: string}
     */
    public function extractValue(array $params, array $context): array
    {
        $path      = $params['path'] ?? null;
        $outputKey = $params['output_key'] ?? 'extracted_value';

        if (! $path) {
            return ['status' => 'error', 'reason' => 'Missing path parameter'];
        }

        // Normalize: strip leading "$." and convert [N] array notation to .N
        $normalized = ltrim($path, '$.');
        $normalized = preg_replace('/\[(\d+)\]/', '.$1', $normalized) ?? $normalized;
        $segments   = explode('.', $normalized);

        $current = $context;
        foreach ($segments as $segment) {
            if ($segment === '') {
                continue;
            }
            if (is_array($current) && array_key_exists($segment, $current)) {
                $current = $current[$segment];
            } elseif (is_numeric($segment) && is_array($current) && isset($current[(int) $segment])) {
                $current = $current[(int) $segment];
            } else {
                return ['value' => null, 'path' => $path, 'found' => false, 'status' => 'not_found'];
            }
        }

        return ['value' => $current, 'path' => $path, 'found' => true, 'output_key' => $outputKey, 'status' => 'success'];
    }
}
