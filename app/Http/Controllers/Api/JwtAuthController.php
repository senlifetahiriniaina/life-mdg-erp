<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Models\User;
use App\Services\JwtService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class JwtAuthController
{
    public function __construct(private JwtService $jwtService)
    {
    }

    /**
     * Login with email/password and receive JWT tokens
     */
    public function login(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required|min:6',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = User::where('email', $request->input('email'))->first();

        if (!$user || !Hash::check($request->input('password'), $user->password)) {
            return response()->json(['error' => 'Invalid credentials'], 401);
        }

        if (!$user->is_active) {
            return response()->json(['error' => 'Account is inactive'], 403);
        }

        $accessToken = $this->jwtService->generateToken($user->id, [
            'email' => $user->email,
            'name' => $user->name,
            'type' => 'access',
        ], 15 * 60); // 15 minutes

        $refreshToken = $this->jwtService->generateRefreshToken($user->id);

        return response()->json([
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken,
            'token_type' => 'Bearer',
            'expires_in' => 15 * 60,
            'user' => $user->only(['id', 'name', 'email', 'avatar_url']),
        ], 200);
    }

    /**
     * Refresh access token using refresh token
     */
    public function refresh(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'refresh_token' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $payload = $this->jwtService->verifyToken($request->input('refresh_token'));

        if (!$payload || !$this->jwtService->isRefreshToken($payload)) {
            return response()->json(['error' => 'Invalid refresh token'], 401);
        }

        $user = User::find($payload['sub']);

        if (!$user || !$user->is_active) {
            return response()->json(['error' => 'User not found or inactive'], 401);
        }

        $accessToken = $this->jwtService->generateToken($user->id, [
            'email' => $user->email,
            'name' => $user->name,
            'type' => 'access',
        ], 15 * 60);

        return response()->json([
            'access_token' => $accessToken,
            'token_type' => 'Bearer',
            'expires_in' => 15 * 60,
        ], 200);
    }

    /**
     * Verify token validity (for mobile app pre-request check)
     */
    public function verify(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'token' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $payload = $this->jwtService->verifyToken($request->input('token'));

        if (!$payload) {
            return response()->json(['valid' => false], 401);
        }

        $user = User::find($payload['sub']);

        if (!$user || !$user->is_active) {
            return response()->json(['valid' => false], 401);
        }

        return response()->json([
            'valid' => true,
            'user_id' => $payload['sub'],
            'expires_at' => $payload['exp'],
            'issued_at' => $payload['iat'],
        ], 200);
    }

    /**
     * Logout (revoke token on client side, no server-side action needed for stateless JWT)
     */
    public function logout(Request $request): JsonResponse
    {
        // JWT tokens are stateless, so logout is handled client-side (token deletion)
        // Optional: Could maintain blacklist in cache for token revocation
        return response()->json(['message' => 'Successfully logged out'], 200);
    }
}
