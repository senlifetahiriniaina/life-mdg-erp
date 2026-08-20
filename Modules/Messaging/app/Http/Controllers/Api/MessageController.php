<?php

declare(strict_types=1);

namespace Modules\Messaging\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Messaging\Events\MessageSent;
use Modules\Messaging\Models\Conversation;

class MessageController extends Controller
{
    public function index(Request $request, Conversation $conversation): JsonResponse
    {
        $this->authorize('view', $conversation);

        $messages = $conversation->messages()->with('sender:id,name')->paginate(50);

        $conversation->participants()->where('user_id', $request->user()->id)
            ->update(['last_read_at' => now()]);

        return response()->json($messages);
    }

    public function store(Request $request, Conversation $conversation): JsonResponse
    {
        $this->authorize('post', $conversation);

        $validated = $request->validate([
            'body' => ['required', 'string', 'max:5000'],
        ]);

        $message = $conversation->messages()->create([
            'sender_id' => $request->user()->id,
            'body' => $validated['body'],
        ]);
        $conversation->touch();

        broadcast(new MessageSent($message))->toOthers();

        return response()->json(['data' => $message->load('sender:id,name')], 201);
    }
}
