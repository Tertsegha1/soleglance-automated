<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HealthAlert extends Model
{
    protected $fillable = [
        'disease', 'country', 'region', 'method', 'statistic', 'count', 'baseline_mean',
        'window_start', 'window_end', 'status', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'window_start' => 'date',
            'window_end' => 'date',
            'notified_at' => 'datetime',
            'webhook_sent_at' => 'datetime',
        ];
    }

    public function handler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by');
    }

    public function area(): string
    {
        $country = config("health.countries.{$this->country}", $this->country);

        return $this->region ? "{$this->region} ({$country})" : $country;
    }

    public function title(): string
    {
        return HealthMention::label($this->disease) . ' signal in ' . $this->area();
    }

    public function payload(): array
    {
        return [
            'id' => $this->id,
            'disease' => $this->disease,
            'disease_label' => HealthMention::label($this->disease),
            'country' => $this->country,
            'region' => $this->region,
            'method' => $this->method,
            'statistic' => $this->statistic,
            'count' => $this->count,
            'window_start' => $this->window_start->toDateString(),
            'window_end' => $this->window_end->toDateString(),
            'url' => route('health.index', ['disease' => $this->disease, 'country' => $this->country]),
            'note' => 'Automated signal from public posts and news on SoleGlance. Requires verification; not a confirmed outbreak.',
        ];
    }
}
