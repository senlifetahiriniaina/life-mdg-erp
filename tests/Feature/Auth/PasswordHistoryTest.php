<?php

declare(strict_types=1);

use App\Models\PasswordHistory;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Auth\Notifications\ResetPassword;

test('profile password update rejects a recently used password', function () {
    $user = User::factory()->create(['password' => Hash::make('OldPassw0rd!')]);
    PasswordHistory::create(['user_id' => $user->id, 'password_hash' => $user->password]);

    $this->actingAs($user)
        ->from('/profile')
        ->patch('/profile/password', [
            'current_password' => 'OldPassw0rd!',
            'password' => 'OldPassw0rd!',
            'password_confirmation' => 'OldPassw0rd!',
        ])
        ->assertRedirect('/profile')
        ->assertSessionHasErrors('password');
});

test('profile password update accepts a new password and records it in history', function () {
    $user = User::factory()->create(['password' => Hash::make('OldPassw0rd!')]);

    $this->actingAs($user)
        ->patch('/profile/password', [
            'current_password' => 'OldPassw0rd!',
            'password' => 'BrandNewPassw0rd!',
            'password_confirmation' => 'BrandNewPassw0rd!',
        ])
        ->assertSessionDoesntHaveErrors('password');

    $user->refresh();
    expect(Hash::check('BrandNewPassw0rd!', $user->password))->toBeTrue();
    expect($user->passwordHistories()->count())->toBe(1);
    expect(Hash::check('BrandNewPassw0rd!', $user->passwordHistories()->first()->password_hash))->toBeTrue();
});

test('password reset rejects a recently used password', function () {
    Notification::fake();

    $user = User::factory()->create(['password' => Hash::make('OldPassw0rd!')]);
    PasswordHistory::create(['user_id' => $user->id, 'password_hash' => $user->password]);

    $token = null;
    Notification::assertNothingSent();
    $token = \Illuminate\Support\Facades\Password::createToken($user);

    $this->from('/reset-password/'.$token)
        ->post('/reset-password', [
            'token' => $token,
            'email' => $user->email,
            'password' => 'OldPassw0rd!',
            'password_confirmation' => 'OldPassw0rd!',
        ])
        ->assertSessionHasErrors('password');
});

test('password reset accepts a new password and records it in history', function () {
    $user = User::factory()->create(['password' => Hash::make('OldPassw0rd!')]);
    $token = \Illuminate\Support\Facades\Password::createToken($user);

    $this->post('/reset-password', [
        'token' => $token,
        'email' => $user->email,
        'password' => 'BrandNewPassw0rd!',
        'password_confirmation' => 'BrandNewPassw0rd!',
    ])->assertRedirect(route('login'));

    $user->refresh();
    expect(Hash::check('BrandNewPassw0rd!', $user->password))->toBeTrue();
    expect($user->passwordHistories()->count())->toBe(1);
});
