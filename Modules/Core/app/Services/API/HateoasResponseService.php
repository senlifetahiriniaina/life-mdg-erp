<?php

declare(strict_types=1);

namespace Modules\Core\Services\API;

use Illuminate\Pagination\Paginator;

class HateoasResponseService
{
    /**
     * Format a single resource with HATEOAS links
     */
    public function formatResource(
        array $resource,
        string $resourceType,
        int $id,
        array $allowedActions = []
    ): array {
        $links = $this->generateLinks($resourceType, $id, $allowedActions);

        return array_merge($resource, [
            '_links' => $links,
            '_type' => $resourceType,
        ]);
    }

    /**
     * Format a collection of resources with pagination links
     */
    public function formatCollection(
        array $items,
        string $resourceType,
        ?Paginator $paginator = null,
        array $filters = []
    ): array {
        $formattedItems = array_map(
            fn($item) => $this->formatResource($item, $resourceType, $item['id'] ?? null),
            $items
        );

        $response = [
            'data' => $formattedItems,
            '_type' => "collection<{$resourceType}>",
            '_links' => $this->generateCollectionLinks($resourceType, $paginator, $filters),
        ];

        if ($paginator) {
            $response['pagination'] = $this->formatPagination($paginator);
        }

        return $response;
    }

    /**
     * Format pagination data with links
     */
    public function formatPagination(Paginator $paginator): array
    {
        return [
            'total' => $paginator->total(),
            'per_page' => $paginator->perPage(),
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'from' => $paginator->firstItem(),
            'to' => $paginator->lastItem(),
            '_links' => [
                'first' => $paginator->url(1),
                'last' => $paginator->url($paginator->lastPage()),
                'prev' => $paginator->previousPageUrl(),
                'next' => $paginator->nextPageUrl(),
            ],
        ];
    }

    /**
     * Generate links for a single resource
     */
    private function generateLinks(string $resourceType, ?int $id, array $allowedActions = []): array
    {
        $links = [
            'self' => [
                'href' => "/api/v1/{$this->resourceTypeToPath($resourceType)}/{$id}",
                'method' => 'GET',
                'rel' => 'self',
            ],
        ];

        if (in_array('update', $allowedActions) || empty($allowedActions)) {
            $links['update'] = [
                'href' => "/api/v1/{$this->resourceTypeToPath($resourceType)}/{$id}",
                'method' => 'PUT',
                'rel' => 'update',
            ];
        }

        if (in_array('delete', $allowedActions) || empty($allowedActions)) {
            $links['delete'] = [
                'href' => "/api/v1/{$this->resourceTypeToPath($resourceType)}/{$id}",
                'method' => 'DELETE',
                'rel' => 'delete',
            ];
        }

        // Add resource-specific links
        $links = array_merge($links, $this->getResourceSpecificLinks($resourceType, $id));

        return $links;
    }

    /**
     * Generate links for a collection
     */
    private function generateCollectionLinks(
        string $resourceType,
        ?Paginator $paginator = null,
        array $filters = []
    ): array {
        $path = $this->resourceTypeToPath($resourceType);

        $links = [
            'self' => [
                'href' => "/api/v1/{$path}",
                'method' => 'GET',
                'rel' => 'self',
            ],
            'create' => [
                'href' => "/api/v1/{$path}",
                'method' => 'POST',
                'rel' => 'create',
            ],
        ];

        if ($paginator) {
            if ($paginator->onFirstPage()) {
                unset($links['first'], $links['prev']);
            }

            if (!$paginator->hasMorePages()) {
                unset($links['next'], $links['last']);
            }
        }

        return $links;
    }

    /**
     * Get resource-specific links
     */
    private function getResourceSpecificLinks(string $resourceType, ?int $id): array
    {
        if (!$id) {
            return [];
        }

        return match ($resourceType) {
            'crm.contact' => [
                'activities' => [
                    'href' => "/api/v1/crm/contacts/{$id}/activities",
                    'method' => 'GET',
                    'rel' => 'activities',
                ],
                'opportunities' => [
                    'href' => "/api/v1/crm/contacts/{$id}/opportunities",
                    'method' => 'GET',
                    'rel' => 'opportunities',
                ],
                'duplicate_detection' => [
                    'href' => "/api/v1/crm/contacts/{$id}/detect-duplicates",
                    'method' => 'GET',
                    'rel' => 'duplicate-detection',
                ],
            ],
            'inventory.product' => [
                'stock' => [
                    'href' => "/api/v1/inventory/products/{$id}/stock",
                    'method' => 'GET',
                    'rel' => 'stock',
                ],
                'movements' => [
                    'href' => "/api/v1/inventory/products/{$id}/movements",
                    'method' => 'GET',
                    'rel' => 'movements',
                ],
                'forecast' => [
                    'href' => "/api/v1/inventory/products/{$id}/forecast",
                    'method' => 'GET',
                    'rel' => 'forecast',
                ],
            ],
            'accounting.invoice' => [
                'payments' => [
                    'href' => "/api/v1/accounting/invoices/{$id}/payments",
                    'method' => 'GET',
                    'rel' => 'payments',
                ],
                'send' => [
                    'href' => "/api/v1/accounting/invoices/{$id}/send",
                    'method' => 'POST',
                    'rel' => 'send',
                ],
                'print' => [
                    'href' => "/api/v1/accounting/invoices/{$id}/print",
                    'method' => 'GET',
                    'rel' => 'print',
                ],
            ],
            default => [],
        };
    }

    /**
     * Convert resource type to API path
     */
    private function resourceTypeToPath(string $resourceType): string
    {
        [$module, $resource] = explode('.', $resourceType);

        return "{$module}/" . str_replace('_', '-', $resource) . 's';
    }

    /**
     * Generate options for HATEOAS (allowed methods for a resource)
     */
    public function generateOptions(string $resourceType, ?int $id = null): array
    {
        $methods = ['GET'];

        if ($id) {
            $methods[] = 'PUT';
            $methods[] = 'DELETE';
        } else {
            $methods[] = 'POST';
        }

        return [
            'methods' => $methods,
            'resource_type' => $resourceType,
            'uri' => "/api/v1/{$this->resourceTypeToPath($resourceType)}" . ($id ? "/{$id}" : ''),
        ];
    }
}
