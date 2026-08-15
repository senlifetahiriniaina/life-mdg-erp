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

test('delete returns bool', function () {
    $user = Mockery::mock(User::class);
    $user->shouldReceive('can')->with('integration.connector.delete')->andReturn(true);
    $connector = Mockery::mock(IntegrationConnector::class);

    expect($this->policy->delete($user, $connector))->toBeBool()->toBeTrue();
});
