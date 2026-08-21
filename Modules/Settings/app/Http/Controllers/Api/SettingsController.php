<?php

declare(strict_types=1);

namespace Modules\Settings\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Settings\Models\Setting;
use Modules\Settings\Services\SettingsService;

/**
 * @group Settings
 *
 * Manage global and per-tenant configuration settings.
 */
class SettingsController extends Controller
{
    public function __construct(
        private readonly SettingsService $service,
    ) {}

    /**
     * GET /api/v1/settings
     *
     * List all settings (admin only).
     *
     * Chantier 32.9 (14-layer deep audit, layer 6 — security): confirmed
     * empirically that this was a real cross-tenant leak, the same class of
     * bug documented dozens of times elsewhere in this app's history —
     * `SettingPolicy::viewAll()` gates on the plain `admin` role, which in
     * this app's convention is a *per-company* role (unlike `super-admin`,
     * which alone is meant to see every tenant, and already bypasses every
     * Gate check via Gate::before regardless of what this method does) —
     * but the query itself had `withoutGlobalScopes()` and no company_id
     * filter of any kind, so any company's `admin` could list every other
     * company's settings, including whatever an `encrypted` value_type
     * setting's ciphertext or a non-public setting's raw value happens to
     * hold. Fixed to scope to the caller's own company unless they are a
     * real super-admin.
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAll', Setting::class);

        $query = Setting::withoutGlobalScopes()
            ->orderBy('module')
            ->orderBy('key');

        if (! $request->user()?->hasRole('super-admin')) {
            $tenantId = $request->user()?->company_id;
            $query->where(function ($q) use ($tenantId) {
                if ($tenantId) {
                    $q->where('tenant_id', $tenantId)->orWhereNull('tenant_id');
                } else {
                    $q->whereNull('tenant_id');
                }
            });
        }

        $settings = $query->paginate(100);

        return response()->json($settings);
    }

    /**
     * GET /api/v1/settings/{module}
     *
     * Return all settings for the given module scoped to the current tenant.
     */
    public function showModule(string $module): JsonResponse
    {
        $this->authorize('viewAny', Setting::class);

        $settings = $this->service->getModule($module);

        return response()->json([
            'module'   => $module,
            'settings' => $settings,
        ]);
    }

    /**
     * PUT /api/v1/settings/{module}/{key}
     *
     * Update (or create) a single setting for the current tenant.
     */
    public function update(Request $request, string $module, string $key): JsonResponse
    {
        $tenantId = auth()?->user()?->company_id;
        $setting  = Setting::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('module', $module)
            ->where('key', $key)
            ->first() ?? new Setting(['tenant_id' => $tenantId, 'module' => $module, 'key' => $key]);

        $this->authorize('update', $setting);

        // Chantier 32.9 (14-layer deep audit, layer 8 — business validation):
        // `value` previously had no type-conformance rule of any kind beyond
        // `required` — a caller declaring `value_type=boolean` could submit
        // any arbitrary string, silently mangled by encodeValue()/setTyped()'s
        // truthy-cast rather than rejected. Confirmed empirically via
        // tinker: `value="false"` (a non-empty string, PHP-truthy) with
        // `value_type=boolean` silently stored as `true` — the exact
        // "boolean setting set to an arbitrary string" gap this audit's own
        // checklist named. Fixed with rules keyed off the declared
        // value_type, matching what a client that actually sends a real JS
        // boolean/int already produces, and rejecting (422) a client that
        // sends a type-mismatched string instead of silently corrupting it.
        $valueRules = match ($request->input('value_type')) {
            'boolean' => ['required', 'boolean'],
            'integer' => ['required', 'integer'],
            default   => ['required'],
        };

        $validated = $request->validate([
            'value'      => $valueRules,
            'value_type' => ['sometimes', 'in:string,integer,boolean,json,encrypted'],
            'description' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'is_public'  => ['sometimes', 'boolean'],
        ]);

        $valueType = $validated['value_type'] ?? null;

        if ($valueType !== null) {
            $this->service->setTyped($module, $key, $validated['value'], $valueType);
        } else {
            $this->service->set($module, $key, $validated['value']);
        }

        // Optionally update meta fields. Re-fetch: set()/setTyped() above may
        // have just created the row via updateOrCreate(), so the pre-write
        // $setting resolved for authorize() above may now be stale/missing.
        if (isset($validated['description']) || isset($validated['is_public'])) {
            $setting = Setting::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('module', $module)
                ->where('key', $key)
                ->first();

            if ($setting) {
                $setting->fill(array_filter([
                    'description' => $validated['description'] ?? null,
                    'is_public'   => $validated['is_public'] ?? null,
                ], fn ($v) => $v !== null))->save();
            }
        }

        return response()->json([
            'module'  => $module,
            'key'     => $key,
            'value'   => $this->service->get($module, $key),
        ]);
    }

    /**
     * POST /api/v1/settings/{module}/bulk
     *
     * Update multiple settings at once for the current tenant.
     *
     * Body: { "settings": { "key1": value1, "key2": value2, ... } }
     */
    public function bulk(Request $request, string $module): JsonResponse
    {
        $this->authorize('create', Setting::class);

        $validated = $request->validate([
            'settings'   => ['required', 'array'],
            'settings.*' => ['present'],
        ]);

        $this->service->setMany($module, $validated['settings']);

        return response()->json([
            'module'   => $module,
            'updated'  => count($validated['settings']),
            'settings' => $this->service->getModule($module),
        ]);
    }
}
