<?php

namespace App\Enums;

/**
 * Topic categories used by the trending dashboards. Kept in sync with
 * analytics/soleglance_analytics/topics.py.
 */
enum Category: string
{
    case Politics = 'politics';
    case Business = 'business';
    case Sports = 'sports';
    case Entertainment = 'entertainment';
    case Technology = 'technology';
    case Health = 'health';
    case Weather = 'weather';
    case Travel = 'travel';
    case Other = 'other';

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
