<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use ReflectionClass;
use ReflectionMethod;

class GenerateScribeDocumentation extends Command
{
    protected $signature = 'scribe:auto-doc {path=Modules} {--force}';
    protected $description = 'Generate Scribe documentation for API controllers';

    public function handle(): int
    {
        $basePath = base_path($this->argument('path'));
        $force = $this->option('force');

        if (!is_dir($basePath)) {
            $this->error("Path not found: $basePath");
            return 1;
        }

        $controllers = $this->findControllers($basePath);
        $this->info("Found " . count($controllers) . " API controllers");

        $updated = 0;
        foreach ($controllers as $file) {
            if ($this->addDocumentation($file, $force)) {
                $updated++;
                $this->line("✅ " . basename($file));
            }
        }

        $this->info("\nUpdated: $updated controllers");
        return 0;
    }

    private function findControllers(string $basePath): array
    {
        $files = [];
        $pattern = $basePath . '/*/app/Http/Controllers/Api/*Controller.php';

        foreach (glob($pattern) as $file) {
            if (!str_contains(file_get_contents($file), '@group')) {
                $files[] = $file;
            }
        }

        return $files;
    }

    private function addDocumentation(string $file, bool $force): bool
    {
        $content = file_get_contents($file);

        // Skip if already documented (unless force)
        if (!$force && str_contains($content, '@group')) {
            return false;
        }

        // Parse class name and module
        preg_match('/namespace (.*?);/', $content, $namespaceMatch);
        preg_match('/class (\w+) extends/', $content, $classMatch);

        if (!$classMatch) {
            return false;
        }

        $namespace = $namespaceMatch[1] ?? '';
        $className = $classMatch[1];
        $module = $this->extractModule($namespace);
        $resource = $this->extractResource($className);

        // Generate doc block
        $docBlock = $this->generateDocBlock($module, $resource, $className);

        // Insert doc block before class declaration
        $content = preg_replace(
            '/^(class \w+)/m',
            $docBlock . "\nclass $1",
            $content,
            1
        );

        // Also add method docs for CRUD operations
        $content = $this->addMethodDocumentation($content, $module, $resource);

        file_put_contents($file, $content);

        return true;
    }

    private function generateDocBlock(string $module, string $resource, string $className): string
    {
        return <<<EOD
/**
 * @group $module - $resource
 * Manage $resource resources for the $module module
 *
 * @authenticated
 * @param int \$id The resource ID
 * @param string \$sort_by Field to sort by (created_at, updated_at, name)
 * @param string \$sort_order Sort order (asc, desc)
 * @param int \$per_page Results per page (default: 50, max: 100)
 *
 * @response 200 {"data": [...], "meta": {"total": 100, "per_page": 50, "current_page": 1}}
 * @response 401 {"message": "Unauthorized"}
 * @response 403 {"message": "Forbidden"}
 * @response 404 {"message": "Not found"}
 * @response 422 {"message": "Validation error", "errors": {...}}
 * @response 429 {"message": "Too many requests"}
 */
EOD;
    }

    private function addMethodDocumentation(string $content, string $module, string $resource): string
    {
        $patterns = [
            '/public function index/' => "/**\n     * @return {$resource}[] paginated list\n     * @queryParam sort_by string\n     * @queryParam sort_order string\n     * @queryParam per_page integer\n     */\n    public function index",

            '/public function store/' => "/**\n     * @bodyParam name string required\n     * @response 201 {\"id\": 1, \"name\": \"...\"}\n     */\n    public function store",

            '/public function show/' => "/**\n     * @urlParam id integer required The $resource ID\n     */\n    public function show",

            '/public function update/' => "/**\n     * @urlParam id integer required The $resource ID\n     * @response 200 The updated resource\n     */\n    public function update",

            '/public function destroy/' => "/**\n     * @urlParam id integer required The $resource ID\n     * @response 204\n     */\n    public function destroy",
        ];

        foreach ($patterns as $pattern => $replacement) {
            $content = preg_replace($pattern, $replacement, $content, 1);
        }

        return $content;
    }

    private function extractModule(string $namespace): string
    {
        if (preg_match('/Modules\\\\(\w+)\\\\/', $namespace, $match)) {
            return $match[1];
        }
        return 'Core';
    }

    private function extractResource(string $className): string
    {
        return str_replace('Controller', '', $className);
    }
}
