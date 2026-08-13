<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\JwtService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class JwtAuthenticate
{
    public function __construct(private JwtService $jwtService)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $token = $this->extractToken($request);

        if (!$token) {
            return response()->json(['error' => 'Missing authorization token'], 401);
        }

        $payload = $this->jwtService->verifyToken($token);

        if (!$payload) {
            return response()->json(['error' => 'Invalid or expired token'], 401);
        }

        // Attach JWT payload to request for use in controllers
        $request->attributes->set('jwt_payload', $payload);
        $request->attributes->set('jwt_user_id', $payload['sub'] ?? null);

        return $next($request);
    }

    private function extractToken(Request $request): ?string
    {
        $header = $request->header('Authorization');

        if (!$header || !str_starts_with($header, 'Bearer ')) {
            return null;
        }

        return substr($header, 7);
    }
}
