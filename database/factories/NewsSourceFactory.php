<?php

namespace Database\Factories;

use App\Models\NewsSource;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<NewsSource>
 */
class NewsSourceFactory extends Factory
{
    protected $model = NewsSource::class;

    public function definition(): array
    {
        return [
            'name' => fake()->company() . ' News',
            'feed_url' => 'https://news.example.test/' . fake()->unique()->slug() . '.xml',
            'country' => 'PK',
            'language' => 'en',
            'active' => true,
        ];
    }
}
