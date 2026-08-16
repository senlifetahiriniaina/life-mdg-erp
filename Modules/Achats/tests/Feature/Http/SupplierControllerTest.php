<?php

namespace Modules\Achats\Tests\Feature\Http;

use App\Models\User;
use Modules\Achats\Models\Supplier;
use Tests\TestCase;

class SupplierControllerTest extends TestCase
{
    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        if (\Spatie\Permission\Models\Permission::count() === 0) {
            $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        }
        $this->user = User::factory()->create();
        $this->user->assignRole('purchasing-manager');
    }

    public function test_can_list_suppliers()
    {
        Supplier::factory()->count(5)->create();

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/achats/suppliers');

        $response->assertStatus(200);
        $response->assertJsonStructure(['data', 'meta']);
    }

    public function test_can_create_supplier()
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/achats/suppliers', [
                'name' => 'Tech Supplies Inc',
                'email' => 'contact@techsupplies.com',
                'phone' => '+1-555-0123',
                'country' => 'USA',
            ]);

        $response->assertStatus(201);
        $response->assertJsonStructure(['id', 'name', 'code']);
        $this->assertDatabaseHas('achats_suppliers', [
            'name' => 'Tech Supplies Inc',
        ]);
    }

    public function test_can_view_supplier()
    {
        $supplier = Supplier::factory()->create();

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/achats/suppliers/{$supplier->id}");

        $response->assertStatus(200);
        $response->assertJsonPath('id', $supplier->id);
        $response->assertJsonPath('name', $supplier->name);
    }

    public function test_can_update_supplier()
    {
        $supplier = Supplier::factory()->create();

        $response = $this->actingAs($this->user, 'sanctum')
            ->putJson("/api/v1/achats/suppliers/{$supplier->id}", [
                'email' => 'newemail@test.com',
                'phone' => '+1-555-9999',
            ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('achats_suppliers', [
            'id' => $supplier->id,
            'email' => 'newemail@test.com',
        ]);
    }

    public function test_can_delete_supplier()
    {
        $supplier = Supplier::factory()->create();

        $response = $this->actingAs($this->user, 'sanctum')
            ->deleteJson("/api/v1/achats/suppliers/{$supplier->id}");

        $response->assertStatus(204);
        $this->assertSoftDeleted('achats_suppliers', ['id' => $supplier->id]);
    }

    public function test_can_get_supplier_performance_metrics()
    {
        $supplier = Supplier::factory()->create();

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/achats/suppliers/{$supplier->id}/performance");

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'supplier_id',
            'supplier_name',
            'metrics' => [
                'total_orders',
                'total_spent',
                'average_order_value',
                'on_time_delivery',
                'quality_score',
            ],
        ]);
    }

    public function test_can_get_supplier_quote_history()
    {
        $supplier = Supplier::factory()->create();

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/achats/suppliers/{$supplier->id}/quotes");

        $response->assertStatus(200);
    }

    public function test_requires_authentication()
    {
        $response = $this->getJson('/api/v1/achats/suppliers');

        $response->assertStatus(401);
    }

    public function test_filter_suppliers_by_active_status()
    {
        Supplier::factory()->count(3)->create(['is_active' => true]);
        Supplier::factory()->count(2)->create(['is_active' => false]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/achats/suppliers?is_active=true');

        $response->assertStatus(200);
        $this->assertCount(3, $response->json('data'));
    }

    public function test_search_suppliers_by_name()
    {
        Supplier::factory()->create(['name' => 'Acme Corporation']);
        Supplier::factory()->create(['name' => 'Tech Supplies']);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/achats/suppliers?search=Acme');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals('Acme Corporation', $response->json('data.0.name'));
    }
}
