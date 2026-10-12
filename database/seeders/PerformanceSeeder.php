<?php

namespace Database\Seeders;

use App\Enums\Category;
use App\Enums\Sentiment;
use App\Enums\Visibility;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Synthetic data for the performance evaluation in the paper.
 *
 *   php artisan db:seed --class=PerformanceSeeder
 *
 * Sizes can be overridden with PERF_USERS, PERF_POSTS_PER_USER,
 * PERF_FRIENDS_PER_USER and PERF_ARTICLES. Every account is fictional;
 * all use the password "password" and usernames perf_<n>.
 */
class PerformanceSeeder extends Seeder
{
    private const WORDS = [
        'budget', 'inflation', 'cricket', 'match', 'election', 'rain', 'flood', 'traffic', 'metro', 'petrol',
        'school', 'exam', 'hospital', 'vaccine', 'film', 'drama', 'phone', 'internet', 'startup', 'market',
        'acha', 'bura', 'bohat', 'mehngai', 'team', 'jeet', 'weather', 'heat', 'train', 'flight', 'concert',
        'policy', 'court', 'protest', 'stock', 'rupee', 'dollar', 'goal', 'wicket', 'storm',
    ];

    public function run(): void
    {
        $users = (int) env('PERF_USERS', 1000);
        $postsPerUser = (int) env('PERF_POSTS_PER_USER', 50);
        $friendsPerUser = (int) env('PERF_FRIENDS_PER_USER', 20);
        $articles = (int) env('PERF_ARTICLES', 10000);
        mt_srand(42);

        $now = now();
        $password = Hash::make('password');
        $countries = ['PK', 'PK', 'PK', 'GB', 'US', 'NG', 'IN'];

        $this->command?->info("Users: {$users}");
        $firstId = (int) DB::table('users')->max('id') + 1;
        foreach (array_chunk(range(1, $users), 500) as $chunk) {
            DB::table('users')->insert(array_map(fn ($i) => [
                'name' => "Perf User {$i}",
                'username' => "perf_{$i}",
                'email' => "perf_{$i}@example.test",
                'email_verified_at' => $now,
                'password' => $password,
                'role' => 'user',
                'country' => $countries[$i % count($countries)],
                'created_at' => $now->copy()->subDays(mt_rand(0, 365)),
                'updated_at' => $now,
            ], $chunk));
        }
        $ids = range($firstId, $firstId + $users - 1);

        $this->command?->info('Friendships');
        $pairs = [];
        foreach ($ids as $u) {
            for ($k = 0; $k < $friendsPerUser / 2; $k++) {
                $v = $ids[mt_rand(0, $users - 1)];
                if ($u === $v) {
                    continue;
                }
                $pairs[min($u, $v) . ':' . max($u, $v)] = [$u, $v];
            }
        }
        foreach (array_chunk(array_values($pairs), 1000) as $chunk) {
            DB::table('friendships')->insert(array_map(fn ($p) => [
                'requester_id' => $p[0], 'addressee_id' => $p[1],
                'user_low_id' => min($p), 'user_high_id' => max($p),
                'status' => 'accepted', 'accepted_at' => $now, 'created_at' => $now, 'updated_at' => $now,
            ], $chunk));
        }

        $this->command?->info('Posts: ' . ($users * $postsPerUser));
        $visibilities = [Visibility::Public, Visibility::Public, Visibility::Public, Visibility::Friends, Visibility::Friends, Visibility::OnlyMe];
        $sentiments = Sentiment::cases();
        $categories = array_filter(Category::cases(), fn ($c) => $c !== Category::Other);
        $batch = [];
        foreach ($ids as $u) {
            for ($k = 0; $k < $postsPerUser; $k++) {
                $created = Carbon::createFromTimestamp($now->timestamp - mt_rand(0, 30 * 86400));
                $batch[] = [
                    'user_id' => $u,
                    'body' => $this->sentence(),
                    'visibility' => $visibilities[mt_rand(0, count($visibilities) - 1)]->value,
                    'country' => $countries[$u % count($countries)],
                    'language' => 'en',
                    'sentiment' => $sentiments[mt_rand(0, 2)]->value,
                    'sentiment_score' => mt_rand(40, 99) / 100,
                    'category' => $categories[array_rand($categories)]->value,
                    'analysed_at' => $created,
                    'created_at' => $created,
                    'updated_at' => $created,
                ];
                if (count($batch) === 1000) {
                    DB::table('posts')->insert($batch);
                    $batch = [];
                }
            }
        }
        if ($batch) {
            DB::table('posts')->insert($batch);
        }

        $this->command?->info("Articles: {$articles}");
        $sourceId = DB::table('news_sources')->insertGetId([
            'name' => 'Synthetic wire', 'feed_url' => 'https://perf.example.test/feed.xml', 'language' => 'en',
            'active' => false, 'created_at' => $now, 'updated_at' => $now,
        ]);
        foreach (array_chunk(range(1, $articles), 1000) as $chunk) {
            DB::table('articles')->insert(array_map(function ($i) use ($sourceId, $now, $sentiments, $categories) {
                $published = Carbon::createFromTimestamp($now->timestamp - mt_rand(0, 30 * 86400));

                return [
                    'news_source_id' => $sourceId, 'guid' => "perf-{$i}", 'title' => ucfirst($this->sentence()),
                    'summary' => $this->sentence() . ' ' . $this->sentence(), 'url' => "https://perf.example.test/{$i}",
                    'published_at' => $published, 'country' => 'PK', 'language' => 'en',
                    'category' => $categories[array_rand($categories)]->value,
                    'sentiment' => $sentiments[mt_rand(0, 2)]->value, 'sentiment_score' => mt_rand(40, 99) / 100,
                    'analysed_at' => $published, 'created_at' => $published, 'updated_at' => $published,
                ];
            }, $chunk));
        }
    }

    private function sentence(): string
    {
        $n = mt_rand(8, 25);
        $words = [];
        for ($i = 0; $i < $n; $i++) {
            $words[] = self::WORDS[mt_rand(0, count(self::WORDS) - 1)];
        }

        return implode(' ', $words);
    }
}
