<?php

declare(strict_types=1);

namespace Modules\Helpdesk\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Helpdesk\Models\Forum;
use Modules\Helpdesk\Models\ForumThread;

/** @extends Factory<ForumThread> */
class ForumThreadFactory extends Factory
{
    protected $model = ForumThread::class;

    public function definition(): array
    {
        return [
            'forum_id'    => Forum::factory(),
            'author_id'   => User::factory(),
            'title'       => $this->faker->sentence(),
            'content'     => $this->faker->paragraphs(2, true),
            'status'      => 'open',
            'views'       => $this->faker->numberBetween(0, 200),
            'upvotes'     => 0,
            'is_answered' => false,
        ];
    }
}
