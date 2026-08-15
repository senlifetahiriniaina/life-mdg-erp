<?php

declare(strict_types=1);

namespace Modules\Inventory\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Modules\Inventory\Models\PickingWave;
use Modules\Inventory\Models\PickLine;
use Modules\Inventory\Services\WavePickingService;

/**
 * @group Controllers - Wave Picking
 *
 * Manage Wave Picking resources.
 */
class WavePickingController extends Controller
{
    public function __construct(private readonly WavePickingService $service) {}

    public function index(): JsonResponse
    {
        $waves = PickingWave::with(['picker', 'lines'])->latest()->paginate(20);

        return response()->json($waves);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'order_ids' => 'required|array|min:1',
            'order_ids.*' => 'integer',
            'picker_id' => 'nullable|exists:users,id',
        ]);

        $wave = $this->service->createWave($data['order_ids']);

        if (isset($data['picker_id'])) {
            $wave->update(['picker_id' => $data['picker_id']]);
        }

        $wave->load(['picker', 'lines.product']);

        return response()->json($wave, 201);
    }

    public function show(PickingWave $wave): JsonResponse
    {
        $wave->load(['picker', 'lines.product']);

        return response()->json($wave);
    }

    public function update(Request $request, PickingWave $wave): JsonResponse
    {
        $data = $request->validate([
            'picker_id' => 'nullable|exists:users,id',
        ]);

        $wave->update($data);
        $wave->load(['picker', 'lines.product']);

        return response()->json($wave);
    }

    public function destroy(PickingWave $wave): JsonResponse
    {
        if ($wave->status === 'in_progress') {
            return response()->json(['message' => 'Cannot delete a wave in progress.'], 422);
        }

        $wave->lines()->delete();
        $wave->delete();

        return response()->json(null, 204);
    }

    public function start(PickingWave $wave): JsonResponse
    {
        $this->service->startWave($wave);
        $wave->load(['picker', 'lines.product']);

        return response()->json($wave);
    }

    public function pickLine(Request $request, PickingWave $wave, PickLine $wavePickLine): JsonResponse
    {
        if ((int) $wavePickLine->wave_id !== $wave->id) {
            return response()->json(['message' => 'Line does not belong to this wave.'], 422);
        }

        $data = $request->validate([
            'qty' => 'required|numeric|min:0',
        ]);

        $this->service->confirmPick($wavePickLine, (float) $data['qty']);
        $wavePickLine->refresh();

        return response()->json($wavePickLine);
    }

    public function complete(PickingWave $wave): JsonResponse
    {
        $this->service->completeWave($wave);
        $wave->load(['picker', 'lines.product']);

        return response()->json($wave);
    }
}
