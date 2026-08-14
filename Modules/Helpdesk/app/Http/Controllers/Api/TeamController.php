<?php

declare(strict_types=1);

namespace Modules\Helpdesk\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Helpdesk\Models\Team;

/**
 * @group Helpdesk - Team
 *
 * Manage helpdesk support teams.
 */
class TeamController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(Team::withCount('members')->paginate(25));
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email'],
            'auto_assignment' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $team = Team::create($validated);

        return response()->json($team, 201);
    }

    public function show(Team $team): JsonResponse
    {
        return response()->json($team->load('members:id,name,email'));
    }

    public function update(Request $request, Team $team): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email'],
            'auto_assignment' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $team->update($validated);

        return response()->json($team);
    }

    public function destroy(Team $team): JsonResponse
    {
        $team->delete();

        return response()->json(null, 204);
    }
}
