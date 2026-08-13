<?php

declare(strict_types=1);

namespace Modules\Core\Policies;

use App\Models\User;
use Modules\Core\Models\CspViolation;

/**
 * CSP Violation Policy
 *
 * Authorization policy for CSP violation viewing and management.
 * Only admins and security team can view/manage violations.
 */
class CspViolationPolicy
{
    /**
     * Determine if user can view violations.
     *
     * @param User $user
     * @param CspViolation $violation
     * @return bool
     */
    public function view(User $user, CspViolation $violation): bool
    {
        // Only admins and security roles can view violations
        return $user->hasRole(['admin', 'security_manager', 'compliance_officer']);
    }

    /**
     * Determine if user can view any violations.
     *
     * @param User $user
     * @return bool
     */
    public function viewAny(User $user): bool
    {
        return $user->hasRole(['admin', 'security_manager', 'compliance_officer']);
    }

    /**
     * Determine if user can update violations (mark resolved).
     *
     * @param User $user
     * @param CspViolation $violation
     * @return bool
     */
    public function update(User $user, CspViolation $violation): bool
    {
        // Only admins and security team can resolve violations
        return $user->hasRole(['admin', 'security_manager']);
    }

    /**
     * Determine if user can delete violations.
     *
     * @param User $user
     * @param CspViolation $violation
     * @return bool
     */
    public function delete(User $user, CspViolation $violation): bool
    {
        // Only admins can delete violations
        return $user->hasRole('admin');
    }
}
