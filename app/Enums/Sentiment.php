<?php

namespace App\Enums;

enum Sentiment: string
{
    case Positive = 'positive';
    case Neutral = 'neutral';
    case Negative = 'negative';

    public function colorClass(): string
    {
        return match ($this) {
            self::Positive => 'bg-emerald-100 text-emerald-800',
            self::Neutral => 'bg-slate-100 text-slate-700',
            self::Negative => 'bg-rose-100 text-rose-800',
        };
    }
}
