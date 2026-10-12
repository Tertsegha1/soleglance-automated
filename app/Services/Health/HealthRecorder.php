<?php

namespace App\Services\Health;

use App\Enums\Role;
use App\Enums\Visibility;
use App\Models\Article;
use App\Models\HealthMention;
use App\Models\MisinformationReview;
use App\Models\Post;
use App\Models\User;
use App\Notifications\PostFlaggedForReview;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Notification;

/**
 * Stores the analytics service's health result for a post or article and
 * queues likely misinformation for human review.
 */
class HealthRecorder
{
    public function record(Model $subject, array $result): ?HealthMention
    {
        if (!($result['is_health'] ?? false)) {
            HealthMention::where('mentionable_type', $subject->getMorphClass())->where('mentionable_id', $subject->getKey())->delete();

            return null;
        }

        [$country, $region] = $this->place($subject, $result);
        $isPost = $subject instanceof Post;

        $mention = HealthMention::updateOrCreate(
            ['mentionable_type' => $subject->getMorphClass(), 'mentionable_id' => $subject->getKey()],
            [
                'is_public' => !$isPost || $subject->visibility === Visibility::Public,
                'health_score' => $result['health_score'],
                'diseases' => $result['diseases'] ?? [],
                'symptoms' => $result['symptoms'] ?? [],
                'regions' => $result['regions'] ?? [],
                'disease' => $result['diseases'][0] ?? null,
                'country' => $country,
                'region' => $region,
                'misinformation_score' => $result['misinformation_score'] ?? null,
                'occurred_at' => $isPost ? $subject->created_at : ($subject->published_at ?? $subject->created_at),
            ],
        );

        // Only public posts are reviewed for misinformation; private conversations are not policed.
        $score = $result['misinformation_score'] ?? null;
        if ($isPost && $subject->visibility === Visibility::Public && $score !== null
            && $score >= config('health.misinformation_review_threshold')) {
            $review = MisinformationReview::firstOrNew(['post_id' => $subject->getKey()]);
            if (!$review->exists) {
                $review->fill(['score' => $score, 'status' => 'pending'])->save();
                Notification::send(User::where('role', Role::Admin)->get(), new PostFlaggedForReview($subject->getKey(), $score));
            }
        }

        return $mention;
    }

    /**
     * Country/region: a place named in the text wins over the author's or feed's country.
     *
     * @return array{0: ?string, 1: ?string}
     */
    private function place(Model $subject, array $result): array
    {
        $region = $result['regions'][0] ?? null;
        $country = $region ? substr($region, 0, 2) : ($result['countries'][0] ?? null);
        $country ??= $subject->country;

        return [$country ? strtoupper($country) : null, $region];
    }
}
