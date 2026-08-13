<?php

declare(strict_types=1);

namespace Database\Factories\Admin;

use App\Models\Admin\Backup;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Backup>
 */
class BackupFactory extends Factory
{
    protected $model = Backup::class;

    public function definition(): array
    {
        return [
            'type'           => $this->faker->randomElement(['database', 'files', 'full']),
            'status'         => $this->faker->randomElement(['pending', 'running', 'completed', 'failed']),
            'size_bytes'     => $this->faker->numberBetween(50_000_000, 2_000_000_000),
            'file_path'      => 'backups/backup_' . now()->format('YmdHis') . '.sql.gz',
            'storage_driver' => $this->faker->randomElement(['local', 's3', 'gcs', 'azure_blob']),
            'notes'          => null,
            'triggered_by'   => null,
            'started_at'     => now()->subMinutes(10),
            'completed_at'   => now(),
        ];
    }
}
