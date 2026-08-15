<?php

declare(strict_types=1);

use App\Models\User;
use Modules\Sales\Models\SalesOrder;
use Modules\Sales\Policies\SalesOrderPolicy;

beforeEach(function () {
    $this->policy = new SalesOrderPolicy();
});

test('viewAny returns bool', function () {
    $user = Mockery::mock(User::class);
    $user->shouldReceive('can')->with('sales.order.view-any')->andReturn(true);

    expect($this->policy->viewAny($user))->toBeBool()->toBeTrue();
});

test('view returns bool', function () {
    $user = Mockery::mock(User::class);
    $user->shouldReceive('can')->with('sales.order.view')->andReturn(false);
    $salesOrder = Mockery::mock(SalesOrder::class);

    expect($this->policy->view($user, $salesOrder))->toBeBool()->toBeFalse();
});

test('create returns bool', function () {
    $user = Mockery::mock(User::class);
    $user->shouldReceive('can')->with('sales.order.create')->andReturn(true);

    expect($this->policy->create($user))->toBeBool()->toBeTrue();
});

test('update returns bool', function () {
    $user = Mockery::mock(User::class);
    $user->shouldReceive('can')->with('sales.order.update')->andReturn(false);
    $salesOrder = Mockery::mock(SalesOrder::class);

    expect($this->policy->update($user, $salesOrder))->toBeBool()->toBeFalse();
});

test('delete returns bool', function () {
    $user = Mockery::mock(User::class);
    $user->shouldReceive('can')->with('sales.order.delete')->andReturn(true);
    $salesOrder = Mockery::mock(SalesOrder::class);

    expect($this->policy->delete($user, $salesOrder))->toBeBool()->toBeTrue();
});
