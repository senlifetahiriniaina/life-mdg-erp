<?php

namespace Modules\Accounting\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Accounting\Models\FiscalYear;
use Modules\Accounting\Models\Journal;
use Modules\Accounting\Models\JournalEntry;

class JournalEntryFactory extends Factory
{
    protected $model = JournalEntry::class;

    public function definition(): array
    {
        return [
            'journal_id' => Journal::factory(),
            'fiscal_year_id' => FiscalYear::factory(),
            'created_by' => User::factory(),
            'reference' => 'JE-' . strtoupper($this->faker->unique()->lexify('??????')),
            'date' => now(),
            'description' => $this->faker->sentence(),
            'status' => 'draft',
            'currency' => 'USD',
            'exchange_rate' => 1.0,
            'source_type' => 'App\Models\User',
            'source_id' => User::factory(),
        ];
    }

    public function posted()
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'posted',
            'posted_at' => now(),
        ]);
    }

    public function cancelled()
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'cancelled',
        ]);
    }
}
