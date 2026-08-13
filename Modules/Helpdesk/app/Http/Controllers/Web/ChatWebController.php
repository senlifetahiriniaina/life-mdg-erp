<?php

declare(strict_types=1);

namespace Modules\Helpdesk\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Helpdesk\Models\ChatSession;

class ChatWebController extends Controller
{
    public function index(): Response
    {
        $waiting = ChatSession::where('status', 'waiting')
            ->orderBy('started_at')
            ->get();

        $active = ChatSession::where('status', 'active')
            ->with('assignedAgent:id,name')
            ->orderBy('started_at')
            ->get();

        return Inertia::render('Helpdesk/Chat/Index', [
            'waitingSessions' => $waiting,
            'activeSessions' => $active,
        ]);
    }
}
