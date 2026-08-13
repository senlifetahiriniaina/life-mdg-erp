<?php

declare(strict_types=1);

uses(Tests\TestCase::class)->in('Feature', 'Unit');

// Bootstrap function to ensure facade root and cache setup for all tests
beforeEach(function () {
    // Ensure the facade root is properly set
    if (method_exists($this, 'app')) {
        \Illuminate\Support\Facades\Facade::setFacadeApplication($this->app);

        // Ensure cache repository is bound
        if (!$this->app->bound('cache')) {
            $arrayStore = new \Illuminate\Cache\ArrayStore();
            $repository = new \Illuminate\Cache\Repository($arrayStore);
            $this->app->instance('cache', $repository);
        }
    }
});
