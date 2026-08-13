<?php

namespace Modules\Timesheets\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Timesheets\Models\TimesheetEntry;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TimesheetEntryControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // $this->seed(); // Removed: too slow for unit tests
    }

    #[Test]
    public function can_list_timesheet_entries()
    {
        $user = User::factory()->create();
        $entries = TimesheetEntry::factory()->count(5)->create([
            'employee_id' => $user->id,
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
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/timesheets/entries', [
                'employee_id' => $user->id,
                'entry_date' => now()->subDay()->format('Y-m-d'),
                'hours_worked' => 8,
                'description' => 'Completed project development tasks',
                'task_id' => null,
                'notes' => 'All tests passing',
            ]);

        $response->assertCreated()
            ->assertJsonStructure(['data' => ['id', 'employee_id', 'status']]);

        $this->assertDatabaseHas('timesheet_entries', [
            'employee_id' => $user->id,
            'hours_worked' => 8,
            'status' => 'draft',
        ]);
    }

    #[Test]
    public function cannot_create_entry_with_invalid_hours()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/timesheets/entries', [
                'employee_id' => $user->id,
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
        $user = User::factory()->create();
        $entry = TimesheetEntry::factory()->create(['employee_id' => $user->id]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/timesheets/entries/{$entry->id}");

        $response->assertOk()
            ->assertJsonPath('data.id', $entry->id);
    }

    #[Test]
    public function can_update_draft_timesheet_entry()
    {
        $user = User::factory()->create();
        $entry = TimesheetEntry::factory()->create([
            'employee_id' => $user->id,
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
        $user = User::factory()->create();
        $entry = TimesheetEntry::factory()->create([
            'employee_id' => $user->id,
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
        $user = User::factory()->create();
        $entry = TimesheetEntry::factory()->create([
            'employee_id' => $user->id,
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
        $user = User::factory()->create();
        $entry = TimesheetEntry::factory()->create([
            'employee_id' => $user->id,
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
        $approver = actingAsUser('admin');
        $user = User::factory()->create();
        $entry = TimesheetEntry::factory()->create([
            'employee_id' => $user->id,
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
        $approver = actingAsUser('admin');
        $user = User::factory()->create();
        $entry = TimesheetEntry::factory()->create([
            'employee_id' => $user->id,
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
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        TimesheetEntry::factory()->count(3)->create(['employee_id' => $user1->id]);
        TimesheetEntry::factory()->count(2)->create(['employee_id' => $user2->id]);

        $response = $this->actingAs($user1, 'sanctum')
            ->getJson("/api/v1/timesheets/entries?employee_id={$user1->id}");

        $response->assertOk()
            ->assertJsonCount(3, 'data');
    }

    #[Test]
    public function can_filter_entries_by_status()
    {
        $user = User::factory()->create();
        TimesheetEntry::factory()->count(3)->create([
            'employee_id' => $user->id,
            'status' => 'draft',
        ]);
        TimesheetEntry::factory()->count(2)->create([
            'employee_id' => $user->id,
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
        $user = User::factory()->create();
        TimesheetEntry::factory()->create([
            'employee_id' => $user->id,
            'entry_date' => now()->subDays(5),
        ]);
        TimesheetEntry::factory()->create([
            'employee_id' => $user->id,
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
        $approver = actingAsUser('admin');
        User::factory()->create();
        TimesheetEntry::factory()->count(5)->create(['status' => 'submitted']);
        TimesheetEntry::factory()->count(2)->create(['status' => 'approved']);

        $response = $this->actingAs($approver, 'sanctum')
            ->getJson('/api/v1/timesheets/entries/pending/approvals');

        $response->assertOk()
            ->assertJsonCount(5, 'data');
    }
}
