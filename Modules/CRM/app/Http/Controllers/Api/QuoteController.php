<?php

declare(strict_types=1);

namespace Modules\CRM\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Modules\CRM\Http\Resources\QuoteResource;
use Modules\CRM\Models\Quote;
use Modules\CRM\Services\CpqService;

/**
 * @group CRM - CPQ Quotes
 */
class QuoteController extends Controller
{
    public function __construct(private readonly CpqService $cpq) {}

    /**
     * List quotes (paginated).
     *
     * Chantier 32.15: had zero tenant scoping — any authenticated CRM-module user of any
     * company could list every other company's CPQ quotes (pricing, discounts, contact
     * linkage), confirmed empirically before this fix.
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Quote::class);

        $perPage = min((int) ($request->per_page ?? 25), 100);

        $quotes = Quote::query()
            ->with(['contact', 'opportunity'])
            ->where('tenant_id', $request->user()->company_id)
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->when($request->filled('contact_id'), fn ($q) => $q->where('contact_id', $request->contact_id))
            ->when($request->filled('search'), fn ($q) => $q->where('reference', 'like', "%{$request->search}%"))
            ->orderByDesc('created_at')
            ->paginate($perPage);

        return response()->json(QuoteResource::collection($quotes)->response()->getData(true));
    }

    /**
     * Create a new quote.
     */
    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Quote::class);

        $validated = $request->validate([
            'opportunity_id' => 'nullable|exists:crm_opportunities,id',
            'contact_id' => 'nullable|exists:crm_contacts,id',
            'reference' => 'nullable|string|max:100|unique:crm_quotes,reference',
            'status' => 'nullable|in:draft,sent,accepted,rejected,expired',
            'valid_until' => 'nullable|date',
            'discount_amount' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
        ]);

        $validated['tenant_id'] = $request->user()->company_id;

        $quote = $this->cpq->createQuote($validated);
        $quote->load(['contact', 'opportunity', 'lines']);

        return response()->json(new QuoteResource($quote), 201);
    }

    /**
     * Show a quote with lines.
     */
    public function show(Quote $quote): JsonResponse
    {
        $this->authorize('view', $quote);

        $quote->load(['contact', 'opportunity', 'lines.productBundle']);

        return response()->json(new QuoteResource($quote));
    }

    /**
     * Update a quote.
     */
    public function update(Request $request, Quote $quote): JsonResponse
    {
        $this->authorize('update', $quote);

        $validated = $request->validate([
            'opportunity_id' => 'nullable|exists:crm_opportunities,id',
            'contact_id' => 'nullable|exists:crm_contacts,id',
            'status' => 'nullable|in:draft,sent,accepted,rejected,expired',
            'valid_until' => 'nullable|date',
            'discount_amount' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
        ]);

        $quote->update($validated);

        if (isset($validated['discount_amount'])) {
            $this->cpq->recalculate($quote);
        }

        $quote->load(['contact', 'opportunity', 'lines']);

        return response()->json(new QuoteResource($quote));
    }

    /**
     * Soft-delete a quote.
     */
    public function destroy(Quote $quote): JsonResponse
    {
        $this->authorize('delete', $quote);

        $quote->delete();

        return response()->json(status: 200);
    }

    /**
     * Add a line to a quote.
     */
    public function addLine(Request $request, Quote $quote): JsonResponse
    {
        $this->authorize('update', $quote);

        $validated = $request->validate([
            'product_bundle_id' => 'nullable|exists:crm_product_bundles,id',
            'description' => 'required|string|max:500',
            'quantity' => 'required|numeric|min:0.01',
            'unit_price' => 'required|numeric|min:0',
            'discount_pct' => 'nullable|numeric|min:0|max:100',
        ]);

        $line = $this->cpq->addLine($quote, $validated);

        return response()->json($line, 201);
    }

    /**
     * Return HTML representation of the quote for PDF.
     */
    public function pdf(Quote $quote): Response
    {
        $this->authorize('view', $quote);

        $html = $this->cpq->generatePdf($quote);

        return response($html)->header('Content-Type', 'text/html; charset=UTF-8');
    }

    /**
     * Duplicate a quote.
     */
    public function duplicate(Quote $quote): JsonResponse
    {
        $this->authorize('view', $quote);

        $newQuote = $this->cpq->duplicate($quote);
        $newQuote->load(['contact', 'opportunity', 'lines']);

        return response()->json(new QuoteResource($newQuote), 201);
    }
}
