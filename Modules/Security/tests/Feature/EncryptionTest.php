<?php

namespace Modules\Security\Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Security\Models\EncryptionKey;
use Modules\Security\Models\KeyRotationLog;
use Modules\Security\Models\EncryptedField;
use Tests\TestCase;

class EncryptionTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();
        $this->company = Company::factory()->create();
        $this->user = User::factory()->for($this->company)->create();
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        $this->user->assignRole('security-admin');
    }

    public function test_list_encryption_keys(): void
    {
        EncryptionKey::factory(5)->for($this->company)->create();

        $response = $this->actingAs($this->user)->getJson('/api/v1/security/encryption/keys');

        $response->assertOk();
        $response->assertJsonCount(5, 'data');
    }

    public function test_create_aes_key(): void
    {
        $response = $this->actingAs($this->user)->postJson('/api/v1/security/encryption/keys', [
            'key_name' => 'Primary Data Key',
            'key_type' => 'AES-256-GCM',
            'key_usage' => 'data_encryption',
            'key_length_bits' => 256,
            'vault_reference' => 'vault:key:123',
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('security_encryption_keys', [
            'key_name' => 'Primary Data Key',
            'key_type' => 'AES-256-GCM',
        ]);
    }

    public function test_create_rsa_key(): void
    {
        $response = $this->actingAs($this->user)->postJson('/api/v1/security/encryption/keys', [
            'key_name' => 'RSA Key',
            'key_type' => 'RSA',
            'key_usage' => 'signing',
            'key_length_bits' => 2048,
            'vault_reference' => 'vault:rsa:456',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('key_type', 'RSA');
    }

    public function test_view_encryption_key(): void
    {
        $key = EncryptionKey::factory()->for($this->company)->create();

        $response = $this->actingAs($this->user)->getJson("/api/v1/security/encryption/keys/{$key->id}");

        $response->assertOk();
        $response->assertJsonPath('id', $key->id);
    }

    public function test_update_encryption_key(): void
    {
        $key = EncryptionKey::factory()->for($this->company)->create();

        $response = $this->actingAs($this->user)->patchJson("/api/v1/security/encryption/keys/{$key->id}", [
            'key_name' => 'Updated Key Name',
        ]);

        $response->assertOk();
        $response->assertJsonPath('key_name', 'Updated Key Name');
    }

    public function test_rotate_encryption_key(): void
    {
        $key = EncryptionKey::factory()->for($this->company)->create(['key_status' => 'active']);

        $response = $this->actingAs($this->user)->postJson("/api/v1/security/encryption/keys/{$key->id}/rotate");

        $response->assertCreated();
        $this->assertDatabaseHas('key_rotation_logs', [
            'encryption_key_id' => $key->id,
            'rotation_type' => 'requested',
            'rotation_status' => 'in_progress',
        ]);
    }

    public function test_revoke_encryption_key(): void
    {
        $key = EncryptionKey::factory()->for($this->company)->create(['key_status' => 'active']);

        $response = $this->actingAs($this->user)->postJson("/api/v1/security/encryption/keys/{$key->id}/revoke");

        $response->assertOk();
        $response->assertJsonPath('key_status', 'revoked');
    }

    public function test_delete_revoked_key(): void
    {
        $key = EncryptionKey::factory()->for($this->company)->create(['key_status' => 'revoked']);

        $response = $this->actingAs($this->user)->deleteJson("/api/v1/security/encryption/keys/{$key->id}");

        $response->assertNoContent();
    }

    public function test_cannot_rotate_inactive_key(): void
    {
        $key = EncryptionKey::factory()->for($this->company)->create(['key_status' => 'revoked']);

        $response = $this->actingAs($this->user)->postJson("/api/v1/security/encryption/keys/{$key->id}/rotate");

        $response->assertForbidden();
    }

    public function test_key_company_isolation(): void
    {
        $otherCompany = Company::factory()->create();
        $key = EncryptionKey::factory()->for($otherCompany)->create();

        $response = $this->actingAs($this->user)->getJson("/api/v1/security/encryption/keys/{$key->id}");

        $response->assertForbidden();
    }

    public function test_key_types_validation(): void
    {
        $types = ['AES-256-GCM', 'RSA', 'HMAC'];

        foreach ($types as $type) {
            $response = $this->actingAs($this->user)->postJson('/api/v1/security/encryption/keys', [
                'key_name' => "Test {$type}",
                'key_type' => $type,
                'key_usage' => 'data_encryption',
                'key_length_bits' => 256,
                'vault_reference' => "vault:$type:123",
            ]);

            $response->assertCreated();
        }
    }

    public function test_list_rotation_logs(): void
    {
        $key = EncryptionKey::factory()->for($this->company)->create();
        KeyRotationLog::factory(3)->for($key)->create();

        $response = $this->actingAs($this->user)->getJson('/api/v1/security/encryption/rotation-logs');

        $response->assertOk();
        $response->assertJsonCount(3, 'data');
    }

    public function test_list_encrypted_fields(): void
    {
        EncryptedField::factory(5)->for($this->company)->create();

        $response = $this->actingAs($this->user)->getJson('/api/v1/security/encryption/encrypted-fields');

        $response->assertOk();
        $response->assertJsonCount(5, 'data');
    }

    public function test_create_encrypted_field(): void
    {
        $key = EncryptionKey::factory()->for($this->company)->create();

        $response = $this->actingAs($this->user)->postJson('/api/v1/security/encryption/encrypted-fields', [
            'table_name' => 'users',
            'column_name' => 'email',
            'encryption_algorithm' => 'AES-256-GCM',
            'encryption_key_id' => $key->id,
            'is_searchable' => true,
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('encrypted_fields', [
            'table_name' => 'users',
            'column_name' => 'email',
        ]);
    }

    public function test_rotation_log_timestamps(): void
    {
        $key = EncryptionKey::factory()->for($this->company)->create();
        $log = KeyRotationLog::factory()->for($key)->create();

        $response = $this->actingAs($this->user)->getJson('/api/v1/security/encryption/rotation-logs');

        $response->assertOk();
        $response->assertJsonPath('data.0.started_at', fn($date) => $date !== null);
    }

    public function test_key_metadata_persistence(): void
    {
        $metadata = ['environment' => 'production', 'version' => '2'];

        $key = EncryptionKey::factory()->for($this->company)->create(['metadata' => $metadata]);

        $response = $this->actingAs($this->user)->getJson("/api/v1/security/encryption/keys/{$key->id}");

        $response->assertOk();
        $response->assertJsonPath('metadata.environment', 'production');
    }

    public function test_key_pagination(): void
    {
        EncryptionKey::factory(20)->for($this->company)->create();

        $response = $this->actingAs($this->user)->getJson('/api/v1/security/encryption/keys?per_page=10');

        $response->assertOk();
        $response->assertJsonCount(10, 'data');
        $response->assertJsonPath('total', 20);
    }

    public function test_encryption_key_status_active_by_default(): void
    {
        $response = $this->actingAs($this->user)->postJson('/api/v1/security/encryption/keys', [
            'key_name' => 'Test',
            'key_type' => 'AES-256-GCM',
            'key_usage' => 'data_encryption',
            'key_length_bits' => 256,
            'vault_reference' => 'vault:test:123',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('key_status', 'active');
    }

    public function test_encrypted_field_searchability(): void
    {
        $key = EncryptionKey::factory()->for($this->company)->create();

        $response = $this->actingAs($this->user)->postJson('/api/v1/security/encryption/encrypted-fields', [
            'table_name' => 'users',
            'column_name' => 'phone',
            'encryption_algorithm' => 'AES-256-GCM',
            'encryption_key_id' => $key->id,
            'is_searchable' => false,
        ]);

        $response->assertCreated();
        $response->assertJsonPath('is_searchable', false);
    }

    public function test_key_usage_types(): void
    {
        $usages = ['data_encryption', 'field_encryption', 'signing'];

        foreach ($usages as $usage) {
            $response = $this->actingAs($this->user)->postJson('/api/v1/security/encryption/keys', [
                'key_name' => "Test $usage",
                'key_type' => 'AES-256-GCM',
                'key_usage' => $usage,
                'key_length_bits' => 256,
                'vault_reference' => "vault:$usage:123",
            ]);

            $response->assertCreated();
        }
    }

    public function test_unauthenticated_cannot_access(): void
    {
        $response = $this->getJson('/api/v1/security/encryption/keys');

        $response->assertUnauthorized();
    }

    public function test_key_length_bits_validation(): void
    {
        $response = $this->actingAs($this->user)->postJson('/api/v1/security/encryption/keys', [
            'key_name' => 'Test',
            'key_type' => 'AES-256-GCM',
            'key_usage' => 'data_encryption',
            'key_length_bits' => 100,
            'vault_reference' => 'vault:test:123',
        ]);

        $response->assertUnprocessable();
    }

    public function test_vault_reference_required(): void
    {
        $response = $this->actingAs($this->user)->postJson('/api/v1/security/encryption/keys', [
            'key_name' => 'Test',
            'key_type' => 'AES-256-GCM',
            'key_usage' => 'data_encryption',
            'key_length_bits' => 256,
        ]);

        $response->assertUnprocessable();
    }
}
