<?php

declare(strict_types=1);

namespace Modules\BI\Services;

use Modules\BI\Models\Widget;

class DrillDownService
{
    /**
     * Get drill-down data for a widget filtered by a dimension value.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function getDrillDownData(Widget $widget, array $filters): array
    {
        /** @var array<string, mixed> $config */
        $config = $widget->config ?? [];

        $dimension = $filters['dimension'] ?? null;
        $value = $filters['value'] ?? null;
        /** @var array<string, mixed> $additionalFilters */
        $additionalFilters = $filters['additional_filters'] ?? [];

        // Build a filtered data set from widget config
        $baseData = $this->getWidgetBaseData($widget);

        $filtered = array_filter($baseData, function (array $row) use ($dimension, $value): bool {
            if ($dimension === null || $value === null) {
                return true;
            }

            return isset($row[$dimension]) && (string) $row[$dimension] === (string) $value;
        });

        // Apply additional filters
        foreach ($additionalFilters as $filterKey => $filterValue) {
            $filtered = array_filter($filtered, function (array $row) use ($filterKey, $filterValue): bool {
                return isset($row[$filterKey]) && (string) $row[$filterKey] === (string) $filterValue;
            });
        }

        $filteredList = array_values($filtered);

        return [
            'data' => $filteredList,
            'dimension' => $dimension,
            'value' => $value,
            'total' => count($filteredList),
            'config' => $config,
        ];
    }

    /**
     * Return the list of dimensions a widget can be drilled into.
     *
     * @return list<string>
     */
    public function getAvailableDimensions(Widget $widget): array
    {
        /** @var array<string, mixed> $config */
        $config = $widget->config ?? [];

        /** @var list<string> $dimensions */
        $dimensions = [];

        if (isset($config['dimensions']) && is_array($config['dimensions'])) {
            /** @var list<string> $configDims */
            $configDims = $config['dimensions'];
            $dimensions = $configDims;
        } elseif (isset($config['x_axis'])) {
            $dimensions[] = (string) $config['x_axis'];
        }

        // Add time dimensions if available
        if (isset($config['date_field'])) {
            $dimensions[] = 'year';
            $dimensions[] = 'quarter';
            $dimensions[] = 'month';
        }

        return array_values(array_unique($dimensions));
    }

    /**
     * Fetch the widget's underlying data for drill-down processing.
     *
     * @return list<array<string, mixed>>
     */
    private function getWidgetBaseData(Widget $widget): array
    {
        /** @var array<string, mixed> $config */
        $config = $widget->config ?? [];

        return [];
    }
}
