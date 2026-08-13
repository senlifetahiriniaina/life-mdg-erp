<?php

declare(strict_types=1);

namespace Modules\Helpdesk\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Helpdesk\Models\BotDeflection;

/** @extends Factory<BotDeflection> */
class BotDeflectionFactory extends Factory
{
    protected $model = BotDeflection::class;

    public function definition(): array
    {
        return [
            'question' => $this->faker->sentence().'?',
            'matched_article_id' => null,
            'deflected' => $this->faker->boolean(60),
            'ticket_created' => false,
            'session_id' => $this->faker->uuid(),
        ];
    }
}
