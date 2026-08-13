<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;

class ReportPolicy
{
    /**
     * Only finance/accounting/manager/admin can view financial reports.
     * These are high-impact sensitive operations.
     */
    public function viewFinancialReport(User $user): bool
    {
        return $user->hasAnyRole(['accounting', 'finance', 'manager', 'admin']);
    }

    public function viewBalanceSheet(User $user): bool
    {
        return $user->hasAnyRole(['accounting', 'finance', 'manager', 'admin']);
    }

    public function viewIncomeStatement(User $user): bool
    {
        return $user->hasAnyRole(['accounting', 'finance', 'manager', 'admin']);
    }

    public function viewCashFlow(User $user): bool
    {
        return $user->hasAnyRole(['accounting', 'finance', 'manager', 'admin']);
    }

    public function viewTaxReport(User $user): bool
    {
        return $user->hasAnyRole(['accounting', 'finance', 'admin']);
    }

    public function viewVatDeclaration(User $user): bool
    {
        return $user->hasAnyRole(['accounting', 'finance', 'admin']);
    }
}
