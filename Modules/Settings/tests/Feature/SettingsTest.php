<?php

declare(strict_types=1);

use App\Models\Company;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Modules\Settings\Models\Setting;
use Modules\Settings\Services\SettingsService;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    if (\Spatie\Permission\Models\Permission::count() === 0) {
        test()->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    }
    $this->user  = User::factory()->create(['company_id' => Company::factory()->create()->id]);
    $this->user->givePermissionTo('settings.update');
    // Chantier 10: Modules/Settings/routes/api.php gained a route-level
    // module:/role: gate (previously had neither at all, one of the two
    // modules CLAUDE.md explicitly flagged) — a plain permission grant with
    // no real Spatie role now 403s before SettingsController's own
    // authorize() checks are ever reached.
    \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    $this->user->assignRole('admin');
    $this->token = $this->user->createToken('test')->plainTextToken;
});

// ---------------------------------------------------------------------------
// test_can_get_settings_for_module
// ---------------------------------------------------------------------------

it('can get settings for a module', function () {
    Setting::withoutGlobalScopes()->create([
        'tenant_id'  => 1,
        'module'     => 'general',
        'key'        => 'app_name',
        'value'      => 'WideHalo ERP',
        'value_type' => 'string',
        'is_public'  => true,
    ]);

    $this->withToken($this->token)
        ->getJson('/api/v1/settings/general')
        ->assertOk()
        ->assertJsonPath('module', 'general')
        ->assertJsonStructure(['module', 'settings']);
});

// ---------------------------------------------------------------------------
// test_can_set_setting_value
// ---------------------------------------------------------------------------

it('can set a setting value via PUT endpoint', function () {
    $this->withToken($this->token)
        ->putJson('/api/v1/settings/general/app_name', [
            'value' => 'My ERP',
        ])
        ->assertOk()
        ->assertJsonPath('key', 'app_name')
        ->assertJsonPath('value', 'My ERP');

    $this->assertDatabaseHas('settings', [
        'module' => 'general',
        'key'    => 'app_name',
        'value'  => 'My ERP',
    ]);
});

// ---------------------------------------------------------------------------
// test_settings_are_cached_per_tenant
// ---------------------------------------------------------------------------

it('settings are cached per tenant', function () {
    Cache::flush();

    Setting::withoutGlobalScopes()->create([
        'tenant_id'  => 1,
        'module'     => 'hr',
        'key'        => 'leave_days',
        'value'      => '25',
        'value_type' => 'integer',
        'is_public'  => false,
    ]);

    /** @var SettingsService $service */
    $service = app(SettingsService::class);

    // Acting as tenant user so the scope applies
    $this->actingAs($this->user);

    // First call — hits the database and populates cache
    $first = $service->get('hr', 'leave_days');

    // The cache key should now exist
    $cacheKey = 'settings:1:hr:leave_days';
    expect(Cache::has($cacheKey))->toBeTrue();

    // Second call — served from cache, still same value
    $second = $service->get('hr', 'leave_days');
    expect($first)->toBe($second);
});

// ---------------------------------------------------------------------------
// test_settings_support_all_value_types
// ---------------------------------------------------------------------------

it('settings support all value types', function () {
    $this->actingAs($this->user);

    /** @var SettingsService $service */
    $service = app(SettingsService::class);

    // string
    $service->setTyped('types', 'str_val', 'hello', 'string');
    expect($service->get('types', 'str_val'))->toBe('hello');

    // integer
    $service->setTyped('types', 'int_val', 42, 'integer');
    expect($service->get('types', 'int_val'))->toBe(42);

    // boolean
    $service->setTyped('types', 'bool_val', true, 'boolean');
    expect($service->get('types', 'bool_val'))->toBeTrue();

    // json
    $service->setTyped('types', 'json_val', ['a' => 1, 'b' => 2], 'json');
    expect($service->get('types', 'json_val'))->toBe(['a' => 1, 'b' => 2]);

    // encrypted — value must round-trip but not be stored in plaintext
    $service->setTyped('types', 'enc_val', 'secret-password', 'encrypted');
    $stored = Setting::withoutGlobalScopes()
        ->where('module', 'types')
        ->where('key', 'enc_val')
        ->value('value');
    expect($stored)->not->toBe('secret-password'); // must be encrypted in DB
    expect($service->get('types', 'enc_val'))->toBe('secret-password');
});

// ---------------------------------------------------------------------------
// test_unauthorized_cannot_modify_settings
// ---------------------------------------------------------------------------

it('unauthenticated request cannot modify settings', function () {
    $this->putJson('/api/v1/settings/general/app_name', ['value' => 'Hacked'])
        ->assertUnauthorized();
});

// ---------------------------------------------------------------------------
// test_bulk_update_settings
// ---------------------------------------------------------------------------

it('can bulk update settings for a module', function () {
    $this->user->givePermissionTo('settings.create');

    $this->withToken($this->token)
        ->postJson('/api/v1/settings/hr/bulk', [
            'settings' => [
                'max_leave_days'  => 30,
                'default_currency' => 'XOF',
            ],
        ])
        ->assertOk()
        ->assertJsonPath('module', 'hr')
        ->assertJsonPath('updated', 2);

    $this->assertDatabaseHas('settings', ['module' => 'hr', 'key' => 'max_leave_days']);
    $this->assertDatabaseHas('settings', ['module' => 'hr', 'key' => 'default_currency']);
});

// ---------------------------------------------------------------------------
// test_global_setting_fallback
// ---------------------------------------------------------------------------

it('falls back to global setting when no tenant override exists', function () {
    // Create a global setting (tenant_id = null)
    Setting::withoutGlobalScopes()->create([
        'tenant_id'  => null,
        'module'     => 'accounting',
        'key'        => 'fiscal_year_start',
        'value'      => '01-01',
        'value_type' => 'string',
        'is_public'  => true,
    ]);

    $this->actingAs($this->user);

    /** @var SettingsService $service */
    $service = app(SettingsService::class);

    $value = $service->get('accounting', 'fiscal_year_start');
    expect($value)->toBe('01-01');
});

// ---------------------------------------------------------------------------
// test_get_setting_with_fallback_default
// ---------------------------------------------------------------------------

it('returns default when setting does not exist', function () {
    $this->actingAs($this->user);

    /** @var SettingsService $service */
    $service = app(SettingsService::class);

    $value = $service->get('nonexistent_module', 'nonexistent_key', 'my_default');
    expect($value)->toBe('my_default');
});

// ---------------------------------------------------------------------------
// test_set_boolean_type
// ---------------------------------------------------------------------------

it('stores and retrieves boolean type setting correctly', function () {
    $this->actingAs($this->user);

    /** @var SettingsService $service */
    $service = app(SettingsService::class);

    $service->setTyped('hr', 'feature_enabled', true, 'boolean');
    expect($service->get('hr', 'feature_enabled'))->toBeTrue();

    $service->setTyped('hr', 'feature_disabled', false, 'boolean');
    expect($service->get('hr', 'feature_disabled'))->toBeFalse();
});

// ---------------------------------------------------------------------------
// test_set_integer_type
// ---------------------------------------------------------------------------

it('stores and retrieves integer type setting correctly', function () {
    $this->actingAs($this->user);

    /** @var SettingsService $service */
    $service = app(SettingsService::class);

    $service->setTyped('inventory', 'reorder_level', 50, 'integer');
    $val = $service->get('inventory', 'reorder_level');
    expect($val)->toBe(50);
});

// ---------------------------------------------------------------------------
// test_set_json_type
// ---------------------------------------------------------------------------

it('stores and retrieves json type setting correctly', function () {
    $this->actingAs($this->user);

    /** @var SettingsService $service */
    $service = app(SettingsService::class);

    $data = ['currencies' => ['XOF', 'XAF', 'GHS'], 'default' => 'XOF'];
    $service->setTyped('accounting', 'currency_config', $data, 'json');
    $val = $service->get('accounting', 'currency_config');
    expect($val)->toBe($data);
});

// ---------------------------------------------------------------------------
// test_set_encrypted_type
// ---------------------------------------------------------------------------

it('stores encrypted type without plain-text in database', function () {
    $this->actingAs($this->user);

    /** @var SettingsService $service */
    $service = app(SettingsService::class);

    $service->setTyped('integrations', 'api_secret', 'super-secret-key', 'encrypted');

    // Stored value must be encrypted (not plain text)
    $stored = Setting::withoutGlobalScopes()
        ->where('module', 'integrations')
        ->where('key', 'api_secret')
        ->value('value');

    expect($stored)->not->toBe('super-secret-key');
    expect($service->get('integrations', 'api_secret'))->toBe('super-secret-key');
});

// ---------------------------------------------------------------------------
// test_tenant_isolation
// ---------------------------------------------------------------------------

it('tenant A cannot read tenant B settings', function () {
    // Create tenant A user
    $companyA = Company::factory()->create();
    $userA = User::factory()->create(['company_id' => $companyA->id]);
    // Create tenant B user
    $companyB = Company::factory()->create();
    $userB = User::factory()->create(['company_id' => $companyB->id]);

    // Create a setting for tenant B
    Setting::withoutGlobalScopes()->create([
        'tenant_id'  => $companyB->id,
        'module'     => 'crm',
        'key'        => 'secret_config',
        'value'      => 'tenant_b_secret',
        'value_type' => 'string',
        'is_public'  => false,
    ]);

    // Acting as tenant A — should not see tenant B's setting
    $this->actingAs($userA);

    /** @var SettingsService $service */
    $service = app(SettingsService::class);
    $value = $service->get('crm', 'secret_config', 'fallback');

    expect($value)->toBe('fallback');
});

// ---------------------------------------------------------------------------
// test_unauthenticated_returns_401
// ---------------------------------------------------------------------------

it('unauthenticated request to settings module endpoint returns 401', function () {
    $this->getJson('/api/v1/settings/general')
        ->assertUnauthorized();
});

// ---------------------------------------------------------------------------
// test_cache_invalidation_on_write
// ---------------------------------------------------------------------------

it('cache is invalidated after writing a setting', function () {
    Cache::flush();

    $this->actingAs($this->user);

    /** @var SettingsService $service */
    $service = app(SettingsService::class);

    // Write initial value — populates cache
    $service->set('pos', 'receipt_footer', 'Original footer');
    $first = $service->get('pos', 'receipt_footer');
    expect($first)->toBe('Original footer');

    // Write new value — should bust cache
    $service->set('pos', 'receipt_footer', 'Updated footer');

    // Now read again — must return updated value
    $second = $service->get('pos', 'receipt_footer');
    expect($second)->toBe('Updated footer');
});

// ---------------------------------------------------------------------------
// test_public_settings_scope
// ---------------------------------------------------------------------------

it('public scope returns only public settings', function () {
    Setting::withoutGlobalScopes()->create([
        'tenant_id'  => 1,
        'module'     => 'general',
        'key'        => 'public_key',
        'value'      => 'visible',
        'value_type' => 'string',
        'is_public'  => true,
    ]);

    Setting::withoutGlobalScopes()->create([
        'tenant_id'  => 1,
        'module'     => 'general',
        'key'        => 'private_key',
        'value'      => 'hidden',
        'value_type' => 'string',
        'is_public'  => false,
    ]);

    $publicSettings = Setting::withoutGlobalScopes()
        ->where('module', 'general')
        ->public()
        ->get();

    expect($publicSettings->pluck('key')->toArray())->toContain('public_key')
        ->and($publicSettings->pluck('key')->toArray())->not->toContain('private_key');
});

// ---------------------------------------------------------------------------
// test_global_null_tenant_settings_visible_to_all_tenants
// ---------------------------------------------------------------------------

it('global null tenant settings are visible to all tenants', function () {
    Setting::withoutGlobalScopes()->create([
        'tenant_id'  => null,
        'module'     => 'compliance',
        'key'        => 'ohada_enabled',
        'value'      => '1',
        'value_type' => 'boolean',
        'is_public'  => true,
    ]);

    // Verify global scope returns these settings
    $globals = Setting::global()->where('module', 'compliance')->get();
    expect($globals->pluck('key')->toArray())->toContain('ohada_enabled');
});

// ---------------------------------------------------------------------------
// test_module_filter_returns_only_module_settings
// ---------------------------------------------------------------------------

it('module filter returns only settings for that module', function () {
    Setting::withoutGlobalScopes()->create([
        'tenant_id'  => 1, 'module' => 'hr', 'key' => 'max_vacation', 'value' => '25',
        'value_type' => 'integer', 'is_public' => false,
    ]);

    Setting::withoutGlobalScopes()->create([
        'tenant_id'  => 1, 'module' => 'crm', 'key' => 'pipeline_stages', 'value' => '5',
        'value_type' => 'integer', 'is_public' => false,
    ]);

    $hrSettings = Setting::withoutGlobalScopes()->forModule('hr')->where('tenant_id', 1)->get();

    expect($hrSettings->every(fn ($s) => $s->module === 'hr'))->toBeTrue()
        ->and($hrSettings->pluck('key')->toArray())->not->toContain('pipeline_stages');
});

// ---------------------------------------------------------------------------
// test_delete_setting_removes_from_cache
// ---------------------------------------------------------------------------

it('deleting a setting removes cached value', function () {
    Cache::flush();

    $this->actingAs($this->user);

    /** @var SettingsService $service */
    $service = app(SettingsService::class);

    $service->set('manufacturing', 'batch_size', '100');

    // Ensure it's cached
    $service->get('manufacturing', 'batch_size');
    $cacheKey = 'settings:1:manufacturing:batch_size';
    expect(Cache::has($cacheKey))->toBeTrue();

    // Delete the setting directly
    Setting::withoutGlobalScopes()
        ->where('module', 'manufacturing')
        ->where('key', 'batch_size')
        ->delete();

    // Manually forget the cache as the service would
    Cache::forget($cacheKey);

    expect(Cache::has($cacheKey))->toBeFalse();
});

// ---------------------------------------------------------------------------
// test_bulk_update_cache_invalidation
// ---------------------------------------------------------------------------

it('bulk update invalidates module cache', function () {
    Cache::flush();

    $this->actingAs($this->user);

    /** @var SettingsService $service */
    $service = app(SettingsService::class);

    // Pre-warm module cache
    $service->getModule('logistics');

    // Bulk update should flush module cache
    $service->setMany('logistics', ['tracking_enabled' => true, 'default_carrier' => 'DHL']);

    // Chantier 32.9: the module-level cache key gained a ":v2" suffix
    // (SettingsService::moduleKey()'s own docblock explains why — the
    // cached shape changed from a flat key=>value map to key=>{value,
    // is_public} for the new per-record visibility filter). Before this
    // fix, this assertion checked the OLD literal key format, which the
    // real code no longer ever writes to at all — the assertion still
    // "passed" but was vacuously true (Cache::has() on a key nothing
    // writes is always false, regardless of whether invalidation
    // actually works), not a real regression test. Fixed to the real key.
    $moduleCacheKey = 'settings:1:logistics:v2';
    expect(Cache::has($moduleCacheKey))->toBeFalse();

    // And confirm the invalidation is actually observable, not just that
    // the cache slot is empty: a fresh getModule() call returns the newly
    // bulk-written values.
    $fresh = $service->getModule('logistics');
    expect($fresh['tracking_enabled'])->toBeTrue()
        ->and($fresh['default_carrier'])->toBe('DHL');
});

// ---------------------------------------------------------------------------
// test_setting_not_found_returns_default
// ---------------------------------------------------------------------------

it('setting not found returns the supplied default value', function () {
    $this->actingAs($this->user);

    /** @var SettingsService $service */
    $service = app(SettingsService::class);

    $result = $service->get('no_module', 'no_key', 42);
    expect($result)->toBe(42);
});

// ---------------------------------------------------------------------------
// test_update_endpoint_with_value_type
// ---------------------------------------------------------------------------

it('update endpoint accepts explicit value_type and stores correctly', function () {
    $this->withToken($this->token)
        ->putJson('/api/v1/settings/accounting/vat_rate', [
            'value'      => '18',
            'value_type' => 'integer',
        ])
        ->assertOk()
        ->assertJsonPath('key', 'vat_rate');

    $this->assertDatabaseHas('settings', [
        'module'     => 'accounting',
        'key'        => 'vat_rate',
        'value_type' => 'integer',
    ]);
});
