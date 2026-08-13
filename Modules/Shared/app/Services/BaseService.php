<?php

declare(strict_types=1);

namespace Modules\Shared\Services;

use Illuminate\Database\Eloquent\Model;
use Modules\Shared\Exceptions\TenantException;

abstract class BaseService
{
    protected int $companyId;

    public function __construct(int $companyId)
    {
        if ($companyId <= 0) {
            throw TenantException::invalidCompanyId($companyId);
        }

        $this->companyId = $companyId;
    }

    /**
     * Verify model belongs to current company
     */
    protected function verifyCompanyOwnership(Model $model, string $fieldName = 'company_id'): void
    {
        if (! $model instanceof Model) {
            throw TenantException::companyIsolationViolation(
                'Invalid model provided'
            );
        }

        $modelCompanyId = $model->{$fieldName} ?? null;

        if ($modelCompanyId !== $this->companyId) {
            throw TenantException::companyIsolationViolation(
                "Model does not belong to company {$this->companyId}"
            );
        }
    }

    /**
     * Verify multiple models belong to current company
     */
    protected function verifyCompanyOwnershipMany(array $models, string $fieldName = 'company_id'): void
    {
        foreach ($models as $model) {
            $this->verifyCompanyOwnership($model, $fieldName);
        }
    }

    /**
     * Get company-scoped query
     */
    protected function scopeQuery($query, string $fieldName = 'company_id')
    {
        return $query->where($fieldName, $this->companyId);
    }

    /**
     * Get current company ID
     */
    public function getCompanyId(): int
    {
        return $this->companyId;
    }

    /**
     * Change company ID (requires explicit action)
     */
    protected function setCompanyId(int $companyId): self
    {
        if ($companyId <= 0) {
            throw TenantException::invalidCompanyId($companyId);
        }

        $this->companyId = $companyId;
        return $this;
    }
}
