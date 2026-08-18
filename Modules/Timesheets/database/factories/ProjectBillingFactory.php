<?php

declare(strict_types=1);

namespace Modules\Timesheets\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Projects\Models\Project;
use Modules\Timesheets\Models\ProjectBilling;

/**
 * Chantier 8.4: was scaffold boilerplate (fake()->word() on every FK/date/
 * numeric column, plus a dozen fields — name/title/slug/code/email/phone/
 * quantity/price/cost — that don't exist on this model at all) — rewritten
 * to match ProjectBilling's real $fillable/$casts.
 *
 * @extends Factory<ProjectBilling>
 */
class ProjectBillingFactory extends Factory
{
    protected $model = ProjectBilling::class;

    public function definition(): array
    {
        $amount = fake()->randomFloat(2, 500_000, 10_000_000);
        $tva = round($amount * 0.18, 2);

        return [
            'project_id' => Project::factory(),
            'reference' => 'BILL-'.now()->year.'-'.fake()->unique()->numberBetween(1, 9999),
            'billing_type' => fake()->randomElement(['fixed', 'milestone', 'percentage', 'time_material']),
            'amount' => $amount,
            'tva_amount' => $tva,
            'total_ttc' => round($amount + $tva, 2),
            'status' => 'draft',
            'ohada_account' => '7061',
            'description' => fake()->sentence(),
            'billing_date' => fake()->dateTimeBetween('-30 days', 'now')->format('Y-m-d'),
            'milestone_id' => null,
            'percentage' => null,
            'period_start' => null,
            'period_end' => null,
            'invoice_reference' => null,
        ];
    }
}
