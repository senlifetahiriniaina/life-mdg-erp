<?php

declare(strict_types=1);

namespace Modules\Logistics\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Logistics\Models\HsCode;

class HsCodeController extends Controller
{
    /**
     * Search HS codes by keyword or code prefix, optionally filtered by chapter.
     *
     * GET /api/v1/logistics/hs-codes/search?q=cotton&lang=fr|en&chapter=52
     */
    public function search(Request $request): JsonResponse
    {
        $query = HsCode::query();

        $term = (string) ($request->query('q', ''));
        if ($term !== '') {
            $query->searchByCode($term);
        }

        $chapter = (string) ($request->query('chapter', ''));
        if ($chapter !== '') {
            $query->chapter($chapter);
        }

        $lang = (string) ($request->query('lang', 'fr'));
        $descriptionField = $lang === 'en' ? 'description_en' : 'description_fr';

        $results = $query
            ->orderBy('code')
            ->paginate(50);

        return response()->json([
            'data' => $results->getCollection()->map(fn (HsCode $hs) => [
                'code'             => $hs->code,
                'description'      => $hs->{$descriptionField},
                'description_fr'   => $hs->description_fr,
                'description_en'   => $hs->description_en,
                'chapter'          => $hs->chapter,
                'section'          => $hs->section,
                'unit'             => $hs->unit,
                'duty_rate'        => $hs->duty_rate_default,
                'formatted_rate'   => $hs->formatted_rate,
                'vat_applicable'   => $hs->vat_applicable,
                'requires_license' => $hs->requires_license,
            ]),
            'meta' => [
                'current_page' => $results->currentPage(),
                'last_page'    => $results->lastPage(),
                'per_page'     => $results->perPage(),
                'total'        => $results->total(),
            ],
        ]);
    }

    /**
     * List all WCO chapters with code count and label.
     *
     * GET /api/v1/logistics/hs-codes/chapters
     */
    public function chapters(): JsonResponse
    {
        $chapters = HsCode::query()
            ->selectRaw('chapter, COUNT(*) as total, MIN(description_fr) as label_fr, MIN(description_en) as label_en')
            ->whereNotNull('chapter')
            ->groupBy('chapter')
            ->orderBy('chapter')
            ->get()
            ->map(fn (HsCode $row) => [
                'chapter'  => $row->chapter,
                'label_fr' => $row->label_fr,
                'label_en' => $row->label_en,
                'total'    => (int) $row->total,
            ]);

        return response()->json(['data' => $chapters]);
    }

    /**
     * Get a single HS code by exact code.
     *
     * GET /api/v1/logistics/hs-codes/{code}
     */
    public function show(string $code): JsonResponse
    {
        $hs = HsCode::where('code', $code)->first();

        if ($hs === null) {
            return response()->json([
                'message' => "HS code '{$code}' not found.",
            ], 404);
        }

        return response()->json([
            'data' => [
                'code'             => $hs->code,
                'description_fr'   => $hs->description_fr,
                'description_en'   => $hs->description_en,
                'chapter'          => $hs->chapter,
                'section'          => $hs->section,
                'unit'             => $hs->unit,
                'duty_rate'        => $hs->duty_rate_default,
                'formatted_rate'   => $hs->formatted_rate,
                'vat_applicable'   => $hs->vat_applicable,
                'requires_license' => $hs->requires_license,
                'notes'            => $hs->notes,
            ],
        ]);
    }
}
