<?php

return [
    // Diseases that can raise outbreak alerts (ids from analytics/soleglance_analytics/health/lexicon.py).
    'priority_diseases' => ['lassa_fever', 'cholera', 'meningitis', 'dengue', 'measles', 'diphtheria', 'mpox', 'yellow_fever', 'polio'],

    'labels' => [
        'lassa_fever' => 'Lassa fever', 'cholera' => 'Cholera', 'meningitis' => 'Meningitis', 'dengue' => 'Dengue',
        'malaria' => 'Malaria', 'typhoid' => 'Typhoid', 'measles' => 'Measles', 'diphtheria' => 'Diphtheria',
        'mpox' => 'Mpox', 'yellow_fever' => 'Yellow fever', 'polio' => 'Polio', 'covid19' => 'COVID-19',
        'tuberculosis' => 'Tuberculosis', 'influenza' => 'Influenza', 'hepatitis' => 'Hepatitis',
        'cancer' => 'Cancer', 'diabetes' => 'Diabetes', 'cardiovascular' => 'Heart attack / stroke',
        'respiratory' => 'Asthma / pneumonia', 'mental_health' => 'Mental health', 'neurological' => 'Neurological',
        'allergy' => 'Allergy',
    ],

    'countries' => ['NG' => 'Nigeria', 'PK' => 'Pakistan'],

    // Early-warning detector (CDC EARS). See paper for validation.
    'signals' => [
        'method' => env('HEALTH_SIGNAL_METHOD', 'c2'),
        'baseline_days' => 7,
        'min_count' => (int) env('HEALTH_SIGNAL_MIN_COUNT', 3),
        'lookback_days' => 56,
        'cooldown_days' => 7,
    ],

    // Misinformation flags at or above this score go to human review (never auto-removed).
    'misinformation_review_threshold' => 0.7,

    // Optional: POST each new alert as JSON to a health partner's endpoint,
    // signed with HMAC-SHA256 in the X-SoleGlance-Signature header.
    'alert_webhook' => env('HEALTH_ALERT_WEBHOOK'),
    'webhook_secret' => env('HEALTH_WEBHOOK_SECRET'),

    // Retrain automatically once this many new corrections have been sent.
    'retrain_after_feedback' => (int) env('RETRAIN_AFTER_FEEDBACK', 50),
];
