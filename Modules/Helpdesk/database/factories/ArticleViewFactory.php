<?php

declare(strict_types=1);

namespace Modules\Helpdesk\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Helpdesk\Models\ArticleView;
use Modules\Helpdesk\Models\KbArticle;

class ArticleViewFactory extends Factory
{
    protected $model = ArticleView::class;

    public function definition(): array
    {
        return [
            'article_id' => KbArticle::factory(),
            'user_id' => null,
            'ip_address' => $this->faker->ipv4(),
            'viewed_at' => now(),
            'helpful' => null,
        ];
    }
}
