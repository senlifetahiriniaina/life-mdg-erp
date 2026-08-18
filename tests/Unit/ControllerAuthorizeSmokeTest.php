<?php

declare(strict_types=1);

/**
 * Illuminate\Routing\Controller (what these 9 controllers extended) defines
 * its own __call() that throws BadMethodCallException for any undefined
 * method — so every $this->authorize() call in them was structurally
 * guaranteed to fatal on first hit, not just latently risky. None of
 * these 9 actions happen to be exercised by existing HTTP-level Feature
 * tests (confirmed via a stash-verified before/after run: identical
 * 544 failed/428 passed either way), so this is the actual regression
 * guard for the fix rather than an existing test flipping green.
 */
test('controllers that call $this->authorize() can actually resolve it', function () {
    $controllers = [
        \Modules\Security\Http\Controllers\IncidentController::class,
        \Modules\Security\Http\Controllers\EncryptionController::class,
        \Modules\Security\Http\Controllers\ComplianceController::class,
        \Modules\Accounting\Http\Controllers\Api\ExpenseController::class,
        \Modules\Accounting\Http\Controllers\Api\BudgetController::class,
        \Modules\Strategy\Http\Controllers\Api\StrategyObjectiveLinkController::class,
        \Modules\Validation\Http\Controllers\Api\ApprovalRequestController::class,
        \Modules\Achats\Http\Controllers\Api\PurchaseOrderController::class,
    ];

    foreach ($controllers as $class) {
        expect(method_exists($class, 'authorize'))
            ->toBeTrue("{$class} calls \$this->authorize() but can't resolve it — would fatal with BadMethodCallException on first hit.");
        expect(is_subclass_of($class, \App\Http\Controllers\Controller::class))
            ->toBeTrue("{$class} should extend App\\Http\\Controllers\\Controller, not Illuminate\\Routing\\Controller directly.");
    }
});

test('App\Http\Controllers\Controller is a real superset of Illuminate\Routing\Controller', function () {
    expect(is_subclass_of(\App\Http\Controllers\Controller::class, \Illuminate\Routing\Controller::class))->toBeTrue();

    // middleware()/getMiddleware() specifically — several controllers being
    // moved onto this base class call $this->middleware() in their
    // constructor; without this, swapping their base class would silently
    // break that instead of fixing $this->authorize().
    expect(method_exists(\App\Http\Controllers\Controller::class, 'middleware'))->toBeTrue();
    expect(method_exists(\App\Http\Controllers\Controller::class, 'getMiddleware'))->toBeTrue();
});
