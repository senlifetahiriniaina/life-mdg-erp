<?php

namespace Modules\Timesheets\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\HR\Models\Employee;
use Modules\Timesheets\Models\TimesheetEntry;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Chantier 8.4: employee_id on TimesheetEntry FKs to hr_employees.id, not
 * users.id — this whole file assigned $user->id directly as employee_id,
 * which happened to keep passing only because TimesheetEntryController's
 * non-manager filter and TimesheetEntryPolicy's ownership checks had the
 * exact same mismatch bug (both compared against auth()->id()/$user->id
 * instead of the linked employee's id). Now that both are fixed, every
 * entry needs a real, linked Employee record.
 */
class TimesheetEntryControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'employee', 'guard_name' => 'web']);
    }

    /** @return array{0: User, 1: Employee} */
    private function employeeUser(string $role = 'employee'): array
    {
        $user = User::factory()->create();
        $user->assignRole($role);
        $employee = Employee::factory()->create(['user_id' => $user->id]);

        return [$user, $employee];
    }

    #[Test]
    public function can_list_timesheet_entries()
    {
        [$user, $employee] = $this->employeeUser();
        $entries = TimesheetEntry::factory()->count(5)->create([
            'employee_id' => $employee->id,
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/timesheets/entries');

        $response->assertOk()
            ->assertJsonCount(5, 'data')
            ->assertJsonStructure([
                'data' => [
                    0 => ['id', 'employee_id', 'entry_date', 'hours_worked', 'status'],
                ],
            ]);
    }

    #[Test]
    public function can_create_timesheet_entry()
    {
        [$user, $employee] = $this->employeeUser();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/timesheets/entries', [
                'employee_id' => $employee->id,
                'entry_date' => now()->subDay()->format('Y-m-d'),
                'hours_worked' => 8,
                'description' => 'Completed project development tasks',
                'task_id' => null,
                'notes' => 'All tests passing',
            ]);

        $response->assertCreated()
            ->assertJsonStructure(['data' => ['id', 'employee_id', 'status']]);

        $this->assertDatabaseHas('timesheet_entries', [
            'employee_id' => $employee->id,
            'hours_worked' => 8,
            'status' => 'draft',
        ]);
    }

    #[Test]
    public function cannot_create_entry_with_invalid_hours()
    {
        [$user, $employee] = $this->employeeUser();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/timesheets/entries', [
                'employee_id' => $employee->id,
                'entry_date' => now()->subDay()->format('Y-m-d'),
                'hours_worked' => 25,
                'description' => 'Test entry',
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors('hours_worked');
    }

    #[Test]
    public function can_retrieve_specific_timesheet_entry()
    {
        [$user, $employee] = $this->employeeUser();
        $entry = TimesheetEntry::factory()->create(['employee_id' => $employee->id]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/timesheets/entries/{$entry->id}");

        $response->assertOk()
            ->assertJsonPath('data.id', $entry->id);
    }

    #[Test]
    public function can_update_draft_timesheet_entry()
    {
        [$user, $employee] = $this->employeeUser();
        $entry = TimesheetEntry::factory()->create([
            'employee_id' => $employee->id,
            'status' => 'draft',
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->patchJson("/api/v1/timesheets/entries/{$entry->id}", [
                'hours_worked' => 9,
                'description' => 'Updated description',
            ]);

        $response->assertOk();

        $entry->refresh();
        $this->assertEquals(9, $entry->hours_worked);
        $this->assertEquals('Updated description', $entry->description);
    }

    #[Test]
    public function cannot_update_submitted_timesheet_entry()
    {
        [$user, $employee] = $this->employeeUser();
        $entry = TimesheetEntry::factory()->create([
            'employee_id' => $employee->id,
            'status' => 'submitted',
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->patchJson("/api/v1/timesheets/entries/{$entry->id}", [
                'hours_worked' => 9,
            ]);

        $response->assertForbidden();
    }

    #[Test]
    public function can_delete_draft_timesheet_entry()
    {
        [$user, $employee] = $this->employeeUser();
        $entry = TimesheetEntry::factory()->create([
            'employee_id' => $employee->id,
            'status' => 'draft',
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->deleteJson("/api/v1/timesheets/entries/{$entry->id}");

        $response->assertOk();

        $this->assertDatabaseMissing('timesheet_entries', ['id' => $entry->id]);
    }

    #[Test]
    public function can_submit_timesheet_entry()
    {
        [$user, $employee] = $this->employeeUser();
        $entry = TimesheetEntry::factory()->create([
            'employee_id' => $employee->id,
            'status' => 'draft',
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/timesheets/entries/{$entry->id}/submit");

        $response->assertOk();

        $entry->refresh();
        $this->assertEquals('submitted', $entry->status);
        $this->assertNotNull($entry->submitted_at);
    }

    #[Test]
    public function can_approve_timesheet_entry()
    {
        $approver = $this->actingAsUser('admin');
        [, $employee] = $this->employeeUser();
        $entry = TimesheetEntry::factory()->create([
            'employee_id' => $employee->id,
            'status' => 'submitted',
        ]);

        $response = $this->actingAs($approver, 'sanctum')
            ->postJson("/api/v1/timesheets/entries/{$entry->id}/approve", [
                'notes' => 'Approved',
            ]);

        $response->assertOk();

        $entry->refresh();
        $this->assertEquals('approved', $entry->status);
        $this->assertNotNull($entry->approved_at);
    }

    #[Test]
    public function can_reject_timesheet_entry()
    {
        $approver = $this->actingAsUser('admin');
        [, $employee] = $this->employeeUser();
        $entry = TimesheetEntry::factory()->create([
            'employee_id' => $employee->id,
            'status' => 'submitted',
        ]);

        $response = $this->actingAs($approver, 'sanctum')
            ->postJson("/api/v1/timesheets/entries/{$entry->id}/reject", [
                'notes' => 'Missing details',
            ]);

        $response->assertOk();

        $entry->refresh();
        $this->assertEquals('rejected', $entry->status);
    }

    #[Test]
    public function can_filter_entries_by_employee()
    {
        [$user1, $employee1] = $this->employeeUser();
        [, $employee2] = $this->employeeUser();
        TimesheetEntry::factory()->count(3)->create(['employee_id' => $employee1->id]);
        TimesheetEntry::factory()->count(2)->create(['employee_id' => $employee2->id]);

        $response = $this->actingAs($user1, 'sanctum')
            ->getJson("/api/v1/timesheets/entries?employee_id={$employee1->id}");

        $response->assertOk()
            ->assertJsonCount(3, 'data');
    }

    #[Test]
    public function can_filter_entries_by_status()
    {
        [$user, $employee] = $this->employeeUser();
        TimesheetEntry::factory()->count(3)->create([
            'employee_id' => $employee->id,
            'status' => 'draft',
        ]);
        TimesheetEntry::factory()->count(2)->create([
            'employee_id' => $employee->id,
            'status' => 'approved',
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/timesheets/entries?status=draft');

        $response->assertOk()
            ->assertJsonCount(3, 'data');
    }

    #[Test]
    public function can_filter_entries_by_date_range()
    {
        [$user, $employee] = $this->employeeUser();
        TimesheetEntry::factory()->create([
            'employee_id' => $employee->id,
            'entry_date' => now()->subDays(5),
        ]);
        TimesheetEntry::factory()->create([
            'employee_id' => $employee->id,
            'entry_date' => now(),
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/timesheets/entries?from_date='.now()->subDays(2)->format('Y-m-d'));

        $response->assertOk()
            ->assertJsonCount(1, 'data');
    }

    #[Test]
    public function can_get_pending_approvals()
    {
        $approver = $this->actingAsUser('admin');
        User::factory()->create();
        TimesheetEntry::factory()->count(5)->create(['status' => 'submitted']);
        TimesheetEntry::factory()->count(2)->create(['status' => 'approved']);

        $response = $this->actingAs($approver, 'sanctum')
            ->getJson('/api/v1/timesheets/entries/pending/approvals');

        $response->assertOk()
            ->assertJsonCount(5, 'data');
    }
}
