<?php

declare(strict_types=1);

namespace Modules\Accounting\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class HandleOpenBankingWebhookRequest extends FormRequest
{
    /**
     * Verifies the HMAC-SHA256 signature in the X-Signature header against a
     * per-provider secret (config('banking.{provider}_webhook_secret')) --
     * no Sanctum auth on a bank-provider webhook, so this is the only guard.
     */
    /** Providers config/banking.php actually defines a webhook secret for. */
    private const SUPPORTED_PROVIDERS = ['plaid', 'nordigen', 'bridge', 'truelayer'];

    public function authorize(): bool
    {
        $provider = $this->input('provider');
        $secret   = in_array($provider, self::SUPPORTED_PROVIDERS, true)
            ? config("banking.{$provider}_webhook_secret")
            : null;
        $signature = $this->header('X-Signature');

        if (! $provider || ! $secret || ! $signature) {
            return false;
        }

        $expected = hash_hmac('sha256', $this->getContent(), (string) $secret);

        return hash_equals($expected, (string) $signature);
    }

    protected function failedAuthorization(): void
    {
        throw new HttpResponseException(
            response()->json(['error' => 'Unauthorized'], 401)
        );
    }

    public function rules(): array
    {
        return [
            'provider' => ['required', 'string'],
        ];
    }
}
