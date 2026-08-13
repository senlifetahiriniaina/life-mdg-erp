<?php

declare(strict_types=1);

namespace Modules\Helpdesk\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Modules\Helpdesk\Models\KbArticle;
use Modules\Helpdesk\Models\KbCategory;

class KbArticleFactory extends Factory
{
    protected $model = KbArticle::class;

    public function definition(): array
    {
        $title = $this->faker->sentence();

        return [
            'category_id' => KbCategory::factory(),
            'created_by' => User::factory(),
            'author_id' => null,
            'title' => $title,
            'slug' => Str::slug($title).'-'.$this->faker->unique()->randomNumber(4),
            'content' => $this->faker->paragraphs(3, true),
            'excerpt' => $this->faker->optional()->sentence(),
            'tags' => null,
            'status' => 'draft',
            'view_count' => 0,
            'helpful_count' => 0,
            'not_helpful_count' => 0,
            'published_at' => null,
            'reading_time_minutes' => $this->faker->numberBetween(1, 15),
        ];
    }

    public function published(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'published',
            'published_at' => now(),
        ]);
    }
}
