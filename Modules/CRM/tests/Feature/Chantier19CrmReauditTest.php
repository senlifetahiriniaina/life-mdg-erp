<?php

declare(strict_types=1);

use App\Models\Company;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Modules\CRM\Models\Account;
use Modules\CRM\Models\Contact;
use Modules\CRM\Models\Lead;
use Modules\CRM\Models\Opportunity;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Chantier 19 — CRM re-audit (empirical re-verification pass).
 *
 * CLAUDE.md's Chantier 10 CRM entry documented, but explicitly deferred, a severe finding:
 * ContactController/AccountController/LeadController/OpportunityController had zero
 * tenant/company scoping on index/show/update/destroy — any authenticated user of any company
 * could read, edit, or delete any other company's contacts/accounts/leads/opportunities.
 * Confirmed still true empirically via tinker before this fix (see the assistant's report for
 * this task): AccountPolicy in particular had a *policy that looked like it enforced tenant
 * isolation* (CrmAccountPolicy::sameTenant()) but compared the phantom users.tenant_id column
 * against a tenant_id column that never existed on crm_accounts at all — a permanently vacuous
 * '' === '' pass, not a missing check.
 *
 * This test locks in the fix: company-scoped index() listings, and policy-enforced
 * view/update/delete denial across a real HTTP request for all 4 core CRM entities.
 */
function crmReauditUser(string $companySuffix, string $role = 'employee'): User
{
    if (Permission::count() === 0) {
        test()->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    }

    Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);

    $company = Company::create([
        'name' => "Chantier19 Co {$companySuffix}",
        'currency' => 'MGA',
        'timezone' => 'Indian/Antananarivo',
    ]);

    $user = User::factory()->create(['company_id' => $company->id]);
    $user->forceFill([
        'two_factor_enabled' => true,
        'google2fa_secret' => 'JBSWY3DPEHPK3PXP',
        'two_factor_confirmed_at' => now(),
    ])->save();
    $user->assignRole($role);

    // module:CRM middleware gates on tenant_modules keyed by the user's own id (a separate,
    // per-user module-toggle concept — not the company_id tenant-data boundary this test is
    // about), matching tests/Pest.php's actingAsUser() convention.
    DB::table('tenant_modules')->updateOrInsert(
        ['tenant_id' => (string) $user->id, 'module' => 'CRM', 'department' => null],
        ['enabled' => true, 'settings' => '{}', 'updated_at' => now(), 'created_at' => now()]
    );

    return $user;
}

test('contact index only returns the caller company own contacts', function () {
    $userA = crmReauditUser('A');
    $userB = crmReauditUser('B');

    Contact::factory()->create(['company_id' => $userA->company_id, 'owner_id' => $userA->id, 'first_name' => 'Alice']);
    Contact::factory()->create(['company_id' => $userB->company_id, 'owner_id' => $userB->id, 'first_name' => 'Bob']);

    $response = test()->actingAs($userA, 'sanctum')->getJson('/api/v1/crm/contacts')->assertOk();

    $names = collect($response->json('data'))->pluck('first_name');
    expect($names)->toContain('Alice')->not->toContain('Bob');
});

test('company B cannot view, update, or delete company A contact', function () {
    $userA = crmReauditUser('A');
    $userB = crmReauditUser('B');

    $contact = Contact::factory()->create(['company_id' => $userA->company_id, 'owner_id' => $userA->id]);

    test()->actingAs($userB, 'sanctum')->getJson("/api/v1/crm/contacts/{$contact->id}")->assertForbidden();
    test()->actingAs($userB, 'sanctum')->putJson("/api/v1/crm/contacts/{$contact->id}", ['first_name' => 'Hacked'])->assertForbidden();
    test()->actingAs($userB, 'sanctum')->deleteJson("/api/v1/crm/contacts/{$contact->id}")->assertForbidden();

    expect($contact->fresh()->first_name)->not->toBe('Hacked');
});

test('company A can still view update and delete its own contact', function () {
    $userA = crmReauditUser('A');
    $contact = Contact::factory()->create(['company_id' => $userA->company_id, 'owner_id' => $userA->id]);

    test()->actingAs($userA, 'sanctum')->getJson("/api/v1/crm/contacts/{$contact->id}")->assertOk();
    test()->actingAs($userA, 'sanctum')->putJson("/api/v1/crm/contacts/{$contact->id}", ['first_name' => 'Updated'])
        ->assertOk()->assertJsonPath('first_name', 'Updated');
    test()->actingAs($userA, 'sanctum')->deleteJson("/api/v1/crm/contacts/{$contact->id}")->assertNoContent();
});

test('account index only returns the caller company own accounts', function () {
    $userA = crmReauditUser('A');
    $userB = crmReauditUser('B');

    Account::factory()->create(['company_id' => $userA->company_id, 'owner_id' => $userA->id, 'name' => 'Acme A']);
    Account::factory()->create(['company_id' => $userB->company_id, 'owner_id' => $userB->id, 'name' => 'Acme B']);

    $response = test()->actingAs($userA, 'sanctum')->getJson('/api/v1/crm/accounts')->assertOk();

    $names = collect($response->json('data'))->pluck('name');
    expect($names)->toContain('Acme A')->not->toContain('Acme B');
});

test('company B cannot view or update company A account (CrmAccountPolicy vacuous-pass regression)', function () {
    $userA = crmReauditUser('A');
    $userB = crmReauditUser('B');

    $account = Account::factory()->create(['company_id' => $userA->company_id, 'owner_id' => $userA->id]);

    // Before the fix, this was a real, empirically-confirmed bypass: CrmAccountPolicy compared
    // the always-null users.tenant_id against a tenant_id column crm_accounts never had, so
    // both sides were '' and the check always passed regardless of company.
    test()->actingAs($userB, 'sanctum')->getJson("/api/v1/crm/accounts/{$account->id}")->assertForbidden();
    test()->actingAs($userB, 'sanctum')->putJson("/api/v1/crm/accounts/{$account->id}", ['name' => 'Hacked'])->assertForbidden();

    expect($account->fresh()->name)->not->toBe('Hacked');
});

test('company A can still view and update its own account', function () {
    $userA = crmReauditUser('A');
    $account = Account::factory()->create(['company_id' => $userA->company_id, 'owner_id' => $userA->id]);

    test()->actingAs($userA, 'sanctum')->getJson("/api/v1/crm/accounts/{$account->id}")->assertOk();
    test()->actingAs($userA, 'sanctum')->putJson("/api/v1/crm/accounts/{$account->id}", ['name' => 'Renamed'])
        ->assertOk()->assertJsonPath('name', 'Renamed');
});

test('lead index only returns the caller company own leads', function () {
    $userA = crmReauditUser('A');
    $userB = crmReauditUser('B');

    Lead::create(['title' => 'Lead A', 'owner_id' => $userA->id, 'company_id' => $userA->company_id]);
    Lead::create(['title' => 'Lead B', 'owner_id' => $userB->id, 'company_id' => $userB->company_id]);

    $response = test()->actingAs($userA, 'sanctum')->getJson('/api/v1/crm/leads')->assertOk();

    $titles = collect($response->json('data'))->pluck('title');
    expect($titles)->toContain('Lead A')->not->toContain('Lead B');
});

test('company B cannot view update or delete company A lead', function () {
    $userA = crmReauditUser('A');
    $userB = crmReauditUser('B');

    $lead = Lead::create(['title' => 'Confidential Lead', 'owner_id' => $userA->id, 'company_id' => $userA->company_id]);

    test()->actingAs($userB, 'sanctum')->getJson("/api/v1/crm/leads/{$lead->id}")->assertForbidden();
    test()->actingAs($userB, 'sanctum')->putJson("/api/v1/crm/leads/{$lead->id}", ['title' => 'Hacked'])->assertForbidden();
    test()->actingAs($userB, 'sanctum')->deleteJson("/api/v1/crm/leads/{$lead->id}")->assertForbidden();

    expect($lead->fresh()->title)->not->toBe('Hacked');
});

test('opportunity index only returns the caller company own opportunities', function () {
    $userA = crmReauditUser('A');
    $userB = crmReauditUser('B');

    Opportunity::create(['name' => 'Deal A', 'owner_id' => $userA->id, 'tenant_id' => $userA->company_id, 'amount' => 1000, 'status' => 'open', 'stage' => 'lead']);
    Opportunity::create(['name' => 'Deal B', 'owner_id' => $userB->id, 'tenant_id' => $userB->company_id, 'amount' => 2000, 'status' => 'open', 'stage' => 'lead']);

    $response = test()->actingAs($userA, 'sanctum')->getJson('/api/v1/crm/opportunities')->assertOk();

    $names = collect($response->json('data'))->pluck('name');
    expect($names)->toContain('Deal A')->not->toContain('Deal B');
});

test('company B cannot view update or delete company A opportunity', function () {
    $userA = crmReauditUser('A');
    $userB = crmReauditUser('B');

    $opp = Opportunity::create(['name' => 'Secret Deal', 'owner_id' => $userA->id, 'tenant_id' => $userA->company_id, 'amount' => 5000, 'status' => 'open', 'stage' => 'lead']);

    test()->actingAs($userB, 'sanctum')->getJson("/api/v1/crm/opportunities/{$opp->id}")->assertForbidden();
    test()->actingAs($userB, 'sanctum')->putJson("/api/v1/crm/opportunities/{$opp->id}", ['name' => 'Hacked'])->assertForbidden();
    test()->actingAs($userB, 'sanctum')->deleteJson("/api/v1/crm/opportunities/{$opp->id}")->assertForbidden();

    expect($opp->fresh()->name)->not->toBe('Hacked');
});

test('opportunity store populates tenant_id from the acting user company', function () {
    $userA = crmReauditUser('A');

    $response = test()->actingAs($userA, 'sanctum')->postJson('/api/v1/crm/opportunities', [
        'name' => 'New Deal',
        'amount' => 1500,
    ])->assertCreated();

    $opp = Opportunity::find($response->json('id'));
    expect((int) $opp->tenant_id)->toBe((int) $userA->company_id);
});

test('company A can still view update and delete its own opportunity', function () {
    $userA = crmReauditUser('A');
    $opp = Opportunity::create(['name' => 'My Deal', 'owner_id' => $userA->id, 'tenant_id' => $userA->company_id, 'amount' => 3000, 'status' => 'open', 'stage' => 'lead']);

    test()->actingAs($userA, 'sanctum')->getJson("/api/v1/crm/opportunities/{$opp->id}")->assertOk();
    test()->actingAs($userA, 'sanctum')->putJson("/api/v1/crm/opportunities/{$opp->id}", ['name' => 'Updated Deal'])
        ->assertOk()->assertJsonPath('name', 'Updated Deal');
    test()->actingAs($userA, 'sanctum')->deleteJson("/api/v1/crm/opportunities/{$opp->id}")->assertNoContent();
});

test('contact and account creation populate company_id from the acting user', function () {
    $userA = crmReauditUser('A');

    $contactResp = test()->actingAs($userA, 'sanctum')->postJson('/api/v1/crm/contacts', [
        'first_name' => 'New', 'last_name' => 'Contact',
    ])->assertCreated();
    $accountResp = test()->actingAs($userA, 'sanctum')->postJson('/api/v1/crm/accounts', [
        'name' => 'New Account',
    ])->assertCreated();

    expect((int) Contact::find($contactResp->json('id'))->company_id)->toBe((int) $userA->company_id);
    expect((int) Account::find($accountResp->json('id'))->company_id)->toBe((int) $userA->company_id);
});

// ── Web (Inertia) controller: a second, separate code path with the same headline gap ──────

test('web contacts index page only lists the caller company own contacts', function () {
    $userA = crmReauditUser('A');
    $userB = crmReauditUser('B');

    Contact::factory()->create(['company_id' => $userA->company_id, 'owner_id' => $userA->id, 'first_name' => 'Alice']);
    Contact::factory()->create(['company_id' => $userB->company_id, 'owner_id' => $userB->id, 'first_name' => 'Bob']);

    $response = test()->actingAs($userA)->get('/crm/contacts')->assertOk();

    $response->assertInertia(function ($page) {
        $names = collect($page->toArray()['props']['contacts']['data'])->pluck('full_name');
        expect($names->filter(fn ($n) => str_starts_with($n, 'Alice')))->toHaveCount(1);
        expect($names->filter(fn ($n) => str_starts_with($n, 'Bob')))->toHaveCount(0);
    });
});

test('web contact show page denies company B viewing company A contact', function () {
    $userA = crmReauditUser('A');
    $userB = crmReauditUser('B');

    $contact = Contact::factory()->create(['company_id' => $userA->company_id, 'owner_id' => $userA->id]);

    test()->actingAs($userB)->get("/crm/contacts/{$contact->id}")->assertForbidden();
    test()->actingAs($userA)->get("/crm/contacts/{$contact->id}")->assertOk();
});

test('web account edit/update denies company B against company A account', function () {
    $userA = crmReauditUser('A');
    $userB = crmReauditUser('B');

    $account = Account::factory()->create(['company_id' => $userA->company_id, 'owner_id' => $userA->id]);

    test()->actingAs($userB)->get("/crm/accounts/{$account->id}/edit")->assertForbidden();
    test()->actingAs($userB)->put("/crm/accounts/{$account->id}", ['name' => 'Hacked'])->assertForbidden();

    expect($account->fresh()->name)->not->toBe('Hacked');
});

test('web account store populates company_id from the acting user', function () {
    $userA = crmReauditUser('A');

    test()->actingAs($userA)->post('/crm/accounts', ['name' => 'Web Created Account'])
        ->assertRedirect();

    $account = Account::where('name', 'Web Created Account')->first();
    expect((int) $account->company_id)->toBe((int) $userA->company_id);
});
