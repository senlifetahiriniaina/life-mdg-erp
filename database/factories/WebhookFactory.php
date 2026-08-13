<?php
declare(strict_types=1);
namespace Database\Factories;

use App\Models\User;
use App\Models\Webhook;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class WebhookFactory extends Factory
{
    protected $model = Webhook::class;

    public function definition(): array
    {
        return [
            'user_id'     => User::factory(),
            'url'         => $this->faker->url(),
            'secret'      => Str::random(32),
            'events'      => ['crm.contact.created'],
            'is_active'   => true,
            'description' => $this->faker->sentence(),
        ];
    }
}
