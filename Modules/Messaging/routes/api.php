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

Route::middleware(['auth:sanctum', 'session.security', 'tenancy.user', 'module:Messaging', 'role:employee,manager,admin,super-admin'])->prefix('v1/messaging')->group(function () {
    Route::get('users', [ConversationController::class, 'users']);
    Route::get('conversations', [ConversationController::class, 'index']);
    Route::post('conversations', [ConversationController::class, 'store'])->middleware('throttle:create_post');
    Route::get('conversations/{conversation}', [ConversationController::class, 'show']);
    Route::get('conversations/{conversation}/messages', [MessageController::class, 'index']);
    Route::post('conversations/{conversation}/messages', [MessageController::class, 'store'])->middleware('throttle:create_post');

    Route::post('ai/assist', [MessagingAiAssistController::class, 'assist']);
});
