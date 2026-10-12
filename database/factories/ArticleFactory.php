<?php

namespace Database\Factories;

use App\Models\Article;
use App\Models\NewsSource;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Article>
 */
class ArticleFactory extends Factory
{
    protected $model = Article::class;

    public function definition(): array
    {
        return [
            'news_source_id' => NewsSource::factory(),
            'guid' => fake()->uuid(),
            'title' => fake()->sentence(),
            'summary' => fake()->paragraph(),
            'url' => fake()->url(),
            'published_at' => now()->subHours(fake()->numberBetween(1, 48)),
            'country' => 'PK',
            'language' => 'en',
        ];
    }
}
