<?php
declare(strict_types=1);
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Webhook;
use App\Services\WebhookService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class WebhookController extends Controller
{
    public function __construct(private readonly WebhookService $service) {}

    public function index(Request $request): JsonResponse
    {
        $webhooks = Webhook::where('user_id', $request->user()->id)
            ->paginate($request->integer('per_page', 50));
        return response()->json($webhooks);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'url'         => ['required', 'url'],
            'events'      => ['required', 'array', 'min:1'],
            'events.*'    => ['required', 'string'],
            'description' => ['nullable', 'string', 'max:255'],
        ]);
        $validated['user_id'] = $request->user()->id;
        $validated['secret']  = Str::random(32);

        $webhook = Webhook::create($validated);
        return response()->json($webhook, 201);
    }

    public function show(Webhook $webhook): JsonResponse
    {
        $this->authorize('view', $webhook);
        return response()->json($webhook->load('deliveries'));
    }

    public function update(Request $request, Webhook $webhook): JsonResponse
    {
        $this->authorize('update', $webhook);
        $validated = $request->validate([
            'url'         => ['sometimes', 'url'],
            'events'      => ['sometimes', 'array', 'min:1'],
            'is_active'   => ['sometimes', 'boolean'],
            'description' => ['sometimes', 'nullable', 'string'],
        ]);
        $webhook->update($validated);
        return response()->json($webhook->fresh());
    }

    public function destroy(Webhook $webhook): JsonResponse
    {
        $this->authorize('delete', $webhook);
        $webhook->delete();
        return response()->json(null, 204);
    }

    public function redeliver(Webhook $webhook): JsonResponse
    {
        $this->authorize('update', $webhook);
        $lastDelivery = $webhook->deliveries()->latest()->first();
        if (!$lastDelivery) {
            return response()->json(['message' => 'No deliveries to redeliver.'], 422);
        }
        $this->service->dispatch($lastDelivery->event, $lastDelivery->payload);
        return response()->json(['message' => 'Redelivery queued.']);
    }

    public function availableEvents(): JsonResponse
    {
        $events = [
            'crm.contact.created', 'crm.contact.updated', 'crm.deal.won', 'crm.deal.lost',
            'invoice.created', 'invoice.paid', 'invoice.overdue',
            'hr.employee.hired', 'hr.leave.approved',
            'inventory.low_stock', 'inventory.reorder_triggered',
            'helpdesk.ticket.created', 'helpdesk.ticket.resolved',
            'order.created', 'order.shipped', 'order.delivered',
            'project.created', 'project.completed',
        ];
        return response()->json(['events' => $events]);
    }
}
