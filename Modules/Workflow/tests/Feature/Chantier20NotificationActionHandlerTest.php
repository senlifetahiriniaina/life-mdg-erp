<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Workflow\Services\Actions\NotificationActionHandler;

uses(RefreshDatabase::class);

// Chantier 20 — regression for `sendInAppNotification()`, which previously
// raw-inserted into `notifications` using columns (tenant_id/user_id/
// is_read/action_url) that don't exist on the real Laravel-default table
// (id/type/notifiable/data/read_at) — every notify.in_app dispatch fataled
// with "no such column" before this fix.

test('notify.in_app writes a real notification row via NotificationService', function () {
    $user = User::factory()->create();

    $result = app(NotificationActionHandler::class)->dispatch('in_app', [
        'title' => 'Alerte workflow',
        'body' => 'Une étape du workflow requiert votre attention.',
        'type' => 'warning',
        'action_url' => '/workflow/flows/1',
    ], [
        'to' => [(string) $user->id],
        'tenant_id' => $user->company_id ?? 1,
    ]);

    expect($result['status'])->toBe('success');
    expect($result['action'])->toBe('notify.in_app');

    $row = $user->notifications()->latest()->first();
    expect($row)->not->toBeNull();
    $data = is_string($row->data) ? json_decode($row->data, true) : $row->data;
    expect($data['title'])->toBe('Alerte workflow');
    expect($data['body'])->toBe('Une étape du workflow requiert votre attention.');
});

test('notify.in_app skips gracefully when no user resolves', function () {
    $result = app(NotificationActionHandler::class)->dispatch('in_app', [
        'title' => 'x',
        'body' => 'y',
    ], [
        'to' => ['nonexistent-role'],
        'tenant_id' => 999999,
    ]);

    expect($result['status'])->toBe('skipped');
});
