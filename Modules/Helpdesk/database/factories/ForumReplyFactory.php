<?php

declare(strict_types=1);

namespace Modules\Helpdesk\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Helpdesk\Models\ForumReply;
use Modules\Helpdesk\Models\ForumThread;

/** @extends Factory<ForumReply> */
class ForumReplyFactory extends Factory
{
    protected $model = ForumReply::class;

    public function definition(): array
    {
        return [
            'thread_id'          => ForumThread::factory(),
            'author_id'          => User::factory(),
            'content'            => $this->faker->paragraph(),
            'is_accepted_answer' => false,
            'upvotes'            => 0,
        ];
    }
}
