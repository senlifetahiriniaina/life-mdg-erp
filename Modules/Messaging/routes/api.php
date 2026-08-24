<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Messaging\Http\Controllers\Api\ConversationController;
use Modules\Messaging\Http\Controllers\Api\MessageController;
use Modules\Messaging\Http\Controllers\Api\MessagingAiAssistController;

/*
|--------------------------------------------------------------------------
| Messaging API Routes
|--------------------------------------------------------------------------
*/

// Chantier 32.28: dropped the `role:employee,manager,admin,super-admin` gate.
// Confirmed empirically that a user carrying only a specialised role (e.g.
// sales-rep, hr-manager — this app assigns a single role per real user, see
// DemoSeeder's syncRoles([$d['role']])) got a flat 403 on every messaging
// endpoint. Internal team messaging is not a job-role-scoped feature — it is
// meant for literally any authenticated user of a tenant with the module
// enabled, the same precedent already used by Core's own universal
// `notifications/*` routes (auth + tenancy only, no role gate at all).
// `module:Messaging` alone still lets an admin disable the module per tenant.
Route::middleware(['auth:sanctum', 'session.security', 'tenancy.user', 'module:Messaging'])->prefix('v1/messaging')->group(function () {
    Route::get('users', [ConversationController::class, 'users']);
    Route::get('conversations', [ConversationController::class, 'index']);
    Route::post('conversations', [ConversationController::class, 'store'])->middleware('throttle:create_post');
    Route::get('conversations/{conversation}', [ConversationController::class, 'show']);
    Route::get('conversations/{conversation}/messages', [MessageController::class, 'index']);
    Route::post('conversations/{conversation}/messages', [MessageController::class, 'store'])->middleware('throttle:create_post');

    Route::post('ai/assist', [MessagingAiAssistController::class, 'assist']);
});
