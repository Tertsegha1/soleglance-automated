<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MisinformationReview extends Model
{
    protected $fillable = ['post_id', 'score', 'status', 'correction', 'source_url'];

    protected function casts(): array
    {
        return ['reviewed_at' => 'datetime'];
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }
}
