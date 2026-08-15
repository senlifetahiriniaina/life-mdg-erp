<?php

declare(strict_types=1);

use App\Models\User;
use Modules\Setup\Models\ImportJob;
use Modules\Setup\Policies\ImportSessionPolicy;

beforeEach(function () {
    $this->policy = new ImportSessionPolicy();
});

test('viewAny returns bool', function () {
    $user = Mockery::mock(User::class);
    $user->shouldReceive('can')->with('setup.import.view-any')->andReturn(true);

    expect($this->policy->viewAny($user))->toBeBool()->toBeTrue();
});

test('view returns bool', function () {
    $user = Mockery::mock(User::class);
    $user->shouldReceive('can')->with('setup.import.view')->andReturn(false);
    $importJob = Mockery::mock(ImportJob::class);

    expect($this->policy->view($user, $importJob))->toBeBool()->toBeFalse();
});

test('create returns bool', function () {
    $user = Mockery::mock(User::class);
    $user->shouldReceive('can')->with('setup.import.create')->andReturn(true);

    expect($this->policy->create($user))->toBeBool()->toBeTrue();
});

test('update returns bool', function () {
    $user = Mockery::mock(User::class);
    $user->shouldReceive('can')->with('setup.import.update')->andReturn(false);
    $importJob = Mockery::mock(ImportJob::class);

    expect($this->policy->update($user, $importJob))->toBeBool()->toBeFalse();
});

test('delete returns bool', function () {
    $user = Mockery::mock(User::class);
    $user->shouldReceive('can')->with('setup.import.delete')->andReturn(true);
    $importJob = Mockery::mock(ImportJob::class);

    expect($this->policy->delete($user, $importJob))->toBeBool()->toBeTrue();
});
