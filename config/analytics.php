<?php

return [
    // Base URL of the Python analytics service (analytics/). Empty disables analysis.
    'url' => env('ANALYTICS_URL'),
    'token' => env('ANALYTICS_TOKEN'),
    'timeout' => (int) env('ANALYTICS_TIMEOUT', 30),

    // Trend detection parameters (see analytics/soleglance_analytics/trends.py).
    'trends' => [
        'window_hours' => (int) env('TRENDS_WINDOW_HOURS', 24),
        'history_windows' => (int) env('TRENDS_HISTORY_WINDOWS', 7),
        'min_mentions' => (int) env('TRENDS_MIN_MENTIONS', 3),
        'limit' => 30,
        'max_documents' => 20000,
    ],

    'news' => [
        'fetch_timeout' => 15,
        'max_items_per_feed' => 100,
    ],
];
