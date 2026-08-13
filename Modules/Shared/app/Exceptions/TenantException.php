<?php

declare(strict_types=1);

namespace Modules\Shared\Exceptions;

class TenantException extends BaseException
{
    protected string $errorCode = 'TENANT_ERROR';

    public static function invalidCompanyId(int $companyId, array $context = []): self
    {
        return new self(
            "Invalid company ID: {$companyId}",
            400,
            null,
            array_merge(['company_id' => $companyId], $context)
        );
    }

    public static function companyIsolationViolation(string $message = '', array $context = []): self
    {
        return new self(
            $message ?: 'Company isolation constraint violated',
            403,
            null,
            $context
        );
    }

    public static function multiTenancyNotConfigured(array $context = []): self
    {
        return new self(
            'Multi-tenancy not properly configured',
            500,
            null,
            $context
        );
    }
}
