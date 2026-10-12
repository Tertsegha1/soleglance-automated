<?php

namespace App\Models;

use App\Enums\Category;
use App\Enums\FriendshipStatus;
use App\Enums\Sentiment;
use App\Enums\Visibility;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Post extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['body', 'visibility', 'country'];

    protected function casts(): array
    {
        return [
            'visibility' => Visibility::class,
            'sentiment' => Sentiment::class,
            'sentiment_scores' => 'array',
            'category' => Category::class,
            'analysed_at' => 'datetime',
            'hidden_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function media(): HasMany
    {
        return $this->hasMany(PostMedia::class);
    }

    public function audience(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'post_audience');
    }

    public function reactions(): HasMany
    {
        return $this->hasMany(Reaction::class);
    }

    public function misinformationReview(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(MisinformationReview::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }

    /**
     * Posts the given viewer is allowed to see. This is the single source of
     * truth for post visibility; feeds, profiles, search, reactions and
     * comments all go through it (see PostPolicy::view for the per-model check).
     */
    public function scopeVisibleTo(Builder $query, ?User $viewer): Builder
    {
        $query->whereNull('posts.hidden_at')
            ->whereHas('user', fn ($q) => $q->whereNull('suspended_at'));

        if (!$viewer) {
            return $query->where('visibility', Visibility::Public);
        }

        // Admins get no extra reach here; moderation screens query posts directly.
        return $query->where(function (Builder $q) use ($viewer) {
            $q->where('posts.user_id', $viewer->id)
                ->orWhere('visibility', Visibility::Public)
                ->orWhere(fn ($q) => $q
                    ->where('visibility', Visibility::Friends)
                    ->whereExists(fn ($sub) => $sub->selectRaw('1')
                        ->from('friendships')
                        ->where('friendships.status', FriendshipStatus::Accepted->value)
                        ->where(fn ($pair) => $pair
                            ->where(fn ($p) => $p->whereColumn('friendships.requester_id', 'posts.user_id')->where('friendships.addressee_id', $viewer->id))
                            ->orWhere(fn ($p) => $p->whereColumn('friendships.addressee_id', 'posts.user_id')->where('friendships.requester_id', $viewer->id)))))
                ->orWhere(fn ($q) => $q
                    ->where('visibility', Visibility::Custom)
                    ->whereExists(fn ($sub) => $sub->selectRaw('1')
                        ->from('post_audience')
                        ->whereColumn('post_audience.post_id', 'posts.id')
                        ->where('post_audience.user_id', $viewer->id)));
        });
    }

    public function scopePubliclyAnalysable(Builder $query): Builder
    {
        // Analytics (trends, reports) only ever aggregate public posts.
        return $query->where('visibility', Visibility::Public)->whereNull('hidden_at');
    }
}
