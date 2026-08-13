<?php

declare(strict_types=1);

namespace Modules\Helpdesk\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Helpdesk\Models\ForumPost;

/** @extends Factory<ForumPost> */
class ForumPostFactory extends Factory
{
    protected $model = ForumPost::class;

    public function definition(): array
    {
        return [
            'title' => $this->faker->sentence(),
            'content' => $this->faker->paragraphs(2, true),
            'author_id' => User::factory(),
            'category' => $this->faker->randomElement(['general', 'billing', 'technical', 'feature-request']),
            'votes' => 0,
            'views' => $this->faker->numberBetween(0, 100),
            'accepted_answer_id' => null,
            'status' => 'open',
        ];
    }
}
