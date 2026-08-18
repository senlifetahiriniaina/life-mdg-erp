<?php

declare(strict_types=1);

use App\Models\User;
use Modules\Integration\Models\IntegrationConnector;
use Modules\Integration\Policies\IntegrationConnectorPolicy;

beforeEach(function () {
    $this->policy = new IntegrationConnectorPolicy();
});

test('viewAny returns bool', function () {
    $user = Mockery::mock(User::class);
    $user->shouldReceive('can')->with('integration.connector.view-any')->andReturn(true);

    expect($this->policy->viewAny($user))->toBeBool()->toBeTrue();
});

test('view returns bool', function () {
    $user = Mockery::mock(User::class);
    $user->shouldReceive('can')->with('integration.connector.view')->andReturn(false);
    $connector = Mockery::mock(IntegrationConnector::class);

    // can() denies -> short-circuits before ownsTenant() ever touches
    // $user->company_id / $connector->tenant_id, so a plain (non-partial)
    // mock is fine here.
    expect($this->policy->view($user, $connector))->toBeBool()->toBeFalse();
});

test('create returns bool', function () {
    $user = Mockery::mock(User::class);
    $user->shouldReceive('can')->with('integration.connector.create')->andReturn(true);

    expect($this->policy->create($user))->toBeBool()->toBeTrue();
});

test('update returns bool', function () {
    $user = Mockery::mock(User::class);
    $user->shouldReceive('can')->with('integration.connector.update')->andReturn(false);
    $connector = Mockery::mock(IntegrationConnector::class);

    expect($this->policy->update($user, $connector))->toBeBool()->toBeFalse();
});

test('update allows only when permission granted AND connector belongs to the user\'s tenant', function () {
    // Chantier 8.6 IDOR fix: can()=true is no longer sufficient on its own —
    // ownsTenant() is also evaluated, so these need real (partial-mock)
    // attribute access rather than a fully-stubbed mock.
    $user = Mockery::mock(User::class)->makePartial();
    $user->shouldReceive('can')->with('integration.connector.update')->andReturn(true);
    $user->company_id = 7;

    $sameTenant = Mockery::mock(IntegrationConnector::class)->makePartial();
    $sameTenant->tenant_id = '7';
    expect($this->policy->update($user, $sameTenant))->toBeTrue();

    $otherTenant = Mockery::mock(IntegrationConnector::class)->makePartial();
    $otherTenant->tenant_id = '99';
    expect($this->policy->update($user, $otherTenant))->toBeFalse();
});

test('delete returns bool', function () {
    $user = Mockery::mock(User::class)->makePartial();
    $user->shouldReceive('can')->with('integration.connector.delete')->andReturn(true);
    $user->company_id = 3;

    $connector = Mockery::mock(IntegrationConnector::class)->makePartial();
    $connector->tenant_id = '3';

    expect($this->policy->delete($user, $connector))->toBeBool()->toBeTrue();
});

test('delete denies when connector belongs to a different tenant, even with permission', function () {
    $user = Mockery::mock(User::class)->makePartial();
    $user->shouldReceive('can')->with('integration.connector.delete')->andReturn(true);
    $user->company_id = 3;

    $connector = Mockery::mock(IntegrationConnector::class)->makePartial();
    $connector->tenant_id = '99';

    expect($this->policy->delete($user, $connector))->toBeBool()->toBeFalse();
});
