<?php

declare(strict_types=1);

use App\Models\User;
use Modules\Reporting\Models\ReportDefinition;
use Modules\Reporting\Policies\ReportPolicy;

beforeEach(function () {
    $this->policy = new ReportPolicy();
});

test('viewAny returns bool', function () {
    $user = Mockery::mock(User::class);
    $user->shouldReceive('can')->with('reporting.report.view-any')->andReturn(true);

    expect($this->policy->viewAny($user))->toBeBool()->toBeTrue();
});

test('view returns bool', function () {
    $user = Mockery::mock(User::class);
    $user->shouldReceive('can')->with('reporting.report.view')->andReturn(false);
    $report = Mockery::mock(ReportDefinition::class);

    expect($this->policy->view($user, $report))->toBeBool()->toBeFalse();
});

test('create returns bool', function () {
    $user = Mockery::mock(User::class);
    $user->shouldReceive('can')->with('reporting.report.create')->andReturn(true);

    expect($this->policy->create($user))->toBeBool()->toBeTrue();
});

test('update returns bool', function () {
    $user = Mockery::mock(User::class);
    $user->shouldReceive('can')->with('reporting.report.update')->andReturn(false);
    $report = Mockery::mock(ReportDefinition::class);

    expect($this->policy->update($user, $report))->toBeBool()->toBeFalse();
});

test('delete returns bool', function () {
    $user = Mockery::mock(User::class);
    $user->shouldReceive('can')->with('reporting.report.delete')->andReturn(true);
    $report = Mockery::mock(ReportDefinition::class);

    expect($this->policy->delete($user, $report))->toBeBool()->toBeTrue();
});
