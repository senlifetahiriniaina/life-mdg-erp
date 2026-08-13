<?php

declare(strict_types=1);

namespace Modules\Core\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Modules\Core\Jobs\AnonymizeUserJob;
use Modules\Core\Jobs\SarExportJob;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * @group Account — RGPD
 *
 * Manage the authenticated user's own account (GDPR right to erasure).
 */
class AccountController extends Controller
{
    /**
     * Delete the authenticated user's account and anonymise all personal data.
     *
     * Requires current password confirmation.
     *
     * @response 204 {}
     * @response 422 {"message": "Password is incorrect."}
     */
    public function destroy(Request $request): JsonResponse
    {
        $request->validate([
            'password' => ['required', 'string'],
        ]);

        $user = $request->user();

        if (! Hash::check($request->string('password')->value(), $user->password)) {
            return response()->json(['message' => 'Password is incorrect.'], 422);
        }

        // Revoke all tokens so the session is immediately invalidated
        $user->tokens()->delete();

        // Dispatch async job so the HTTP response is fast
        AnonymizeUserJob::dispatch($user->id);

        return response()->json(null, 204);
    }

    /**
     * Export all personal data for the authenticated user (GDPR SAR).
     *
     * Returns a cached JSON download if one was produced in the last 24 hours.
     * Otherwise queues `SarExportJob` and responds 202 asking the client to retry.
     *
     * @response 200  application/json  (binary download)
     * @response 202  {"message": "Export in progress. Please retry in a few minutes."}
     */
    public function export(Request $request): JsonResponse|StreamedResponse
    {
        /** @var User $user */
        $user = $request->user();
        $storagePath = "sar/{$user->id}/export.json";
        $disk = Storage::disk('local');

        if ($disk->exists($storagePath) && $disk->lastModified($storagePath) >= now()->subDay()->timestamp) {
            return $disk->download($storagePath, "sar_export_{$user->id}.json", [
                'Content-Type' => 'application/json',
            ]);
        }

        SarExportJob::dispatch($user);

        return response()->json(['message' => 'Export in progress. Please retry in a few minutes.'], 202);
    }
}
