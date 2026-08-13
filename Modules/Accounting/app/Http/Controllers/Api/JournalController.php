<?php

declare(strict_types=1);

namespace Modules\Accounting\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Accounting\Http\Requests\StoreJournalRequest;
use Modules\Accounting\Http\Requests\UpdateJournalRequest;
use Modules\Accounting\Http\Resources\JournalResource;
use Modules\Accounting\Models\Journal;

/**
 * @group Accounting - Journal
 *
 * Manage accounting journals and journal entries.
 */
class JournalController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Journal::query()
            ->when($request->search, fn ($q, $s) => $q->where(function ($q) use ($s) {
                $q->where('name', 'like', "%{$s}%")
                    ->orWhere('code', 'like', "%{$s}%");
            }))
            ->when($request->type, fn ($q, $v) => $q->where('type', $v))
            ->when($request->is_active !== null, fn ($q) => $q->where('is_active', $request->boolean('is_active')));

        $perPage = min((int) ($request->per_page ?? 25), 100);

        return response()->json(
            JournalResource::collection($query->orderBy('name')->paginate($perPage))->response()->getData(true)
        );
    }

    public function store(StoreJournalRequest $request): JsonResponse
    {
        $journal = Journal::create($request->validated());

        return response()->json(new JournalResource($journal), 201);
    }

    public function show(Journal $journal): JsonResponse
    {
        return response()->json(new JournalResource($journal));
    }

    public function update(UpdateJournalRequest $request, Journal $journal): JsonResponse
    {
        $this->authorize('update', $journal);

        $journal->update($request->validated());

        return response()->json(new JournalResource($journal->fresh()));
    }

    public function destroy(Journal $journal): JsonResponse
    {
        $this->authorize('delete', $journal);

        if (!$journal->canBeDeleted()) {
            return response()->json(['message' => 'Cannot delete a journal that has invoices or entries.'], 422);
        }

        $journal->delete();

        return response()->json(null, 204);
    }
}
