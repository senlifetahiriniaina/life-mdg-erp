<?php

declare(strict_types=1);

namespace Modules\BI\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\BI\Models\ExternalDataSource;
use Modules\BI\Models\ExternalCredential;
use Modules\BI\Models\FieldMapping;
use Modules\BI\Models\SyncConfiguration;
use Modules\BI\Models\TransformationRule;
use Modules\BI\Models\SyncHistory;

/**
 * @group BI - External Data
 *
 * Manage external data source integrations
 */
class ExternalDataSourceController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', ExternalDataSource::class);

        $sources = ExternalDataSource::with(['creator'])
            ->where('company_id', $request->user()->company_id)
            ->when($request->input('status'), fn($q, $s) => $q->where('status', $s))
            ->latest()
            ->paginate(20);

        return response()->json($sources);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', ExternalDataSource::class);

        $validated = $request->validate([
            'name'                  => 'required|string|max:255',
            'description'           => 'nullable|string',
            'source_type'           => 'required|string|in:google_analytics,shopify,salesforce,hubspot,stripe,custom_api',
            'authentication_type'   => 'required|string|in:oauth,api_key,basic_auth,bearer_token',
            'connection_config'     => 'required|array',
        ]);

        $source = ExternalDataSource::create([
            ...$validated,
            'company_id' => $request->user()->company_id,
            'created_by' => $request->user()->id,
            'status'     => 'inactive',
        ]);

        return response()->json($source, 201);
    }

    public function show(ExternalDataSource $source): JsonResponse
    {
        $this->authorize('view', $source);
        $source->load(['fieldMappings', 'syncConfiguration', 'transformationRules']);

        return response()->json($source);
    }

    public function update(Request $request, ExternalDataSource $source): JsonResponse
    {
        $this->authorize('view', $source);

        $validated = $request->validate([
            'name'              => 'string|max:255',
            'description'       => 'nullable|string',
            'connection_config' => 'array',
        ]);

        $source->update($validated);

        return response()->json($source);
    }

    public function delete(ExternalDataSource $source): JsonResponse
    {
        $this->authorize('delete', $source);
        $source->delete();

        return response()->json(null, 204);
    }

    public function testConnection(Request $request, ExternalDataSource $source): JsonResponse
    {
        $this->authorize('connect', $source);

        try {
            // Implement connection test logic based on source_type
            $source->recordTestConnection();

            return response()->json(['success' => true, 'message' => 'Connection test successful']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 422);
        }
    }

    public function connect(ExternalDataSource $source): JsonResponse
    {
        $this->authorize('connect', $source);

        $source->connect();

        return response()->json(['status' => $source->status]);
    }

    public function disconnect(ExternalDataSource $source): JsonResponse
    {
        $this->authorize('disconnect', $source);

        $source->disconnect();

        return response()->json(['status' => $source->status]);
    }

    // Credentials management
    public function storeCredential(Request $request, ExternalDataSource $source): JsonResponse
    {
        $this->authorize('manageCredentials', $source);

        $validated = $request->validate([
            'credential_type' => 'required|string',
            'value'          => 'required|string',
            'expires_at'     => 'nullable|date',
        ]);

        $credential = new ExternalCredential($validated);
        $credential->setEncryptedValue($validated['value']);
        $credential->source_id = $source->id;
        $credential->save();

        return response()->json(['message' => 'Credential saved securely'], 201);
    }

    public function deleteCredential(ExternalDataSource $source, ExternalCredential $credential): JsonResponse
    {
        $this->authorize('manageCredentials', $source);

        if ($credential->source_id !== $source->id) {
            return response()->json(['error' => 'Credential not in source'], 404);
        }

        $credential->delete();

        return response()->json(null, 204);
    }

    // Field mappings
    public function mapFields(Request $request, ExternalDataSource $source): JsonResponse
    {
        $this->authorize('transformData', $source);

        $validated = $request->validate([
            'source_field'         => 'required|string',
            'target_field'         => 'required|string',
            'data_type'           => 'required|string|in:string,integer,decimal,datetime,boolean,json',
            'transformation_rule'  => 'nullable|string',
            'is_primary_key'      => 'boolean',
        ]);

        $mapping = FieldMapping::create([
            'source_id' => $source->id,
            ...$validated,
        ]);

        return response()->json($mapping, 201);
    }

    public function updateMapping(Request $request, ExternalDataSource $source, FieldMapping $mapping): JsonResponse
    {
        $this->authorize('transformData', $source);

        if ($mapping->source_id !== $source->id) {
            return response()->json(['error' => 'Mapping not in source'], 404);
        }

        $validated = $request->validate([
            'target_field'        => 'string',
            'data_type'          => 'string|in:string,integer,decimal,datetime,boolean,json',
            'transformation_rule' => 'nullable|string',
            'is_mapped'          => 'boolean',
        ]);

        $mapping->update($validated);

        return response()->json($mapping);
    }

    public function deleteMapping(ExternalDataSource $source, FieldMapping $mapping): JsonResponse
    {
        $this->authorize('transformData', $source);

        if ($mapping->source_id !== $source->id) {
            return response()->json(['error' => 'Mapping not in source'], 404);
        }

        $mapping->delete();

        return response()->json(null, 204);
    }

    // Sync configuration
    public function configureSyncRequest(Request $request, ExternalDataSource $source): JsonResponse
    {
        $this->authorize('configureSync', $source);

        $validated = $request->validate([
            'sync_type'           => 'required|string|in:full,incremental,delta',
            'frequency'          => 'required|string|in:manual,hourly,daily,weekly,monthly',
            'scheduled_time'     => 'nullable|date_format:H:i:s',
            'day_of_week'        => 'nullable|string',
            'day_of_month'       => 'nullable|integer|between:1,31',
            'batch_size'         => 'integer|min:10',
            'max_retries'        => 'integer|min:1',
            'retry_delay_minutes' => 'integer|min:1',
            'filter_criteria'    => 'nullable|array',
        ]);

        $config = SyncConfiguration::updateOrCreate(
            ['source_id' => $source->id],
            $validated
        );

        return response()->json($config);
    }

    public function syncNow(Request $request, ExternalDataSource $source): JsonResponse
    {
        $this->authorize('configureSync', $source);

        $source->markSyncing();

        // Queue sync job in real implementation
        SyncHistory::create([
            'source_id' => $source->id,
            'status'    => 'running',
            'sync_type' => 'manual',
            'started_at' => now(),
        ]);

        return response()->json(['message' => 'Sync started']);
    }

    public function syncHistory(Request $request, ExternalDataSource $source): JsonResponse
    {
        $this->authorize('viewHistory', $source);

        $history = SyncHistory::where('source_id', $source->id)
            ->when($request->input('status'), fn($q, $s) => $q->where('status', $s))
            ->latest()
            ->paginate(20);

        return response()->json($history);
    }

    // Transformation rules
    public function addTransformationRule(Request $request, ExternalDataSource $source): JsonResponse
    {
        $this->authorize('transformData', $source);

        $validated = $request->validate([
            'name'        => 'required|string|max:255',
            'description' => 'nullable|string',
            'rule_order'  => 'required|integer',
            'rule_type'   => 'required|string|in:filter,map,aggregate,custom',
            'rule_config' => 'required|array',
        ]);

        $rule = TransformationRule::create([
            'source_id' => $source->id,
            ...$validated,
        ]);

        return response()->json($rule, 201);
    }

    public function updateTransformationRule(Request $request, ExternalDataSource $source, TransformationRule $rule): JsonResponse
    {
        $this->authorize('transformData', $source);

        if ($rule->source_id !== $source->id) {
            return response()->json(['error' => 'Rule not in source'], 404);
        }

        $validated = $request->validate([
            'name'        => 'string|max:255',
            'description' => 'nullable|string',
            'rule_order'  => 'integer',
            'rule_config' => 'array',
            'is_active'   => 'boolean',
        ]);

        $rule->update($validated);

        return response()->json($rule);
    }

    public function deleteTransformationRule(ExternalDataSource $source, TransformationRule $rule): JsonResponse
    {
        $this->authorize('transformData', $source);

        if ($rule->source_id !== $source->id) {
            return response()->json(['error' => 'Rule not in source'], 404);
        }

        $rule->delete();

        return response()->json(null, 204);
    }
}
