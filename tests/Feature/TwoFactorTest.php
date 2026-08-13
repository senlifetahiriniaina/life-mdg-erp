<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PragmaRX\Google2FA\Google2FA;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

function otpFor(string $secret): string
{
    return app(Google2FA::class)->getCurrentOtp($secret);
}

/** @return array{0:string,1:\App\Models\User} */
function enrollUserIn2fa(User $user): array
{
    $secret = app(Google2FA::class)->generateSecretKey();
    $user->forceFill([
        'google2fa_secret' => $secret,
        'two_factor_enabled' => true,
        'two_factor_confirmed_at' => now(),
        'two_factor_recovery_codes' => ['AAAAA-BBBBB', 'CCCCC-DDDDD'],
    ])->save();

    return [$secret, $user];
}

test('user can enrol and confirm 2FA', function () {
    $user = User::factory()->create();

    $setup = $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/auth/2fa/setup')
        ->assertOk()
        ->assertJsonStructure(['secret', 'otpauth_uri']);

    $secret = $setup->json('secret');

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/auth/2fa/confirm', ['code' => otpFor($secret)])
        ->assertOk()
        ->assertJsonStructure(['recovery_codes']);

    expect($user->fresh()->hasTwoFactorEnabled())->toBeTrue();
});

test('login issues a 2FA challenge instead of a token when 2FA is enabled', function () {
    // The Core-module HTTP login route is not registered in the test harness
    // (pre-existing module-route-loading gap — see Core\AuthTest), so exercise
    // the controller method directly to cover the branch added to login().
    [, $user] = enrollUserIn2fa(User::factory()->create(['password' => bcrypt('password123')]));

    $request = Modules\Core\Http\Requests\LoginRequest::create('/login', 'POST', [
        'email' => $user->email,
        'password' => 'password123',
    ]);
    $request->setContainer(app())->validateResolved();

    $payload = app(Modules\Core\Http\Controllers\Api\AuthController::class)
        ->login($request)
        ->getData(true);

    expect($payload['two_factor_required'] ?? null)->toBeTrue()
        ->and($payload['challenge_token'] ?? null)->not->toBeNull()
        ->and($payload)->not->toHaveKey('token');
});

test('a TOTP code completes the 2FA challenge and yields a real token', function () {
    [$secret, $user] = enrollUserIn2fa(User::factory()->create());
    $challenge = $user->createToken('2fa-challenge', ['2fa:challenge'])->plainTextToken;

    $this->withHeader('Authorization', "Bearer {$challenge}")
        ->postJson('/api/v1/auth/2fa/verify', ['code' => otpFor($secret)])
        ->assertOk()
        ->assertJsonStructure(['user', 'token']);
});

test('a recovery code completes the 2FA challenge and is consumed', function () {
    [, $user] = enrollUserIn2fa(User::factory()->create());
    $challenge = $user->createToken('2fa-challenge', ['2fa:challenge'])->plainTextToken;

    $this->withHeader('Authorization', "Bearer {$challenge}")
        ->postJson('/api/v1/auth/2fa/verify', ['code' => 'AAAAA-BBBBB'])
        ->assertOk()
        ->assertJsonStructure(['token']);

    expect($user->fresh()->two_factor_recovery_codes)->not->toContain('AAAAA-BBBBB');
});

test('admin without 2FA is blocked from protected routes until enrolment', function () {
    Role::findOrCreate('admin');
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $this->actingAs($admin, 'sanctum')
        ->getJson('/api/v1/dashboard')
        ->assertForbidden()
        ->assertJson(['two_factor_setup_required' => true]);
});

test('non-admin without 2FA is not gated by the 2FA middleware', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user, 'sanctum')->getJson('/api/v1/dashboard');

    expect($response->json('two_factor_setup_required'))->toBeNull();
});
