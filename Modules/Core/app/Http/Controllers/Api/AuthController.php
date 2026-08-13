<?php

declare(strict_types=1);

namespace Modules\Core\Http\Controllers\Api;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Modules\Core\Http\Requests\LoginRequest;
use Modules\Core\Http\Requests\RegisterRequest;

/**
 * @group Core - Auth
 *
 * Register, login, logout and manage authentication tokens.
 */
class AuthController extends Controller
{
    /**
     * Register a new user account.
     *
     * @unauthenticated
     *
     * @response 201 {"user":{"id":1,"email":"jane@example.com"},"token":"1|abc..."}
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        $user = User::create([
            'name' => $request->name,
            'first_name' => $request->first_name,
            'last_name' => $request->last_name,
            'email' => $request->email,
            'password' => $request->password,
            'locale' => $request->locale ?? app()->getLocale(),
        ]);

        $token = $user->createToken('api')->plainTextToken;

        return response()->json([
            'user' => $user,
            'token' => $token,
        ], 201);
    }

    /**
     * Login and receive an API token.
     *
     * @unauthenticated
     *
     * @response {"user":{"id":1,"email":"jane@example.com","roles":[]},"token":"1|abc..."}
     * @response 429 {"message":"Account locked due to too many failed login attempts. Please try again later."}
     * @response 422 {"message":"These credentials do not match our records."}
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::where('email', $request->email)->first();

        if ($user && $user->isAccountLocked()) {
            return response()->json([
                'message' => 'Account locked due to too many failed login attempts. Please try again later.',
                'locked_until' => $user->getLockedUntilFormatted(),
            ], 429);
        }

        if (! $user || ! Hash::check($request->password, $user->password)) {
            if ($user) {
                $user->recordFailedLoginAttempt();
            }

            event(new \Illuminate\Auth\Events\Failed('sanctum', $user, [
                'email' => $request->email,
            ]));

            throw ValidationException::withMessages([
                'email' => [__('auth.failed')],
            ]);
        }

        if (! $user->is_active) {
            return response()->json(['message' => __('auth.deactivated')], 403);
        }

        $user->resetLoginAttempts();

        $user->update([
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
        ]);

        event(new \Illuminate\Auth\Events\Login('sanctum', $user, false));

        // When 2FA is enabled, withhold the full token and issue a short-lived
        // challenge token (ability "2fa:challenge"). The client must complete
        // POST /v1/auth/2fa/verify to receive a real API token.
        if ($user->hasTwoFactorEnabled()) {
            $challengeToken = $user->createToken('2fa-challenge', ['2fa:challenge'])->plainTextToken;

            return response()->json([
                'two_factor_required' => true,
                'challenge_token' => $challengeToken,
            ]);
        }

        $token = $user->createToken($request->device_name ?? 'api')->plainTextToken;

        return response()->json([
            'user' => $user->load('roles'),
            'token' => $token,
        ]);
    }

    /** Revoke the current access token. */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out successfully']);
    }

    /** Return the authenticated user's profile with roles and permissions. */
    public function me(Request $request): JsonResponse
    {
        return response()->json($request->user()->load(['roles', 'permissions']));
    }

    /** Rotate the current token: revoke it and issue a fresh one. */
    public function refresh(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();
        $token = $request->user()->createToken('api')->plainTextToken;

        return response()->json(['token' => $token]);
    }
}
