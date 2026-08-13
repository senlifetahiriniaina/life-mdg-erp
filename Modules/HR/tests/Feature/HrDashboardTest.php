<?php

declare(strict_types=1);

use App\Models\User;
use Modules\HR\Models\Department;
use Modules\HR\Models\Employee;


describe('HR Dashboard API', function () {

    beforeEach(function () {
        $this->user = User::factory()->create();
    });

    it('dashboard returns correct structure', function () {
        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/hr/dashboard');

        $response->assertOk()
            ->assertJsonStructure([
                'stats' => [
                    'headcount',
                    'absences_today',
                    'open_positions',
                    'avg_tenure_months',
                ],
                'department_distribution',
                'leave_stats' => [
                    'pending_requests',
                    'approved_this_month',
                ],
                'recruitment_funnel',
                'attendance' => [
                    'present',
                    'remote',
                    'on_leave',
                    'absent',
                    'total',
                ],
            ]);
    });

    it('realtime endpoint returns lightweight stats', function () {
        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/hr/dashboard/realtime');

        $response->assertOk()
            ->assertJsonStructure([
                'headcount',
                'absences_today',
                'attendance' => [
                    'present',
                    'remote',
                    'on_leave',
                    'absent',
                    'total',
                ],
            ]);
    });

    it('headcount is accurate', function () {
        Employee::factory()->count(4)->create(['status' => 'active']);
        Employee::factory()->count(2)->create(['status' => 'inactive']);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/hr/dashboard');

        $response->assertOk();
        expect($response->json('stats.headcount'))->toBe(4);
    });

    it('department distribution sums to total headcount', function () {
        $deptA = Department::factory()->create(['name' => 'Engineering']);
        $deptB = Department::factory()->create(['name' => 'Sales']);

        Employee::factory()->count(3)->create(['status' => 'active', 'department_id' => $deptA->id]);
        Employee::factory()->count(2)->create(['status' => 'active', 'department_id' => $deptB->id]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/hr/dashboard');

        $response->assertOk();

        $dist = $response->json('department_distribution');
        $total = array_sum($dist);

        expect($total)->toBe(5);
    });

    it('unauthenticated request is rejected', function () {
        $this->getJson('/api/v1/hr/dashboard')->assertUnauthorized();
        $this->getJson('/api/v1/hr/dashboard/realtime')->assertUnauthorized();
    });
});
