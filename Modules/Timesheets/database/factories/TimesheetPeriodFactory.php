<?php

declare(strict_types=1);

namespace Modules\Timesheets\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\HR\Models\Employee;
use Modules\Timesheets\Models\TimesheetPeriod;

/**
 * Chantier 8.4: was scaffold boilerplate (fake()->word() on every FK/date/
 * numeric column, plus a dozen fields — name/title/slug/code/email/phone/
 * quantity/price/cost — that don't exist on this model at all) — rewritten
 * to match TimesheetPeriod's real $fillable/$casts.
 *
 * @extends Factory<TimesheetPeriod>
 */
class TimesheetPeriodFactory extends Factory
{
    protected $model = TimesheetPeriod::class;

    public function definition(): array
    {
        // (employee_id, period_start) is unique — fake()->unique() ensures
        // multiple factory calls for the same employee in one test don't
        // collide on a randomly-picked Monday.
        $weeksAgo = fake()->unique()->numberBetween(0, 51);
        $periodStart = now()->subWeeks($weeksAgo)->startOfWeek();
        $periodEnd = $periodStart->copy()->addDays(6);

        return [
            'tenant_id' => 1,
            'employee_id' => Employee::factory(),
            'period_start' => $periodStart->format('Y-m-d'),
            'period_end' => $periodEnd->format('Y-m-d'),
            'total_hours' => fake()->randomFloat(2, 0, 40),
            'billable_hours' => fake()->randomFloat(2, 0, 40),
            'overtime_hours' => 0,
            'status' => 'draft',
            'submitted_by' => null,
            'submitted_at' => null,
            'approved_by' => null,
            'approved_at' => null,
            'rejected_reason' => null,
        ];
    }

    public function submitted(): static
    {
        return $this->state(fn () => [
            'status' => 'submitted',
            'submitted_at' => now(),
        ]);
    }

    public function approved(): static
    {
        return $this->state(fn () => [
            'status' => 'approved',
            'submitted_at' => now()->subDay(),
            'approved_at' => now(),
        ]);
    }
}
