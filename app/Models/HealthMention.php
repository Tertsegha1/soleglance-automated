<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class HealthMention extends Model
{
    protected $fillable = [
        'mentionable_type', 'mentionable_id', 'is_public', 'health_score', 'diseases', 'symptoms', 'regions', 'disease', 'country', 'region',
        'misinformation_score', 'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'is_public' => 'boolean',
            'diseases' => 'array',
            'symptoms' => 'array',
            'regions' => 'array',
            'occurred_at' => 'datetime',
        ];
    }

    public function mentionable(): MorphTo
    {
        return $this->morphTo();
    }

    public static function label(?string $disease): string
    {
        return config("health.labels.{$disease}", $disease ? ucfirst(str_replace('_', ' ', $disease)) : 'Unspecified');
    }
}
