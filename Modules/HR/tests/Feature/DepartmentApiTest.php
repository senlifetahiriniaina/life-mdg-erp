<?php

use Modules\HR\Models\Department;

describe('Department API', function () {
    beforeEach(function () {
        $this->user = actingAsUser('admin');
    });

    test('can list departments', function () {
        Department::factory()->count(3)->create();

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/hr/departments');

        $response->assertStatus(200)
            ->assertJsonCount(3, 'data');
    });

    test('can create a department', function () {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/hr/departments', [
                'name' => 'Sales',
                'code' => 'SALES',
                'budget_allocation' => 500000,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.name', 'Sales');

        $this->assertDatabaseHas('hr_departments', ['code' => 'SALES']);
    });

    test('can update a department', function () {
        $dept = Department::factory()->create();

        $response = $this->actingAs($this->user, 'sanctum')
            ->patchJson("/api/v1/hr/departments/{$dept->id}", [
                'budget_allocation' => 750000,
            ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('hr_departments', [
            'id' => $dept->id,
            'budget_allocation' => 750000,
        ]);
    });

    test('can get department metrics', function () {
        $dept = Department::factory()->create();

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/hr/departments/{$dept->id}/metrics");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'total_employees',
                'positions',
                'budget_allocated',
            ]);
    });
});
