<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Modules\Core\Jobs\SarExportJob;

uses(RefreshDatabase::class);

it('queues SAR export job when no cached file exists', function (): void {
    Queue::fake();

     $user = actingAsUser('employee');
    $token = $user->createToken('test')->plainTextToken;

    $this->withToken($token)
        ->getJson('/api/v1/account/export')
        ->assertStatus(202)
        ->assertJsonFragment(['message' => 'Export in progress. Please retry in a few minutes.']);

    Queue::assertPushed(SarExportJob::class, function (SarExportJob $job) use ($user): bool {
        return $job->user->id === $user->id;
    });
});

it('returns cached SAR export file within 24h', function (): void {
    Storage::fake('local');

     $user = actingAsUser('employee');
    $path    = "sar/{$user->id}/export.json";
    $payload = json_encode(['exported_at' => now()->toIso8601String(), 'user' => []]);

    Storage::disk('local')->put($path, $payload);

    $token = $user->createToken('test')->plainTextToken;

    $response = $this->withToken($token)
        ->getJson('/api/v1/account/export')
        ->assertStatus(200);

    $response->assertHeader('Content-Disposition', "attachment; filename=sar_export_{$user->id}.json");
});
