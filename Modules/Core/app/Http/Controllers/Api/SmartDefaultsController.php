<?php

declare(strict_types=1);

namespace Modules\Core\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Core\Services\SmartDefaultsService;

/**
 * SmartDefaultsController
 *
 * Exposes country/industry smart defaults so front-end forms can pre-fill
 * tax rates, currencies, payment methods, and unit lists without asking the
 * user.  Part of the "Simplicity First" initiative.
 *
 * @group Smart Defaults — Simplicity First
 */
class SmartDefaultsController extends Controller
{
    public function __construct(private readonly SmartDefaultsService $service) {}

    /**
     * Get form defaults for a given context.
     *
     * Returns all pre-filled defaults (currency, tax rate, units, payment
     * methods …) for the given country, industry, and module combination.
     *
     * @queryParam country  string Country ISO code (default: SN). Example: SN
     * @queryParam industry string Industry key (default: general). Example: textile
     * @queryParam module   string Module name (default: sales). Example: sales
     *
     * @response 200 {
     *   "module": "sales",
     *   "country": "SN",
     *   "industry": "textile",
     *   "currency": "XOF",
     *   "taxRate": 18.0,
     *   "taxLabel": "TVA",
     *   "paymentMethods": ["orange_money", "wave", "mtn_momo", "cash", "bank_transfer"],
     *   "fiscalYearStart": 1,
     *   "accountingStd": "OHADA",
     *   "units": ["mètre", "rouleau", "pièce", "kg"],
     *   "mobileCountryCode": "+221"
     * }
     */
    public function show(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'country'  => ['sometimes', 'string', 'size:2'],
            'industry' => ['sometimes', 'string', 'max:50'],
            'module'   => ['sometimes', 'string', 'max:50'],
        ]);

        $country  = strtoupper($validated['country']  ?? 'SN');
        $industry = strtolower($validated['industry'] ?? 'general');
        $module   = $validated['module'] ?? 'sales';

        $defaults = $this->service->getDefaults($module, $country, $industry);

        return response()->json($defaults);
    }

    /**
     * List all supported countries with their default data.
     *
     * @response 200 {
     *   "SN": { "label": "Sénégal", "currency": "XOF", "tax_rate": 18.0, "..." },
     *   "KE": { "label": "Kenya",   "currency": "KES", "tax_rate": 16.0, "..." }
     * }
     */
    public function countries(): JsonResponse
    {
        return response()->json($this->service->allCountries());
    }

    /**
     * Toggle or set simple mode for the authenticated user.
     *
     * @bodyParam simple_mode boolean required Whether to enable simple mode. Example: true
     *
     * @response 200 {"simple_mode": true}
     */
    public function setSimpleMode(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'simple_mode' => ['required', 'boolean'],
        ]);

        /** @var \App\Models\User $user */
        $user = $request->user();

        app(\Modules\Core\Services\SimpleModeService::class)
            ->setMode($user->id, (bool) $validated['simple_mode']);

        return response()->json(['simple_mode' => $validated['simple_mode']]);
    }
}
