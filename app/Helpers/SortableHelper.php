<?php

declare(strict_types=1);

namespace App\Helpers;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Helper for adding configurable sorting to API list endpoints
 * Usage in controller:
 *
 * public function index(Request $request)
 * {
 *     $query = Model::query();
 *     SortableHelper::apply($request, $query, ['name', 'created_at', 'updated_at']);
 *     return $query->paginate();
 * }
 */
class SortableHelper
{
    /**
     * Apply sorting to a query builder based on request parameters
     *
     * @param Request $request HTTP request
     * @param Builder $query Eloquent query builder
     * @param array<string> $allowedFields Fields that can be sorted
     * @param string $defaultField Default sort field
     * @param string $defaultOrder Default sort order (asc|desc)
     */
    public static function apply(
        Request $request,
        Builder $query,
        array $allowedFields = [],
        string $defaultField = 'created_at',
        string $defaultOrder = 'desc'
    ): Builder {
        $sortBy = $request->input('sort_by', $defaultField);
        $sortOrder = $request->input('sort_order', $defaultOrder);

        // Validate sort field
        if (empty($allowedFields) || in_array($sortBy, $allowedFields)) {
            // Sanitize sort order
            $sortOrder = strtolower($sortOrder) === 'asc' ? 'asc' : 'desc';

            return $query->orderBy($sortBy, $sortOrder);
        }

        // Fall back to default if invalid field
        return $query->orderBy($defaultField, $sortOrder);
    }

    /**
     * Get available sortable fields for documentation
     */
    public static function getFieldsForDocs(array $fields): string
    {
        return implode(', ', $fields);
    }
}
