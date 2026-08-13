<?php

declare(strict_types=1);

namespace Modules\Accounting\Traits;

/**
 * Adds tax calculation to any model exposing a percentage rate (0-100) via
 * getTaxRate() — used by TaxRate and TaxSetting.
 */
trait CalculatesTax
{
    /**
     * The tax rate as a percentage (e.g. 20.0 for 20%), implemented by the
     * consuming model.
     */
    abstract protected function getTaxRate(): float;

    public function calculateTax(float $amount): float
    {
        return round($amount * $this->getTaxRate() / 100, 2);
    }
}
