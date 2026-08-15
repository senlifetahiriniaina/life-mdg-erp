<?php

declare(strict_types=1);

namespace Modules\BI\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Modules\BI\Models\BiQuery;

/**
 * @group Controllers - Query
 *
 * Manage Query resources.
 */
class QueryController extends Controller
{
    private const FORBIDDEN_KEYWORDS = ['INSERT', 'UPDATE', 'DELETE', 'DROP', 'TRUNCATE', 'ALTER', 'CREATE', 'REPLACE'];

    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    /**
     * List saved queries.
     */
    public function index(Request $request): JsonResponse
    {
        $queries = BiQuery::where('created_by', $request->user()->id)
            ->orWhere('is_public', true)
            ->latest()
            ->paginate(20);

        return response()->json($queries);
    }

    /**
     * Store a new saved query.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'sql_query' => 'required|string',
            'datasource' => 'nullable|string|max:100',
            'result_cache_ttl' => 'nullable|integer|min:0',
            'is_public' => 'nullable|boolean',
        ]);

        $this->validateSql($validated['sql_query']);

        $query = BiQuery::create([
            ...$validated,
            'created_by' => $request->user()->id,
            'datasource' => $validated['datasource'] ?? 'default',
            'result_cache_ttl' => $validated['result_cache_ttl'] ?? 0,
            'is_public' => $validated['is_public'] ?? false,
        ]);

        return response()->json($query, 201);
    }

    /**
     * Get a single saved query.
     */
    public function show(Request $request, BiQuery $query): JsonResponse
    {
        if (! $query->is_public && $query->created_by !== $request->user()->id) {
            abort(403, 'Forbidden.');
        }

        return response()->json($query->load('createdBy:id,name,email'));
    }

    /**
     * Update a saved query.
     */
    public function update(Request $request, BiQuery $query): JsonResponse
    {
        if ($query->created_by !== $request->user()->id) {
            abort(403, 'Forbidden.');
        }

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'sql_query' => 'sometimes|string',
            'datasource' => 'nullable|string|max:100',
            'result_cache_ttl' => 'nullable|integer|min:0',
            'is_public' => 'nullable|boolean',
        ]);

        if (isset($validated['sql_query'])) {
            $this->validateSql($validated['sql_query']);
        }

        $query->update($validated);

        return response()->json($query->fresh());
    }

    /**
     * Delete a saved query.
     */
    public function destroy(Request $request, BiQuery $query): Response
    {
        if ($query->created_by !== $request->user()->id) {
            abort(403, 'Forbidden.');
        }

        $query->delete();

        return response()->noContent();
    }

    /**
     * Run a saved query by ID.
     */
    public function run(Request $request, BiQuery $query): JsonResponse
    {
        $this->validateSql($query->sql_query);

        $start = microtime(true);

        try {
            $results = DB::select($query->sql_query);
        } catch (\Throwable $e) {
            return response()->json(['message' => 'Query error: '.$e->getMessage()], 422);
        }

        $durationMs = (int) round((microtime(true) - $start) * 1000);

        $query->update(['last_run_at' => now()]);

        $columns = count($results) > 0 ? array_keys((array) $results[0]) : [];
        $rows = array_map(fn ($row) => array_values((array) $row), $results);

        return response()->json([
            'columns' => $columns,
            'rows' => $rows,
            'duration_ms' => $durationMs,
        ]);
    }

    /**
     * Run a raw SQL query (admin-level).
     */
    public function runRaw(Request $request): JsonResponse
    {
        // Restrict raw query execution to admin users only
        if (! $request->user()->hasAnyRole(['super-admin', 'admin'])) {
            abort(403, 'Raw SQL execution is restricted to administrators.');
        }

        $validated = $request->validate([
            'sql' => 'required|string',
        ]);

        $this->validateSql($validated['sql']);

        $start = microtime(true);

        try {
            $results = DB::select($validated['sql']);
        } catch (\Throwable $e) {
            return response()->json(['message' => 'Query error: '.$e->getMessage()], 422);
        }

        $durationMs = (int) round((microtime(true) - $start) * 1000);

        $columns = count($results) > 0 ? array_keys((array) $results[0]) : [];
        $rows = array_map(fn ($row) => array_values((array) $row), $results);

        return response()->json([
            'columns' => $columns,
            'rows' => $rows,
            'duration_ms' => $durationMs,
        ]);
    }

    /**
     * Export a saved query result as CSV.
     */
    public function export(Request $request, BiQuery $query): Response
    {
        $format = $request->query('format', 'csv');

        $this->validateSql($query->sql_query);

        try {
            $results = DB::select($query->sql_query);
        } catch (\Throwable $e) {
            abort(422, 'Query error: '.$e->getMessage());
        }

        $columns = count($results) > 0 ? array_keys((array) $results[0]) : [];

        $output = implode(',', $columns)."\n";
        foreach ($results as $row) {
            $values = array_map(
                fn ($v) => '"'.str_replace('"', '""', (string) $v).'"',
                array_values((array) $row)
            );
            $output .= implode(',', $values)."\n";
        }

        $filename = 'query_'.$query->id.'_'.now()->format('YmdHis').'.csv';

        return response($output, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    private function validateSql(string $sql): void
    {
        $upperSql = strtoupper($sql);

        foreach (self::FORBIDDEN_KEYWORDS as $keyword) {
            if (preg_match('/\b'.$keyword.'\b/', $upperSql)) {
                abort(422, "Forbidden SQL keyword: {$keyword}. Only SELECT queries are allowed.");
            }
        }
    }
}
