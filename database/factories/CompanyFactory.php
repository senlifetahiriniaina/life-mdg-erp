<?php
declare(strict_types=1);
namespace Database\Factories;

use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

class CompanyFactory extends Factory
{
    protected $model = Company::class;

    public function definition(): array
    {
        return [
            'name'      => $this->faker->company(),
            'code'      => strtoupper($this->faker->unique()->lexify('????')),
            'currency'  => 'XOF',
            'timezone'  => 'UTC',
            'is_active' => true,
        ];
    }
}
