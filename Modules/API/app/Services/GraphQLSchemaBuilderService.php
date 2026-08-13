<?php

namespace Modules\API\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Collection;

class GraphQLSchemaBuilderService
{
    const CACHE_TTL = 86400;
    const SCALAR_TYPES = ['String', 'Int', 'Float', 'Boolean', 'ID', 'DateTime', 'JSON'];

    /**
     * Generate GraphQL schema from model
     */
    public function generateSchema(string $modelName, array $fields, array $relations = []): array
    {
        $schemaId = uniqid('schema_');

        $schema = [
            'id' => $schemaId,
            'name' => $modelName,
            'fields' => $this->buildFields($fields),
            'relations' => $this->buildRelations($relations),
            'queries' => $this->buildQueries($modelName),
            'mutations' => $this->buildMutations($modelName),
            'created_at' => now()->toIso8601String(),
        ];

        Cache::put("graphql:schema:{$schemaId}", $schema, now()->addDays(365));

        return [
            'schema_id' => $schemaId,
            'model' => $modelName,
            'status' => 'generated',
        ];
    }

    /**
     * Build field definitions
     */
    private function buildFields(array $fields): array
    {
        $builtFields = [];

        foreach ($fields as $fieldName => $fieldConfig) {
            $builtFields[$fieldName] = [
                'name' => $fieldName,
                'type' => $fieldConfig['type'] ?? 'String',
                'nullable' => $fieldConfig['nullable'] ?? false,
                'list' => $fieldConfig['list'] ?? false,
                'description' => $fieldConfig['description'] ?? '',
                'default' => $fieldConfig['default'] ?? null,
            ];
        }

        return $builtFields;
    }

    /**
     * Build relation definitions
     */
    private function buildRelations(array $relations): array
    {
        $builtRelations = [];

        foreach ($relations as $relationName => $relationConfig) {
            $builtRelations[$relationName] = [
                'name' => $relationName,
                'type' => $relationConfig['type'], // one, many, morph
                'model' => $relationConfig['model'],
                'through' => $relationConfig['through'] ?? null,
                'eager_load' => $relationConfig['eager_load'] ?? true,
            ];
        }

        return $builtRelations;
    }

    /**
     * Build query definitions
     */
    private function buildQueries(string $modelName): array
    {
        $camelCase = lcfirst($modelName);
        $plural = $camelCase . 's';

        return [
            $camelCase => [
                'name' => $camelCase,
                'description' => "Get single {$modelName} by ID",
                'args' => ['id' => ['type' => 'ID!', 'description' => 'The ID']],
                'return_type' => $modelName,
            ],
            $plural => [
                'name' => $plural,
                'description' => "Get all {$modelName} records",
                'args' => [
                    'first' => ['type' => 'Int', 'description' => 'Number of records'],
                    'after' => ['type' => 'String', 'description' => 'Cursor for pagination'],
                    'filter' => ['type' => 'JSON', 'description' => 'Filter conditions'],
                    'sort' => ['type' => 'String', 'description' => 'Sort field'],
                ],
                'return_type' => "{$modelName}Connection",
            ],
            "{$camelCase}Search" => [
                'name' => "{$camelCase}Search",
                'description' => "Search {$modelName} records",
                'args' => [
                    'query' => ['type' => 'String!', 'description' => 'Search query'],
                    'limit' => ['type' => 'Int', 'description' => 'Result limit'],
                ],
                'return_type' => "[{$modelName}]",
            ],
        ];
    }

    /**
     * Build mutation definitions
     */
    private function buildMutations(string $modelName): array
    {
        $camelCase = lcfirst($modelName);

        return [
            "create{$modelName}" => [
                'name' => "create{$modelName}",
                'description' => "Create new {$modelName}",
                'args' => ['input' => ['type' => "{$modelName}Input!", 'description' => 'Input data']],
                'return_type' => $modelName,
            ],
            "update{$modelName}" => [
                'name' => "update{$modelName}",
                'description' => "Update {$modelName}",
                'args' => [
                    'id' => ['type' => 'ID!', 'description' => 'The ID'],
                    'input' => ['type' => "{$modelName}Input!", 'description' => 'Updated data'],
                ],
                'return_type' => $modelName,
            ],
            "delete{$modelName}" => [
                'name' => "delete{$modelName}",
                'description' => "Delete {$modelName}",
                'args' => ['id' => ['type' => 'ID!', 'description' => 'The ID']],
                'return_type' => 'Boolean',
            ],
        ];
    }

    /**
     * Generate input type
     */
    public function generateInputType(string $modelName, array $fields): array
    {
        $inputTypeName = "{$modelName}Input";

        $inputFields = [];

        foreach ($fields as $fieldName => $fieldConfig) {
            if ($fieldConfig['input'] ?? true) {
                $inputFields[$fieldName] = [
                    'name' => $fieldName,
                    'type' => $fieldConfig['type'] ?? 'String',
                    'nullable' => $fieldConfig['nullable'] ?? true,
                ];
            }
        }

        return [
            'name' => $inputTypeName,
            'fields' => $inputFields,
        ];
    }

    /**
     * Generate connection type (for pagination)
     */
    public function generateConnectionType(string $modelName): array
    {
        $connectionName = "{$modelName}Connection";
        $edgeName = "{$modelName}Edge";

        return [
            'connection' => [
                'name' => $connectionName,
                'fields' => [
                    'edges' => ['type' => "[{$edgeName}]"],
                    'pageInfo' => ['type' => 'PageInfo!'],
                    'totalCount' => ['type' => 'Int!'],
                ],
            ],
            'edge' => [
                'name' => $edgeName,
                'fields' => [
                    'node' => ['type' => $modelName],
                    'cursor' => ['type' => 'String!'],
                ],
            ],
        ];
    }

    /**
     * Get schema definition
     */
    public function getSchema(string $schemaId): ?array
    {
        return Cache::get("graphql:schema:{$schemaId}");
    }

    /**
     * List all schemas
     */
    public function listSchemas(): array
    {
        $keys = Cache::getRedis()->keys('graphql:schema:*');
        $schemas = [];

        foreach ($keys as $key) {
            $schema = Cache::get($key);
            if ($schema) {
                $schemas[] = [
                    'schema_id' => $schema['id'],
                    'model' => $schema['name'],
                    'fields_count' => count($schema['fields']),
                    'relations_count' => count($schema['relations']),
                ];
            }
        }

        return $schemas;
    }

    /**
     * Validate schema
     */
    public function validateSchema(string $schemaId): array
    {
        $schema = Cache::get("graphql:schema:{$schemaId}");

        if (!$schema) {
            return ['error' => 'Schema not found'];
        }

        $errors = [];
        $warnings = [];

        // Check for required fields
        if (empty($schema['fields'])) {
            $errors[] = 'Schema must have at least one field';
        }

        // Check for ID field
        if (!isset($schema['fields']['id'])) {
            $warnings[] = 'Schema should have an ID field';
        }

        // Check for timestamp fields
        if (!isset($schema['fields']['created_at']) || !isset($schema['fields']['updated_at'])) {
            $warnings[] = 'Schema should have created_at and updated_at fields';
        }

        return [
            'schema_id' => $schemaId,
            'valid' => empty($errors),
            'errors' => $errors,
            'warnings' => $warnings,
        ];
    }

    /**
     * Merge schemas
     */
    public function mergeSchemas(array $schemaIds, string $newModelName): array
    {
        $mergedSchema = [
            'id' => uniqid('schema_'),
            'name' => $newModelName,
            'fields' => [],
            'relations' => [],
            'queries' => [],
            'mutations' => [],
        ];

        foreach ($schemaIds as $schemaId) {
            $schema = Cache::get("graphql:schema:{$schemaId}");

            if ($schema) {
                $mergedSchema['fields'] = array_merge($mergedSchema['fields'], $schema['fields']);
                $mergedSchema['relations'] = array_merge($mergedSchema['relations'], $schema['relations']);
            }
        }

        Cache::put("graphql:schema:{$mergedSchema['id']}", $mergedSchema, now()->addDays(365));

        return [
            'new_schema_id' => $mergedSchema['id'],
            'merged_count' => count($schemaIds),
            'status' => 'merged',
        ];
    }
}
