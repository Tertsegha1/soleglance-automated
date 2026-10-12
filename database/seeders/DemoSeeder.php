<?php

namespace Database\Seeders;

use App\Enums\FriendshipStatus;
use App\Enums\Visibility;
use App\Jobs\AnalysePost;
use App\Models\Friendship;
use App\Models\Post;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Synthetic demo content for local development and screenshots.
 * Every account and post here is fictional.
 *
 *   php artisan db:seed --class=DemoSeeder
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $people = collect([
            ['Ayesha Khan', 'ayesha', 'PK', 'Islamabad'],
            ['Bilal Ahmed', 'bilal', 'PK', 'Lahore'],
            ['Chidi Okafor', 'chidi', 'NG', 'Lagos'],
            ['Dana Smith', 'dana', 'GB', 'Leeds'],
            ['Emre Yilmaz', 'emre', 'TR', 'Izmir'],
        ])->map(function ($p) {
            // No factories: Faker is a dev dependency and absent in production images.
            $user = new User([
                'name' => $p[0], 'username' => $p[1], 'email' => "{$p[1]}@example.test",
                'password' => 'password', 'country' => $p[2], 'city' => $p[3], 'bio' => 'Demo account.',
            ]);
            $user->forceFill(['email_verified_at' => now()])->save();

            return $user;
        });

        // Everyone is friends with Ayesha; Bilal and Chidi are friends too.
        foreach ($people->slice(1) as $p) {
            Friendship::create(['requester_id' => $people[0]->id, 'addressee_id' => $p->id, 'status' => FriendshipStatus::Accepted, 'accepted_at' => now()]);
        }
        Friendship::create(['requester_id' => $people[1]->id, 'addressee_id' => $people[2]->id, 'status' => FriendshipStatus::Accepted, 'accepted_at' => now()]);

        $samples = [
            'The new metro line in Lahore is fantastic, my commute is half as long now!',
            'Cricket tonight was a disappointment, the batting collapse was painful to watch.',
            'Load shedding again for six hours. This is getting ridiculous.',
            'Yeh naya budget bohat acha lag raha hai, umeed hai mehngai kam hogi.',
            'Aaj ka match bilkul bakwas tha, team ne bohat bura khela.',
            'Heavy rain expected in Islamabad this weekend, stay safe everyone.',
            'Just finished a great book on machine learning, highly recommend it.',
            'Petrol prices went up again, everything is so expensive now.',
            'بارش کے بعد موسم بہت خوشگوار ہو گیا ہے',
            'The tech conference in Karachi was really inspiring.',
        ];

        foreach ($samples as $i => $body) {
            $post = new Post([
                'body' => $body,
                'visibility' => $i % 4 === 3 ? Visibility::Friends : Visibility::Public,
                'country' => $people[$i % $people->count()]->country,
            ]);
            $post->user()->associate($people[$i % $people->count()]);
            $post->created_at = now()->subHours(($i + 1) * 3);
            $post->save();
            AnalysePost::dispatch($post);
        }

        $this->seedHistory($people);
        $this->seedHealth($people);

        $this->command?->info('Demo users created with password "password": ' . $people->pluck('username')->implode(', '));
    }

    /**
     * Fictional health posts in four languages: background chatter for 8 weeks
     * and a Lassa fever surge in Ondo in the last day, so the dashboard and the
     * outbreak detector have something to show.
     */
    private function seedHealth($people): void
    {
        mt_srand(11);
        $ng = $people->whereIn('country', ['NG'])->values();
        $pk = $people->whereIn('country', ['PK'])->values();
        $background = [
            [$ng, 'Malaria don catch my brother again, body dey hot since yesterday.'],
            [$ng, 'Typhoid and malaria test cost too much for clinic this week.'],
            [$pk, 'Karachi mein dengue ke kuch cases phir se aa rahe hain, machar se bachein.'],
            [$pk, 'Bacche ko bukhar hai, doctor ne kaha typhoid ka test karwao.'],
            [$pk, 'لاہور میں ڈینگی سے بچاؤ کی مہم شروع ہو گئی ہے'],
            [$ng, 'Lassa fever awareness talk for Owo market today, keep food covered from rats.'],
        ];
        $surge = [
            'Lassa fever don reach Owo, two people for hospital, Ondo State. Una careful o.',
            'NCDC confirm new Lassa fever cases for Ondo, abeg no dey dry food for open place.',
            'Another suspected Lassa fever case for Akure. Fever plus bleeding, e dey serious.',
            'Lassa fever cases rising in Ondo, hospital asking people to report fever early.',
        ];

        $make = function ($author, string $text, $when) {
            $post = new Post(['body' => $text, 'visibility' => Visibility::Public, 'country' => $author->country]);
            $post->user()->associate($author);
            $post->created_at = $when;
            $post->save();
            AnalysePost::dispatch($post);
        };

        for ($day = 55; $day >= 1; $day--) {
            if (mt_rand(0, 2) === 0) {
                [$pool, $text] = $background[array_rand($background)];
                if ($pool->isNotEmpty()) {
                    $make($pool[mt_rand(0, $pool->count() - 1)], $text, now()->subDays($day)->subMinutes(mt_rand(5, 1300)));
                }
            }
        }
        if ($ng->isNotEmpty()) {
            for ($k = 0; $k < 8; $k++) {
                $make($ng[$k % $ng->count()], $surge[$k % count($surge)], now()->subMinutes(30 + 90 * $k));
            }
        }
    }

    /**
     * Two weeks of fictional public posts so that trends and topic charts have
     * something to show; "petrol price" spikes in the last day.
     */
    private function seedHistory($people): void
    {
        mt_srand(7);
        $templates = [
            'positive' => [
                'Great {t} news today, really happy about it.', 'Loving the {t} updates this week!',
                '{t} ke baare mein khabar bohat achi hai', 'Finally some good {t} news for Pakistan.',
            ],
            'negative' => [
                'The {t} situation is getting worse every day.', 'Really disappointed with the {t} today.',
                '{t} ki wajah se sab pareshan hain, bohat bura haal hai', 'Another terrible {t} update, so frustrating.',
            ],
            'neutral' => ['Reading about the {t} this morning.', 'Any updates on the {t}?', '{t} ke baare mein kya khayal hai?'],
        ];
        $topics = ['cricket match', 'budget', 'monsoon rain', 'metro bus', 'load shedding', 'petrol price', 'exam results'];

        for ($day = 13; $day >= 0; $day--) {
            foreach ($topics as $topic) {
                $n = $topic === 'petrol price' && $day === 0 ? 12 : mt_rand(0, 2);
                for ($k = 0; $k < $n; $k++) {
                    $mood = $topic === 'petrol price' ? (mt_rand(0, 9) < 8 ? 'negative' : 'neutral') : array_rand($templates);
                    $text = str_replace('{t}', $topic, $templates[$mood][array_rand($templates[$mood])]);
                    $author = $people[mt_rand(0, $people->count() - 1)];
                    $post = new Post(['body' => ucfirst($text), 'visibility' => Visibility::Public, 'country' => $author->country]);
                    $post->user()->associate($author);
                    $post->created_at = now()->subDays($day)->subMinutes(mt_rand(5, 1300));
                    $post->save();
                    AnalysePost::dispatch($post);
                }
            }
        }
    }
}
