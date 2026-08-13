<?php

declare(strict_types=1);

namespace Modules\BI\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\BI\Http\Requests\StoreDataSourceRequest;
use Modules\BI\Http\Requests\UpdateDataSourceRequest;
use Modules\BI\Models\BiDataSource;
use Modules\BI\Services\DataSourceService;

/**
 * @group BI - Data Sources
 *
 * Manage external data source connectors (MySQL, PostgreSQL, REST API, CSV, Google Sheets).
 * Connection config is stored AES-256 encrypted. Each source can be tested, synced, and
 * used as a selectable data source in the BI dashboard builder.
 */
class DataSourceController extends Controller
{
    public function __construct(private readonly DataSourceService $service) {}

    // -------------------------------------------------------------------------
    // CRUD
    // -------------------------------------------------------------------------

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user->can('viewAny', BiDataSource::class)) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $sources = BiDataSource::where('created_by', $user->id)
            ->latest()
            ->paginate(20);

        return response()->json($sources);
    }

    public function store(StoreDataSourceRequest $request): JsonResponse
    {
        $user = $request->user();
        if (! $user->can('create', BiDataSource::class)) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $source = $this->service->create($request->validated(), $user->id);

        return response()->json($source, 201);
    }

    public function show(Request $request, BiDataSource $biDataSource): JsonResponse
    {
        if (! $request->user()->can('view', $biDataSource)) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        return response()->json($biDataSource);
    }

    public function update(UpdateDataSourceRequest $request, BiDataSource $biDataSource): JsonResponse
    {
        if (! $request->user()->can('update', $biDataSource)) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        return response()->json($this->service->update($biDataSource, $request->validated()));
    }

    public function destroy(Request $request, BiDataSource $biDataSource): JsonResponse
    {
        if (! $request->user()->can('delete', $biDataSource)) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $biDataSource->delete();

        return response()->json(['message' => 'Data source deleted.']);
    }

    // -------------------------------------------------------------------------
    // Connection test
    // -------------------------------------------------------------------------

    /**
     * Test connector reachability for a data source.
     *
     * @response 200 {"success": true, "latency_ms": 12, "status": "active", "message": "Connection successful."}
     */
    public function test(Request $request, BiDataSource $biDataSource): JsonResponse
    {
        if (! $request->user()->can('view', $biDataSource)) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        try {
            $result = $this->service->testConnection($biDataSource);
        } catch (\Throwable $e) {
            return response()->json([
                'success'    => false,
                'latency_ms' => 0,
                'status'     => 'error',
                'message'    => $e->getMessage(),
            ]);
        }

        return response()->json([
            'success'    => $result['success'],
            'latency_ms' => $result['latency_ms'],
            'status'     => $result['success'] ? 'active' : 'error',
            'message'    => $result['message'],
        ]);
    }

    // -------------------------------------------------------------------------
    // Sync (fetch latest data)
    // -------------------------------------------------------------------------

    /**
     * Pull the latest data from the external source.
     *
     * @bodyParam query string Source-specific query expression (SQL, range, endpoint path). Example: SELECT * FROM orders LIMIT 100
     *
     * @response 200 {"columns": ["id","name"], "rows": [...], "duration_ms": 45}
     */
    public function sync(Request $request, BiDataSource $biDataSource): JsonResponse
    {
        if (! $request->user()->can('view', $biDataSource)) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $data = $request->validate(['query' => ['sometimes', 'string', 'max:2000']]);

        try {
            $result = $this->service->fetchData($biDataSource, $data['query'] ?? '');

            return response()->json($result);
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    // -------------------------------------------------------------------------
    // Schema discovery
    // -------------------------------------------------------------------------

    /**
     * Discover the schema of an external data source.
     *
     * Returns table/column metadata that can be used in the BI dashboard builder.
     *
     * @response 200 [{"table": "orders", "columns": [{"name": "id", "type": "int", "nullable": false}]}]
     */
    public function schema(Request $request, BiDataSource $biDataSource): JsonResponse
    {
        if (! $request->user()->can('view', $biDataSource)) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        try {
            return response()->json($this->service->getSchema($biDataSource));
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    // -------------------------------------------------------------------------
    // Supported connector types
    // -------------------------------------------------------------------------

    /**
     * List all supported connector types and their required config keys.
     */
    public function types(): JsonResponse
    {
        return response()->json($this->service->supportedTypes());
    }
}
