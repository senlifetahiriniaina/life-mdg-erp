<?php

declare(strict_types=1);

namespace Modules\Projects\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Projects\Models\TimeEntry;

/** @extends Factory<TimeEntry> */
class TimeEntryFactory extends Factory
{
    protected $model = TimeEntry::class;

    public function definition(): array
    {
        $startedAt = fake()->dateTimeBetween('-7 days', '-1 hour');
        $endedAt = fake()->dateTimeBetween($startedAt, 'now');

        $durationMinutes = (int) ceil((strtotime($endedAt->format('Y-m-d H:i:s')) - strtotime($startedAt->format('Y-m-d H:i:s'))) / 60);

        return [
            'project_id' => ProjectFactory::new(),
            'task_id' => null,
            'user_id' => User::factory(),
            'description' => fake()->optional()->sentence(),
            'started_at' => $startedAt,
            'ended_at' => $endedAt,
            'duration_minutes' => $durationMinutes,
            'hourly_rate' => fake()->randomFloat(2, 50, 200),
            'billable' => true,
            'billed' => false,
            'invoice_id' => null,
        ];
    }

    public function running(): static
    {
        return $this->state(fn (array $attributes) => [
            'ended_at' => null,
            'duration_minutes' => null,
        ]);
    }

    public function billed(): static
    {
        return $this->state(fn (array $attributes) => [
            'billed' => true,
        ]);
    }

    public function nonBillable(): static
    {
        return $this->state(fn (array $attributes) => [
            'billable' => false,
        ]);
    }
}
