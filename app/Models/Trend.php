<?php

namespace App\Models;

use App\Enums\Category;
use Illuminate\Database\Eloquent\Model;

class Trend extends Model
{
    protected $fillable = [
        'term', 'category', 'window_start', 'window_end', 'mentions', 'baseline', 'score',
        'positive', 'neutral', 'negative', 'mean_sentiment', 'sample',
    ];

    protected function casts(): array
    {
        return [
            'category' => Category::class,
            'window_start' => 'datetime',
            'window_end' => 'datetime',
            'sample' => 'array',
        ];
    }

    public function total(): int
    {
        return $this->positive + $this->neutral + $this->negative;
    }

    public function share(string $label): float
    {
        return $this->total() ? $this->{$label} / $this->total() : 0.0;
    }
}
