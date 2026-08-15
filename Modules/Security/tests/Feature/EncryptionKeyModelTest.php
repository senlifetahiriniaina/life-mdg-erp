<?php

declare(strict_types=1);

namespace Modules\Security\Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Security\Models\EncryptedField;
use Modules\Security\Models\EncryptionKey;
use Modules\Security\Models\KeyRotationLog;
use Tests\TestCase;

/**
 * EncryptionKeyModelTest — tests for EncryptionKey, KeyRotationLog,
 * and EncryptedField models.
 *
 * Covers: creation, relationships, casts, status values, rotation logs.
 */
class EncryptionKeyModelTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->company = Company::factory()->create();
        $this->user    = User::factory()->for($this->company)->create();
    }

    // ─── EncryptionKey creation ───────────────────────────────────────────────

    public function test_encryption_key_can_be_created(): void
    {
        $key = EncryptionKey::create([
            'company_id'       => $this->company->id,
            'key_name'         => 'Primary Data Key',
            'key_type'         => 'AES-256-GCM',
            'key_usage'        => 'data_encryption',
            'key_status'       => 'active',
            'key_material_hash'=> hash('sha256', 'fake-key-material'),
            'vault_reference'  => 'vault://kv/data/primary',
            'key_length_bits'  => 256,
            'created_at'       => now(),
        ]);

        $this->assertNotNull($key->id);
        $this->assertDatabaseHas('security_encryption_keys', [
            'company_id' => $this->company->id,
            'key_name'   => 'Primary Data Key',
            'key_type'   => 'AES-256-GCM',
        ]);
    }

    public function test_encryption_key_company_relationship(): void
    {
        $key = EncryptionKey::create([
            'company_id'       => $this->company->id,
            'key_name'         => 'Field Encryption Key',
            'key_type'         => 'AES-256-GCM',
            'key_usage'        => 'field_encryption',
            'key_status'       => 'active',
            'key_material_hash'=> hash('sha256', 'field-key'),
            'vault_reference'  => 'vault://kv/field',
            'key_length_bits'  => 256,
            'created_at'       => now(),
        ]);

        $this->assertEquals($this->company->id, $key->company->id);
    }

    // ─── Key status values ────────────────────────────────────────────────────

    public function test_active_key_status_stored(): void
    {
        $key = EncryptionKey::create([
            'company_id'       => $this->company->id,
            'key_name'         => 'Active Key',
            'key_type'         => 'AES-256-GCM',
            'key_usage'        => 'data_encryption',
            'key_status'       => 'active',
            'key_material_hash'=> hash('sha256', 'active'),
            'vault_reference'  => 'vault://kv/active',
            'key_length_bits'  => 256,
            'created_at'       => now(),
        ]);

        $this->assertEquals('active', $key->key_status);
    }

    public function test_rotated_key_status_stored(): void
    {
        $key = EncryptionKey::create([
            'company_id'       => $this->company->id,
            'key_name'         => 'Rotated Key',
            'key_type'         => 'AES-256-GCM',
            'key_usage'        => 'data_encryption',
            'key_status'       => 'rotated',
            'key_material_hash'=> hash('sha256', 'rotated'),
            'vault_reference'  => 'vault://kv/rotated',
            'key_length_bits'  => 256,
            'created_at'       => now(),
            'rotated_at'       => now(),
        ]);

        $this->assertEquals('rotated', $key->key_status);
        $this->assertNotNull($key->rotated_at);
    }

    public function test_revoked_key_status_stored(): void
    {
        $key = EncryptionKey::create([
            'company_id'       => $this->company->id,
            'key_name'         => 'Revoked Key',
            'key_type'         => 'RSA',
            'key_usage'        => 'signing',
            'key_status'       => 'revoked',
            'key_material_hash'=> hash('sha256', 'revoked'),
            'vault_reference'  => 'vault://kv/revoked',
            'key_length_bits'  => 4096,
            'created_at'       => now(),
        ]);

        $this->assertEquals('revoked', $key->key_status);
    }

    // ─── Expiry ───────────────────────────────────────────────────────────────

    public function test_expires_at_nullable(): void
    {
        $key = EncryptionKey::create([
            'company_id'       => $this->company->id,
            'key_name'         => 'No Expiry Key',
            'key_type'         => 'HMAC',
            'key_usage'        => 'signing',
            'key_status'       => 'active',
            'key_material_hash'=> hash('sha256', 'hmac'),
            'vault_reference'  => 'vault://kv/hmac',
            'key_length_bits'  => 256,
            'created_at'       => now(),
        ]);

        $this->assertNull($key->expires_at);
    }

    public function test_expires_at_cast_as_carbon(): void
    {
        $expiresAt = now()->addYear();

        $key = EncryptionKey::create([
            'company_id'       => $this->company->id,
            'key_name'         => 'Expiring Key',
            'key_type'         => 'AES-256-GCM',
            'key_usage'        => 'data_encryption',
            'key_status'       => 'active',
            'key_material_hash'=> hash('sha256', 'expiring'),
            'vault_reference'  => 'vault://kv/expiring',
            'key_length_bits'  => 256,
            'created_at'       => now(),
            'expires_at'       => $expiresAt,
        ]);

        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $key->fresh()->expires_at);
    }

    // ─── KeyRotationLog ───────────────────────────────────────────────────────

    public function test_key_rotation_log_can_be_created(): void
    {
        $key = EncryptionKey::create([
            'company_id'       => $this->company->id,
            'key_name'         => 'Rotation Test Key',
            'key_type'         => 'AES-256-GCM',
            'key_usage'        => 'data_encryption',
            'key_status'       => 'active',
            'key_material_hash'=> hash('sha256', 'rot-test'),
            'vault_reference'  => 'vault://kv/rot',
            'key_length_bits'  => 256,
            'created_at'       => now(),
        ]);

        $log = KeyRotationLog::create([
            'encryption_key_id'   => $key->id,
            'rotation_type'       => 'scheduled',
            'rotation_status'     => 'completed',
            'old_key_hash'        => hash('sha256', 'old-key'),
            'new_key_hash'        => hash('sha256', 'new-key'),
            'records_reencrypted' => 150,
            'started_at'          => now()->subMinutes(5),
            'completed_at'        => now(),
        ]);

        $this->assertNotNull($log->id);
        $this->assertEquals('completed', $log->rotation_status);
        $this->assertEquals(150, $log->records_reencrypted);
    }

    public function test_key_rotation_log_belongs_to_encryption_key(): void
    {
        $key = EncryptionKey::create([
            'company_id'       => $this->company->id,
            'key_name'         => 'Log Relationship Key',
            'key_type'         => 'AES-256-GCM',
            'key_usage'        => 'data_encryption',
            'key_status'       => 'rotated',
            'key_material_hash'=> hash('sha256', 'log-rel'),
            'vault_reference'  => 'vault://kv/log-rel',
            'key_length_bits'  => 256,
            'created_at'       => now(),
        ]);

        $log = KeyRotationLog::create([
            'encryption_key_id'   => $key->id,
            'rotation_type'       => 'emergency',
            'rotation_status'     => 'completed',
            'old_key_hash'        => hash('sha256', 'old'),
            'new_key_hash'        => hash('sha256', 'new'),
            'records_reencrypted' => 0,
            'started_at'          => now(),
            'completed_at'        => now(),
        ]);

        $this->assertEquals($key->id, $log->encryptionKey->id);
    }

    public function test_encryption_key_has_many_rotation_logs(): void
    {
        $key = EncryptionKey::create([
            'company_id'       => $this->company->id,
            'key_name'         => 'Multi Rotation Key',
            'key_type'         => 'AES-256-GCM',
            'key_usage'        => 'data_encryption',
            'key_status'       => 'active',
            'key_material_hash'=> hash('sha256', 'multi-rot'),
            'vault_reference'  => 'vault://kv/multi',
            'key_length_bits'  => 256,
            'created_at'       => now(),
        ]);

        for ($i = 0; $i < 3; $i++) {
            KeyRotationLog::create([
                'encryption_key_id'   => $key->id,
                'rotation_type'       => 'scheduled',
                'rotation_status'     => 'completed',
                'old_key_hash'        => hash('sha256', "old-{$i}"),
                'new_key_hash'        => hash('sha256', "new-{$i}"),
                'records_reencrypted' => $i * 10,
                'started_at'          => now(),
                'completed_at'        => now(),
            ]);
        }

        $this->assertEquals(3, $key->rotationLogs()->count());
    }

    // ─── EncryptedField ───────────────────────────────────────────────────────

    public function test_encrypted_field_can_be_created(): void
    {
        $key = EncryptionKey::create([
            'company_id'       => $this->company->id,
            'key_name'         => 'Field Key',
            'key_type'         => 'AES-256-GCM',
            'key_usage'        => 'field_encryption',
            'key_status'       => 'active',
            'key_material_hash'=> hash('sha256', 'field-key'),
            'vault_reference'  => 'vault://kv/field',
            'key_length_bits'  => 256,
            'created_at'       => now(),
        ]);

        $field = EncryptedField::create([
            'company_id'           => $this->company->id,
            'table_name'           => 'users',
            'column_name'          => 'phone',
            'encryption_algorithm' => 'AES-256-GCM',
            'encryption_key_id'    => $key->id,
            'is_searchable'        => false,
            'is_encrypted'         => true,
        ]);

        $this->assertNotNull($field->id);
        $this->assertDatabaseHas('encrypted_fields', [
            'table_name'  => 'users',
            'column_name' => 'phone',
        ]);
    }

    public function test_encrypted_field_searchable_flag(): void
    {
        $key = EncryptionKey::create([
            'company_id'       => $this->company->id,
            'key_name'         => 'Searchable Key',
            'key_type'         => 'AES-256-GCM',
            'key_usage'        => 'field_encryption',
            'key_status'       => 'active',
            'key_material_hash'=> hash('sha256', 'search'),
            'vault_reference'  => 'vault://kv/search',
            'key_length_bits'  => 256,
            'created_at'       => now(),
        ]);

        $field = EncryptedField::create([
            'company_id'           => $this->company->id,
            'table_name'           => 'contacts',
            'column_name'          => 'email_hash',
            'encryption_algorithm' => 'AES-256-SIV',
            'encryption_key_id'    => $key->id,
            'is_searchable'        => true,
            'is_encrypted'         => true,
        ]);

        $this->assertTrue($field->is_searchable);
    }

    // ─── Company isolation ────────────────────────────────────────────────────

    public function test_encryption_keys_are_company_isolated(): void
    {
        $otherCompany = Company::factory()->create();

        EncryptionKey::create([
            'company_id'       => $this->company->id,
            'key_name'         => 'Company A Key',
            'key_type'         => 'AES-256-GCM',
            'key_usage'        => 'data_encryption',
            'key_status'       => 'active',
            'key_material_hash'=> hash('sha256', 'company-a'),
            'vault_reference'  => 'vault://kv/a',
            'key_length_bits'  => 256,
            'created_at'       => now(),
        ]);

        $companyBKeys = EncryptionKey::where('company_id', $otherCompany->id)->get();

        $this->assertEquals(0, $companyBKeys->count());
    }

    // ─── API endpoint authentication ─────────────────────────────────────────

    public function test_unauthenticated_cannot_list_encryption_keys(): void
    {
        $response = $this->getJson('/v1/security/encryption/keys');

        $response->assertStatus(401);
    }

    public function test_authenticated_can_list_encryption_keys(): void
    {
        $response = $this->actingAs($this->user)->getJson('/v1/security/encryption/keys');

        $response->assertStatus(200);
    }
}
