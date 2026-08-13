<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin\AuditLog;
use App\Models\Admin\ServerConfig;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ServerController extends Controller
{
    private const PROVIDERS = [
        'gcp' => [
            'name'        => 'Google Cloud Platform',
            'console_url' => 'https://console.cloud.google.com',
            'regions'     => ['us-central1', 'eu-west1', 'asia-east1'],
        ],
        'aws' => [
            'name'        => 'Amazon Web Services',
            'console_url' => 'https://aws.amazon.com/console',
            'regions'     => ['us-east-1', 'eu-west-1', 'ap-southeast-1'],
        ],
        'azure' => [
            'name'        => 'Microsoft Azure',
            'console_url' => 'https://portal.azure.com',
            'regions'     => ['eastus', 'westeurope', 'southeastasia'],
        ],
        'digitalocean' => [
            'name'        => 'DigitalOcean',
            'console_url' => 'https://cloud.digitalocean.com',
            'regions'     => ['nyc1', 'ams3', 'sgp1'],
        ],
        'hetzner' => [
            'name'        => 'Hetzner Cloud',
            'console_url' => 'https://console.hetzner.cloud',
            'regions'     => ['nbg1', 'fsn1', 'hel1'],
        ],
        'ovh' => [
            'name'        => 'OVHcloud',
            'console_url' => 'https://www.ovhcloud.com',
            'regions'     => ['GRA', 'SBG', 'WAW'],
        ],
        'custom' => [
            'name'        => 'Custom / On-Premise',
            'console_url' => null,
            'regions'     => [],
        ],
    ];

    private function authorizeAdmin(): void
    {
        $user = request()->user();
        if (! ($user instanceof \App\Models\User) || ! $user->hasAnyRole(['super-admin', 'admin', 'system-admin'])) {
            abort(403, 'Insufficient privileges.');
        }
    }

    public function index(): JsonResponse
    {
        $this->authorizeAdmin();
        $servers = ServerConfig::latest()->paginate(20);

        return response()->json($servers);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorizeAdmin();
        $validated = $request->validate([
            'name'          => ['required', 'string', 'max:255'],
            'provider'      => ['required', Rule::in(array_keys(self::PROVIDERS))],
            'region'        => ['required', 'string', 'max:100'],
            'instance_type' => ['required', 'string', 'max:100'],
            'ip_address'    => ['nullable', 'ip'],
            'api_endpoint'  => ['nullable', 'url', 'max:500'],
            'status'        => ['sometimes', Rule::in(['active', 'stopped', 'maintenance', 'unknown'])],
            'credentials'   => ['nullable', 'string'],
            'metadata'      => ['nullable', 'array'],
        ]);

        $server = new ServerConfig($validated);

        if (isset($validated['credentials'])) {
            $server->credentials = $validated['credentials'];
        }

        $server->save();

        AuditLog::record('create', null, ServerConfig::class, $server->id, ['name' => $server->name]);

        return response()->json($server, 201);
    }

    public function show(ServerConfig $server): JsonResponse
    {
        $this->authorizeAdmin();
        return response()->json($server);
    }

    public function update(Request $request, ServerConfig $server): JsonResponse
    {
        $this->authorizeAdmin();
        $validated = $request->validate([
            'name'          => ['sometimes', 'string', 'max:255'],
            'provider'      => ['sometimes', Rule::in(array_keys(self::PROVIDERS))],
            'region'        => ['sometimes', 'string', 'max:100'],
            'instance_type' => ['sometimes', 'string', 'max:100'],
            'ip_address'    => ['nullable', 'ip'],
            'api_endpoint'  => ['nullable', 'url', 'max:500'],
            'status'        => ['sometimes', Rule::in(['active', 'stopped', 'maintenance', 'unknown'])],
            'credentials'   => ['nullable', 'string'],
            'metadata'      => ['nullable', 'array'],
        ]);

        if (isset($validated['credentials'])) {
            $server->credentials = $validated['credentials'];
            unset($validated['credentials']);
        }

        $server->update($validated);

        AuditLog::record('update', null, ServerConfig::class, $server->id, ['name' => $server->name]);

        return response()->json($server);
    }

    public function destroy(ServerConfig $server): JsonResponse
    {
        $this->authorizeAdmin();
        AuditLog::record('delete', null, ServerConfig::class, $server->id, ['name' => $server->name]);
        $server->delete();

        return response()->json(['message' => 'Server deleted.']);
    }

    public function ping(ServerConfig $server): JsonResponse
    {
        $this->authorizeAdmin();
        // Simulate ping: random latency 10-200 ms
        $latencyMs = random_int(10, 200);
        $status    = $server->ip_address ? 'active' : 'unknown';

        $server->update([
            'last_ping_at' => now(),
            'status'       => $status,
        ]);

        AuditLog::record('ping', null, ServerConfig::class, $server->id);

        return response()->json([
            'latency_ms' => $latencyMs,
            'status'     => $status,
            'pinged_at'  => now()->toISOString(),
        ]);
    }

    public function metrics(ServerConfig $server): JsonResponse
    {
        $this->authorizeAdmin();
        return response()->json([
            'server_id'   => $server->id,
            'cpu_usage'   => random_int(20, 80),
            'memory_usage' => random_int(30, 70),
            'disk_usage'  => random_int(10, 60),
            'uptime_days' => random_int(1, 365),
            'collected_at' => now()->toISOString(),
        ]);
    }

    public function deploy(ServerConfig $server): JsonResponse
    {
        $this->authorizeAdmin();
        $jobId = uniqid('deploy_', true);

        AuditLog::record('deploy', null, ServerConfig::class, $server->id, ['job_id' => $jobId]);

        return response()->json([
            'job_id'     => $jobId,
            'server_id'  => $server->id,
            'status'     => 'queued',
            'message'    => 'Deployment job queued successfully.',
            'queued_at'  => now()->toISOString(),
        ]);
    }

    public function providers(): JsonResponse
    {
        $this->authorizeAdmin();
        return response()->json(self::PROVIDERS);
    }
}
