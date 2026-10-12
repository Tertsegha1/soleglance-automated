<?php

// Deployment settings read by first-start automation (App\Services\Ops\Bootstrapper).
// Read via config(), never env(), so they survive `php artisan optimize`.
return [
    'admin_email' => env('ADMIN_EMAIL', 'admin@soleglance.test'),
    'admin_password' => env('ADMIN_PASSWORD'),
    'seed_default_news_sources' => (bool) env('SEED_DEFAULT_NEWS_SOURCES', true),
];
