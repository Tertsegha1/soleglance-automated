<?php

namespace App\Models;

use App\Enums\Category;
use App\Enums\Sentiment;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Article extends Model
{
    use HasFactory;

    protected $fillable = ['guid', 'title', 'summary', 'url', 'published_at', 'country', 'language', 'category'];

    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
            'category' => Category::class,
            'sentiment' => Sentiment::class,
            'sentiment_scores' => 'array',
            'analysed_at' => 'datetime',
        ];
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(NewsSource::class, 'news_source_id');
    }

    /** Text sent to the analytics service. */
    public function analysisText(): string
    {
        return trim($this->title . '. ' . strip_tags((string) $this->summary));
    }
}
