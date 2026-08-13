<?php

declare(strict_types=1);

namespace App\Services;

use Meilisearch\Client;
use Meilisearch\Endpoints\Indexes;

class SearchService
{
    protected Client $client;
    protected array $indexes = [
        'crm_contacts' => [
            'searchableAttributes' => ['first_name', 'last_name', 'email', 'phone'],
            'filterableAttributes' => ['status', 'account_id', 'tenant_id'],
            'sortableAttributes' => ['created_at', 'updated_at'],
        ],
        'crm_accounts' => [
            'searchableAttributes' => ['name', 'email', 'industry', 'website'],
            'filterableAttributes' => ['type', 'status', 'tenant_id'],
            'sortableAttributes' => ['revenue', 'created_at'],
        ],
        'crm_opportunities' => [
            'searchableAttributes' => ['name', 'description'],
            'filterableAttributes' => ['stage', 'status', 'owner_id', 'account_id', 'tenant_id'],
            'sortableAttributes' => ['amount', 'probability', 'expected_close_date'],
        ],
        'inventory_products' => [
            'searchableAttributes' => ['name', 'sku', 'description', 'category'],
            'filterableAttributes' => ['status', 'category', 'tenant_id'],
            'sortableAttributes' => ['cost_price', 'selling_price'],
        ],
        'accounting_invoices' => [
            'searchableAttributes' => ['invoice_number', 'customer_id'],
            'filterableAttributes' => ['status', 'tenant_id'],
            'sortableAttributes' => ['total', 'invoice_date'],
        ],
        'hr_employees' => [
            'searchableAttributes' => ['name', 'email', 'job_title', 'department'],
            'filterableAttributes' => ['status', 'department', 'tenant_id'],
            'sortableAttributes' => ['created_at'],
        ],
    ];

    public function __construct()
    {
        $this->client = new Client(
            config('meilisearch.host'),
            config('meilisearch.key')
        );
        $this->initializeIndexes();
    }

    protected function initializeIndexes(): void
    {
        foreach ($this->indexes as $indexName => $config) {
            try {
                $index = $this->client->getIndex($indexName);
            } catch (\Exception $e) {
                $index = $this->client->createIndex($indexName);
            }

            $index->updateSettings([
                'searchableAttributes' => $config['searchableAttributes'],
                'filterableAttributes' => $config['filterableAttributes'],
                'sortableAttributes' => $config['sortableAttributes'],
            ]);
        }
    }

    public function index(string $indexName, array $documents): void
    {
        if (!isset($this->indexes[$indexName])) {
            throw new \InvalidArgumentException("Index {$indexName} not configured");
        }

        $index = $this->client->getIndex($indexName);
        $index->addDocuments($documents);
    }

    public function delete(string $indexName, int|string $documentId): void
    {
        $index = $this->client->getIndex($indexName);
        $index->deleteDocument($documentId);
    }

    public function deleteAll(string $indexName): void
    {
        $index = $this->client->getIndex($indexName);
        $index->deleteAllDocuments();
    }

    public function search(
        string $indexName,
        string $query,
        array $filters = [],
        int $limit = 20,
        int $offset = 0
    ): array {
        $index = $this->client->getIndex($indexName);

        $options = [
            'limit' => $limit,
            'offset' => $offset,
        ];

        if (!empty($filters)) {
            $filterString = $this->buildFilterString($filters);
            $options['filter'] = $filterString;
        }

        return $index->search($query, $options);
    }

    public function globalSearch(
        string $query,
        int $tenantId,
        array $indexNames = [],
        int $limit = 10
    ): array {
        $results = [];
        $indexesToSearch = empty($indexNames) ? array_keys($this->indexes) : $indexNames;

        foreach ($indexesToSearch as $indexName) {
            try {
                $searchResults = $this->search(
                    $indexName,
                    $query,
                    ['tenant_id' => $tenantId],
                    $limit
                );

                $results[$indexName] = $searchResults['hits'] ?? [];
            } catch (\Exception $e) {
                \Log::warning("Search error in {$indexName}: {$e->getMessage()}");
            }
        }

        return $results;
    }

    public function facetedSearch(
        string $indexName,
        string $query,
        array $filters = [],
        array $facets = [],
        int $limit = 20
    ): array {
        $index = $this->client->getIndex($indexName);

        $options = [
            'limit' => $limit,
            'facets' => $facets,
        ];

        if (!empty($filters)) {
            $options['filter'] = $this->buildFilterString($filters);
        }

        return $index->search($query, $options);
    }

    protected function buildFilterString(array $filters): array|string
    {
        $parts = [];

        foreach ($filters as $key => $value) {
            if (is_array($value)) {
                // OR condition: field IN [val1, val2, ...]
                $inValues = implode(', ', array_map(fn($v) => "\"{$v}\"", $value));
                $parts[] = "{$key} IN [{$inValues}]";
            } else {
                // AND condition: field = value
                if (is_string($value)) {
                    $parts[] = "{$key} = \"{$value}\"";
                } else {
                    $parts[] = "{$key} = {$value}";
                }
            }
        }

        return implode(' AND ', $parts);
    }

    public function getStats(string $indexName): array
    {
        $index = $this->client->getIndex($indexName);
        return $index->getStats();
    }

    public function health(): bool
    {
        try {
            $this->client->health();
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }
}
