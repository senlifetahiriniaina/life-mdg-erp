<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\Core\Services\SessionSecurityService;
use PragmaRX\Google2FA\Google2FA;

/**
 * @group Security - Two-Factor Authentication
 *
 * TOTP-based 2FA enrolment, confirmation, login challenge and disabling.
 *
 * Enforcement: admin / super-admin roles must enrol (see
 * EnsureTwoFactorAuthenticated middleware); all other users are opt-in.
 */
class TwoFactorController extends Controller
{
    public function __construct(private Google2FA $google2fa, private SessionSecurityService $sessionSecurity)
    {
    }

    /**
     * Begin 2FA enrolment: generate (but do not yet enable) a secret and return
     * the otpauth:// provisioning URI for the authenticator app / QR code.
     */
    public function setup(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $secret = $this->google2fa->generateSecretKey();
        $user->forceFill([
            'google2fa_secret' => $secret,
            'two_factor_enabled' => false,
            'two_factor_confirmed_at' => null,
        ])->save();

        $issuer = config('app.name', 'WideHalo');
        $otpauthUri = $this->google2fa->getQRCodeUrl($issuer, $user->email, $secret);

        return response()->json([
            'secret' => $secret,
            'otpauth_uri' => $otpauthUri,
            'message' => 'Scan the QR code, then confirm with a generated code to activate 2FA.',
        ]);
    }

    /**
     * Confirm enrolment with the first valid code. Activates 2FA and returns
     * one-time recovery codes (shown once).
     */
    public function confirm(Request $request): JsonResponse
    {
        $request->validate(['code' => 'required|string']);

        /** @var User $user */
        $user = $request->user();

        if (empty($user->google2fa_secret)) {
            throw ValidationException::withMessages(['code' => ['Start 2FA setup before confirming.']]);
        }

        if (! $this->google2fa->verifyKey($user->google2fa_secret, $request->string('code')->toString())) {
            throw ValidationException::withMessages(['code' => ['The provided 2FA code is invalid.']]);
        }

        $recoveryCodes = $this->generateRecoveryCodes();

        $user->forceFill([
            'two_factor_enabled' => true,
            'two_factor_confirmed_at' => now(),
            'two_factor_recovery_codes' => $recoveryCodes,
        ])->save();

        return response()->json([
            'message' => 'Two-factor authentication enabled.',
            'recovery_codes' => $recoveryCodes,
        ]);
    }

    /**
     * Complete the login challenge. Called with a short-lived challenge token
     * (ability "2fa:challenge"); accepts a TOTP code or a one-time recovery
     * code. On success, swaps the challenge token for a full API token.
     */
    public function verify(Request $request): JsonResponse
    {
        $request->validate(['code' => 'required|string']);

        /** @var User $user */
        $user = $request->user();
        $code = $request->string('code')->toString();

        $passed = $this->google2fa->verifyKey((string) $user->google2fa_secret, $code)
            || $this->consumeRecoveryCode($user, $code);

        if (! $passed) {
            throw ValidationException::withMessages(['code' => ['The provided 2FA code is invalid.']]);
        }

        // Revoke the challenge token and issue a real one.
        $request->user()->currentAccessToken()->delete();
        $newToken = $user->createToken('api');
        $this->sessionSecurity->createSession((string) $newToken->accessToken->id, $user->id, $request);

        return response()->json([
            'user' => $user->load('roles'),
            'token' => $newToken->plainTextToken,
        ]);
    }

    /** Disable 2FA. Requires current password and a valid TOTP code. */
    public function disable(Request $request): JsonResponse
    {
        $request->validate([
            'password' => 'required|string',
            'code' => 'required|string',
        ]);

        /** @var User $user */
        $user = $request->user();

        if (! Hash::check($request->string('password')->toString(), $user->password)) {
            throw ValidationException::withMessages(['password' => ['The provided password is incorrect.']]);
        }

        if (! $this->google2fa->verifyKey((string) $user->google2fa_secret, $request->string('code')->toString())) {
            throw ValidationException::withMessages(['code' => ['The provided 2FA code is invalid.']]);
        }

        $user->forceFill([
            'google2fa_secret' => null,
            'two_factor_enabled' => false,
            'two_factor_confirmed_at' => null,
            'two_factor_recovery_codes' => null,
        ])->save();

        return response()->json(['message' => 'Two-factor authentication disabled.']);
    }

    /** @return list<string> */
    private function generateRecoveryCodes(): array
    {
        return collect(range(1, 8))
            ->map(fn () => Str::upper(Str::random(5).'-'.Str::random(5)))
            ->all();
    }

    /** Consume a one-time recovery code if it matches; returns true on use. */
    private function consumeRecoveryCode(User $user, string $code): bool
    {
        $codes = $user->two_factor_recovery_codes ?? [];
        $normalized = Str::upper(trim($code));

        if (! in_array($normalized, $codes, true)) {
            return false;
        }

        $user->forceFill([
            'two_factor_recovery_codes' => array_values(array_diff($codes, [$normalized])),
        ])->save();

        return true;
    }
}
