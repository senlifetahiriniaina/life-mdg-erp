<?php

declare(strict_types=1);

namespace Modules\BI\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\BI\Models\DataStory;
use Modules\BI\Models\StorySlide;
use Modules\BI\Models\NarrativeFlow;

/**
 * @group BI - Data Stories
 *
 * Create and manage data storytelling presentations
 */
class DataStoryController extends Controller
{
    public function index(Request $request): JsonResponse|JsonResource
    {
        $this->authorize('viewAny', DataStory::class);

        $stories = DataStory::with(['creator', 'analytics'])
            ->where('company_id', $request->user()->company_id)
            ->when($request->input('status'), function ($query, $status) {
                $query->where('status', $status);
            })
            ->latest()
            ->paginate(20);

        return JsonResource::collection($stories);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', DataStory::class);

        $validated = $request->validate([
            'title'       => 'required|string|max:255',
            'description' => 'nullable|string',
            'summary'     => 'nullable|string',
            'is_public'   => 'boolean',
        ]);

        $story = DataStory::create([
            ...$validated,
            'company_id' => $request->user()->company_id,
            'created_by' => $request->user()->id,
            'status'     => 'draft',
        ]);

        return response()->json($story, 201);
    }

    public function show(DataStory $story): JsonResponse
    {
        $this->authorize('view', $story);
        $story->load(['slides', 'narrativeFlows', 'analytics']);

        return response()->json($story);
    }

    public function update(Request $request, DataStory $story): JsonResponse
    {
        $this->authorize('update', $story);

        $validated = $request->validate([
            'title'       => 'string|max:255',
            'description' => 'nullable|string',
            'summary'     => 'nullable|string',
            'is_public'   => 'boolean',
        ]);

        $story->update($validated);

        return response()->json($story);
    }

    public function destroy(DataStory $story): JsonResponse
    {
        $this->authorize('delete', $story);
        $story->delete();

        return response()->json(null, 204);
    }

    public function publish(DataStory $story): JsonResponse
    {
        $this->authorize('publish', $story);
        $story->publish();

        return response()->json(['status' => $story->status]);
    }

    public function archive(DataStory $story): JsonResponse
    {
        $this->authorize('publish', $story);
        $story->archive();

        return response()->json(['status' => $story->status]);
    }

    public function share(Request $request, DataStory $story): JsonResponse
    {
        $this->authorize('share', $story);

        $validated = $request->validate([
            'recipients'  => 'required|array|min:1',
            'recipients.*' => 'email',
            'message'     => 'nullable|string',
        ]);

        // Implement sharing logic
        return response()->json(['message' => 'Story shared successfully']);
    }

    // Slides management
    public function addSlide(Request $request, DataStory $story): JsonResponse
    {
        $this->authorize('manageNarratives', $story);

        $validated = $request->validate([
            'slide_number'        => 'required|integer|min:1',
            'title'               => 'required|string|max:255',
            'narrative_text'      => 'required|string',
            'visualization_config' => 'nullable|array',
            'interaction_rules'   => 'nullable|array',
            'transition_type'     => 'string|in:fade,slide,zoom,pop',
            'transition_duration' => 'integer|min:100',
        ]);

        $slide = StorySlide::create([
            'story_id' => $story->id,
            ...$validated,
        ]);

        $story->updateSlideCount();

        return response()->json($slide, 201);
    }

    public function updateSlide(Request $request, DataStory $story, StorySlide $slide): JsonResponse
    {
        $this->authorize('manageNarratives', $story);

        if ($slide->story_id !== $story->id) {
            return response()->json(['error' => 'Slide not in story'], 404);
        }

        $validated = $request->validate([
            'title'               => 'string|max:255',
            'narrative_text'      => 'string',
            'visualization_config' => 'nullable|array',
            'interaction_rules'   => 'nullable|array',
            'transition_type'     => 'string|in:fade,slide,zoom,pop',
            'transition_duration' => 'integer|min:100',
        ]);

        $slide->update($validated);

        return response()->json($slide);
    }

    public function deleteSlide(DataStory $story, StorySlide $slide): JsonResponse
    {
        $this->authorize('manageNarratives', $story);

        if ($slide->story_id !== $story->id) {
            return response()->json(['error' => 'Slide not in story'], 404);
        }

        $slide->delete();
        $story->updateSlideCount();

        return response()->json(null, 204);
    }

    // Narrative flows
    public function addNarrativeFlow(Request $request, DataStory $story): JsonResponse
    {
        $this->authorize('manageNarratives', $story);

        $validated = $request->validate([
            'name'        => 'required|string|max:255',
            'description' => 'nullable|string',
            'flow_config' => 'required|array',
        ]);

        $flow = NarrativeFlow::create([
            'story_id' => $story->id,
            ...$validated,
        ]);

        return response()->json($flow, 201);
    }

    public function updateNarrativeFlow(Request $request, DataStory $story, NarrativeFlow $flow): JsonResponse
    {
        $this->authorize('manageNarratives', $story);

        if ($flow->story_id !== $story->id) {
            return response()->json(['error' => 'Flow not in story'], 404);
        }

        $validated = $request->validate([
            'name'        => 'string|max:255',
            'description' => 'nullable|string',
            'flow_config' => 'array',
            'is_active'   => 'boolean',
        ]);

        $flow->update($validated);

        return response()->json($flow);
    }

    public function analytics(DataStory $story): JsonResponse
    {
        $this->authorize('viewAnalytics', $story);

        return response()->json($story->analytics()->first() ?? []);
    }

    public function views(Request $request, DataStory $story): JsonResponse
    {
        $this->authorize('viewAnalytics', $story);

        $views = $story->views()
            ->orderBy('viewed_at', 'desc')
            ->paginate(20);

        return response()->json($views);
    }
}
