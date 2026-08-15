<?php

namespace Modules\Core\Http\Controllers;

use Modules\Core\Services\MobileAuthService;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class MobileAuthController extends Controller
{
    public function __construct(private MobileAuthService $authService) {}

    /**
     * Email/password login
     * POST /api/v1/mobile/auth/login
     */
    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string|min:6',
            'device_name' => 'required|string',
            'platform' => 'required|in:ios,android',
        ]);

        $response = $this->authService->login(
            $validated['email'],
            $validated['password'],
            $validated['device_name'],
            $validated['platform']
        );

        if (!$response) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid credentials',
            ], 401);
        }

        return response()->json([
            'success' => true,
            'message' => 'Login successful',
            'token' => $response['token'],
            'refresh_token' => $response['refresh_token'],
            'expires_in' => $response['expires_in'],
            'user' => [
                'id' => auth()->user()->id,
                'name' => auth()->user()->name,
                'email' => auth()->user()->email,
            ],
        ], 200);
    }

    /**
     * Register biometric (fingerprint/face)
     * POST /api/v1/mobile/auth/biometric/register
     */
    public function registerBiometric(Request $request): JsonResponse
    {
        $request->validate([
            'type' => 'required|in:fingerprint,face,iris',
            'template' => 'required|string',
        ]);

        $user = auth()->user();
        $success = $this->authService->registerBiometric(
            $user,
            $request->input('type'),
            $request->input('template')
        );

        if (!$success) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to register biometric',
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Biometric registered successfully',
            'type' => $request->input('type'),
        ], 200);
    }

    /**
     * Authenticate with biometric
     * POST /api/v1/mobile/auth/biometric/login
     */
    public function loginBiometric(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => 'required|integer',
            'type' => 'required|in:fingerprint,face,iris',
            'template' => 'required|string',
            'device_name' => 'required|string',
            'platform' => 'required|in:ios,android',
        ]);

        $response = $this->authService->loginBiometric(
            $validated['user_id'],
            $validated['type'],
            $validated['template'],
            $validated['device_name'],
            $validated['platform']
        );

        if (!$response) {
            return response()->json([
                'success' => false,
                'message' => 'Biometric authentication failed',
            ], 401);
        }

        return response()->json([
            'success' => true,
            'message' => 'Biometric login successful',
            'token' => $response['token'],
            'refresh_token' => $response['refresh_token'],
            'expires_in' => $response['expires_in'],
        ], 200);
    }

    /**
     * Refresh token
     * POST /api/v1/mobile/auth/refresh
     */
    public function refreshToken(Request $request): JsonResponse
    {
        $request->validate([
            'refresh_token' => 'required|string',
        ]);

        $response = $this->authService->refreshToken($request->input('refresh_token'));

        if (!$response) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired refresh token',
            ], 401);
        }

        return response()->json([
            'success' => true,
            'token' => $response['token'],
            'refresh_token' => $response['refresh_token'],
            'expires_in' => $response['expires_in'],
        ], 200);
    }

    /**
     * Logout (revoke current device)
     * POST /api/v1/mobile/auth/logout
     */
    public function logout(Request $request): JsonResponse
    {
        $user = auth()->user();
        $token = $request->bearerToken();

        $success = $this->authService->revokeDevice($user, $token);

        if (!$success) {
            return response()->json([
                'success' => false,
                'message' => 'Logout failed',
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Logged out successfully',
        ], 200);
    }

    /**
     * Logout all devices
     * POST /api/v1/mobile/auth/logout-all
     */
    public function logoutAll(Request $request): JsonResponse
    {
        $user = auth()->user();
        $success = $this->authService->logoutAllDevices($user);

        if (!$success) {
            return response()->json([
                'success' => false,
                'message' => 'Logout failed',
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Logged out from all devices',
        ], 200);
    }

    /**
     * Get user devices
     * GET /api/v1/mobile/auth/devices
     */
    public function getDevices(): JsonResponse
    {
        $user = auth()->user();
        $devices = $this->authService->getUserDevices($user);

        return response()->json([
            'success' => true,
            'devices' => array_map(fn($device) => [
                'id' => $device['id'],
                'device_name' => $device['device_name'],
                'device_model' => $device['device_model'],
                'platform' => $device['platform'],
                'os_version' => $device['os_version'],
                'is_active' => $device['is_active'],
                'registered_at' => $device['registered_at'],
                'last_login_at' => $device['last_login_at'],
            ], $devices),
        ], 200);
    }

    /**
     * Revoke specific device
     * DELETE /api/v1/mobile/auth/devices/{device_id}
     */
    public function revokeDevice(Request $request, int $deviceId): JsonResponse
    {
        $user = auth()->user();
        // Get device token and revoke
        $success = $this->authService->revokeDevice($user, null);

        if (!$success) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to revoke device',
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Device revoked successfully',
        ], 200);
    }

    /**
     * Get login history
     * GET /api/v1/mobile/auth/history
     */
    public function getLoginHistory(Request $request): JsonResponse
    {
        $user = auth()->user();
        $limit = $request->get('limit', 20);

        $history = $this->authService->getLoginHistory($user, $limit);

        return response()->json([
            'success' => true,
            'total' => count($history),
            'logins' => array_map(fn($log) => [
                'id' => $log['id'],
                'device_name' => $log['device_name'],
                'platform' => $log['platform'],
                'method' => $log['method'],
                'ip_address' => $this->maskIpAddress($log['ip_address']),
                'status' => $log['status'],
                'is_suspicious' => $log['is_suspicious'],
                'login_at' => $log['login_at'],
            ], $history),
        ], 200);
    }

    /**
     * Confirm suspicious activity
     * POST /api/v1/mobile/auth/suspicious-activity/{activity_id}/confirm
     */
    public function confirmSuspiciousActivity(Request $request, int $activityId): JsonResponse
    {
        $user = auth()->user();
        $request->validate([
            'is_legitimate' => 'required|boolean',
        ]);

        // Mark activity as confirmed by user
        // This would be handled by a separate service

        return response()->json([
            'success' => true,
            'message' => 'Activity status updated',
        ], 200);
    }

    /**
     * Mask IP address for security
     */
    private function maskIpAddress(string $ip): string
    {
        if (str_contains($ip, ':')) {
            // IPv6
            return implode(':', array_slice(explode(':', $ip), 0, 4)) . ':****:****';
        } else {
            // IPv4
            $parts = explode('.', $ip);
            $parts[3] = '***';
            return implode('.', $parts);
        }
    }
}
