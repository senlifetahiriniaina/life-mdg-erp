<?php

namespace Modules\Security\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Modules\Security\Models\EncryptionKey;
use Modules\Security\Models\KeyRotationLog;
use Modules\Security\Models\EncryptedField;
use Illuminate\Validation\Rule;

class EncryptionController extends Controller
{
    public function indexKeys(Request $request): JsonResponse
    {
        $this->authorize('viewAny', EncryptionKey::class);

        $keys = EncryptionKey::where('company_id', auth()->user()->company_id)
            ->paginate($request->input('per_page', 15));

        return response()->json($keys);
    }

    public function storeKey(Request $request): JsonResponse
    {
        $this->authorize('create', EncryptionKey::class);

        $validated = $request->validate([
            'key_name' => 'required|string|max:255',
            'key_type' => 'required|in:AES-256-GCM,RSA,HMAC',
            'key_usage' => 'required|in:data_encryption,field_encryption,signing',
            'key_length_bits' => 'required|integer|in:128,256,2048,4096',
            'vault_reference' => 'required|string',
            'metadata' => 'nullable|array',
        ]);

        $key = EncryptionKey::create([
            'company_id' => auth()->user()->company_id,
            'key_status' => 'active',
            'key_material_hash' => hash('sha256', $validated['vault_reference']),
            ...$validated,
        ]);

        return response()->json($key, 201);
    }

    public function showKey(EncryptionKey $key): JsonResponse
    {
        $this->authorize('view', $key);

        $key->load('rotationLogs', 'encryptedFields');

        return response()->json($key);
    }

    public function updateKey(Request $request, EncryptionKey $key): JsonResponse
    {
        $this->authorize('update', $key);

        $validated = $request->validate([
            'key_name' => 'sometimes|string|max:255',
            'metadata' => 'array',
        ]);

        $key->update($validated);

        return response()->json($key);
    }

    public function rotateKey(EncryptionKey $key): JsonResponse
    {
        $this->authorize('rotate', $key);

        $log = KeyRotationLog::create([
            'encryption_key_id' => $key->id,
            'rotation_type' => 'requested',
            'rotation_status' => 'in_progress',
            'old_key_hash' => $key->key_material_hash,
            'new_key_hash' => hash('sha256', uniqid()),
            'started_at' => now(),
        ]);

        $key->update(['rotated_at' => now()]);

        return response()->json($log, 201);
    }

    public function revokeKey(EncryptionKey $key): JsonResponse
    {
        $this->authorize('revoke', $key);

        $key->update(['key_status' => 'revoked']);

        return response()->json($key);
    }

    public function deleteKey(EncryptionKey $key): JsonResponse
    {
        $this->authorize('delete', $key);

        $key->delete();

        return response()->json(null, 204);
    }

    public function indexRotationLogs(Request $request): JsonResponse
    {
        $this->authorize('viewAny', EncryptionKey::class);

        $logs = KeyRotationLog::whereHas('encryptionKey', function ($q) {
            $q->where('company_id', auth()->user()->company_id);
        })->paginate($request->input('per_page', 15));

        return response()->json($logs);
    }

    public function indexEncryptedFields(Request $request): JsonResponse
    {
        $this->authorize('viewAny', EncryptedField::class);

        $fields = EncryptedField::where('company_id', auth()->user()->company_id)
            ->paginate($request->input('per_page', 25));

        return response()->json($fields);
    }

    public function storeEncryptedField(Request $request): JsonResponse
    {
        $this->authorize('create', EncryptedField::class);

        $validated = $request->validate([
            'table_name' => 'required|string',
            'column_name' => 'required|string',
            'encryption_algorithm' => 'required|in:AES-256-GCM,RSA',
            // Chantier 32.3: exists: only checked the key existed anywhere,
            // not that it belonged to the caller's own company — a
            // security-admin from company A could name company B's
            // encryption_key_id, creating a cross-tenant-linked
            // EncryptedField. Since EncryptionKey::encryptedFields() has no
            // company scoping either, company B's admin viewing their OWN
            // key (a same-company, correctly-authorized view) would then see
            // company A's table_name/column_name leaked into
            // $key->encryptedFields — confirmed empirically before this fix.
            // security_encryption_keys.company_id is varchar(36) (the same
            // UUID-tenant-leftover column type already documented and cast
            // everywhere else in this module's Policies) while
            // auth()->user()->company_id is an int — cast to string here too,
            // or this where() silently never matches on some drivers.
            'encryption_key_id' => [
                'required',
                Rule::exists('security_encryption_keys', 'id')->where('company_id', (string) auth()->user()->company_id),
            ],
            'is_searchable' => 'boolean',
        ]);

        $field = EncryptedField::create([
            'company_id' => auth()->user()->company_id,
            'is_encrypted' => true,
            ...$validated,
        ]);

        return response()->json($field, 201);
    }
}
