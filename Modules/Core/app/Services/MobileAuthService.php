<?php

declare(strict_types=1);

namespace Modules\Core\Services;

/**
 * Stub service.
 *
 * The Core module's routes/controllers reference this service, but a full
 * implementation was never written. These methods are placeholders so the
 * module boots and its routes register; they return inert results and must be
 * implemented before the corresponding endpoints are used in production.
 */
class MobileAuthService
{
    public function getLoginHistory(...$args): array
    {
        return ['implemented' => false, 'message' => 'MobileAuthService::getLoginHistory is not yet implemented.'];
    }
    public function getUserDevices(...$args): array
    {
        return ['implemented' => false, 'message' => 'MobileAuthService::getUserDevices is not yet implemented.'];
    }
    public function login(...$args): array
    {
        return ['implemented' => false, 'message' => 'MobileAuthService::login is not yet implemented.'];
    }
    public function loginBiometric(...$args): array
    {
        return ['implemented' => false, 'message' => 'MobileAuthService::loginBiometric is not yet implemented.'];
    }
    public function logoutAllDevices(...$args): array
    {
        return ['implemented' => false, 'message' => 'MobileAuthService::logoutAllDevices is not yet implemented.'];
    }
    public function refreshToken(...$args): array
    {
        return ['implemented' => false, 'message' => 'MobileAuthService::refreshToken is not yet implemented.'];
    }
    public function registerBiometric(...$args): array
    {
        return ['implemented' => false, 'message' => 'MobileAuthService::registerBiometric is not yet implemented.'];
    }
    public function revokeDevice(...$args): array
    {
        return ['implemented' => false, 'message' => 'MobileAuthService::revokeDevice is not yet implemented.'];
    }
}
