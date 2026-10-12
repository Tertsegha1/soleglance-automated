<?php

namespace Database\Factories;

use App\Enums\Visibility;
use App\Models\Post;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Post>
 */
class PostFactory extends Factory
{
    protected $model = Post::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'body' => fake()->paragraph(),
            'visibility' => Visibility::Public,
            'country' => 'PK',
        ];
    }

    public function visibility(Visibility $v): static
    {
        return $this->state(fn () => ['visibility' => $v]);
    }
}
