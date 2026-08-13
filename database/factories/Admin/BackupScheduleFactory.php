<?php

declare(strict_types=1);

namespace Database\Factories\Admin;

use App\Models\Admin\BackupSchedule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BackupSchedule>
 */
class BackupScheduleFactory extends Factory
{
    protected $model = BackupSchedule::class;

    public function definition(): array
    {
        return [
            'type'           => $this->faker->randomElement(['database', 'files', 'full']),
            'frequency'      => $this->faker->randomElement(['hourly', 'daily', 'weekly', 'monthly']),
            'time_of_day'    => $this->faker->time('H:i'),
            'retention_days' => $this->faker->numberBetween(7, 90),
            'storage_driver' => $this->faker->randomElement(['local', 's3', 'gcs', 'azure_blob']),
            'enabled'        => true,
            'last_run_at'    => null,
        ];
    }
}
