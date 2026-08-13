<?php

namespace Modules\Accounting\Policies;

use App\Models\User;
use Modules\Accounting\Models\TaxComplianceReport;

class TaxComplianceReportPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('accounting.tax_compliance.view');
    }

    public function view(User $user, TaxComplianceReport $report): bool
    {
        return $user->hasPermissionTo('accounting.tax_compliance.view')
            && $this->belongsToCompany($user, $report->company_id);
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('accounting.tax_compliance.create');
    }

    public function update(User $user, TaxComplianceReport $report): bool
    {
        return $user->hasPermissionTo('accounting.tax_compliance.update')
            && in_array($report->status, ['draft', 'prepared'])
            && $this->belongsToCompany($user, $report->company_id);
    }

    public function delete(User $user, TaxComplianceReport $report): bool
    {
        return $user->hasPermissionTo('accounting.tax_compliance.delete')
            && $report->status === 'draft'
            && $this->belongsToCompany($user, $report->company_id);
    }

    public function file(User $user, TaxComplianceReport $report): bool
    {
        return $user->hasPermissionTo('accounting.tax_compliance.file')
            && in_array($report->status, ['reviewed', 'prepared'])
            && $this->belongsToCompany($user, $report->company_id);
    }

    public function restore(User $user, TaxComplianceReport $report): bool
    {
        return $user->hasPermissionTo('accounting.tax_compliance.restore');
    }

    public function forceDelete(User $user, TaxComplianceReport $report): bool
    {
        return $user->hasPermissionTo('accounting.tax_compliance.force_delete');
    }

    private function belongsToCompany(User $user, int $companyId): bool
    {
        return $user->company_id === $companyId || $user->hasRole('admin');
    }
}
