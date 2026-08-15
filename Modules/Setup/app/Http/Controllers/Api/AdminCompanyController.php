<?php

declare(strict_types=1);

namespace Modules\Setup\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Validation\Rule;
use Modules\Setup\Models\CompanyProfile;

/**
 * AdminCompanyController
 *
 * Admin-only company profile management.
 * Routes prefix: /api/v1/admin/company
 */
class AdminCompanyController extends Controller
{
    // -----------------------------------------------------------------------
    // GET /admin/company
    // -----------------------------------------------------------------------

    public function show(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id ?? 'default';

        $profile = CompanyProfile::forTenant($tenantId)->first();

        return response()->json([
            'success' => true,
            'data'    => $profile,
        ]);
    }

    // -----------------------------------------------------------------------
    // PUT /admin/company
    // -----------------------------------------------------------------------

    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'company_name'      => 'sometimes|required|string|max:255',
            'legal_name'        => 'nullable|string|max:255',
            'company_type'      => ['nullable', Rule::in(['sarl', 'sa', 'sas', 'spa', 'gmbh', 'ltd', 'plc', 'other'])],
            'industry'          => 'nullable|string|max:100',
            'country_code'      => 'sometimes|required|string|size:2',
            'currency_code'     => 'nullable|string|size:3',
            'timezone'          => 'nullable|string|max:100',
            'fiscal_year_start' => 'nullable|integer|min:1|max:12',
            'phone'             => 'nullable|string|max:50',
            'email'             => 'nullable|email|max:255',
            'website'           => 'nullable|url|max:255',
            'address'           => 'nullable|string|max:500',
            'city'              => 'nullable|string|max:100',
            'postal_code'       => 'nullable|string|max:20',
            'vat_number'        => 'nullable|string|max:50',
        ]);

        $tenantId = $request->user()->tenant_id ?? 'default';

        /** @var CompanyProfile $profile */
        $profile = CompanyProfile::forTenant($tenantId)->firstOrCreate(
            ['tenant_id' => $tenantId],
            ['company_name' => $validated['company_name'] ?? 'My Company', 'country_code' => $validated['country_code'] ?? 'SN']
        );

        $profile->fill($validated);
        $profile->save();

        return response()->json([
            'success' => true,
            'data'    => $profile->fresh(),
        ]);
    }

    // -----------------------------------------------------------------------
    // POST /admin/company/logo
    // -----------------------------------------------------------------------

    public function uploadLogo(Request $request): JsonResponse
    {
        $request->validate([
            'logo' => 'required|image|mimes:jpeg,png,gif,webp|max:2048',
        ]);

        $tenantId = $request->user()->tenant_id ?? 'default';

        $path = $request->file('logo')->store('logos', 'public');

        // Update or create profile with new logo path
        $profile = CompanyProfile::forTenant($tenantId)->first();

        if ($profile) {
            $profile->logo_path = $path;
            $profile->save();
        }

        return response()->json([
            'success' => true,
            'data'    => [
                'logo_path' => $path,
                'url'       => asset("storage/{$path}"),
            ],
        ]);
    }
}
