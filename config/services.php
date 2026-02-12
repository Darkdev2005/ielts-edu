<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],
    'stripe' => [
        'secret' => env('STRIPE_SECRET'),
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
    ],
    'openai' => [
        'api_key' => env('OPENAI_API_KEY'),
        'model' => env('OPENAI_MODEL', 'gpt-4o-mini'),
        'base_url' => env('OPENAI_BASE_URL', 'https://api.openai.com/v1'),
    ],
    'gemini' => [
        'api_key' => env('GEMINI_API_KEY'),
        'model' => env('GEMINI_MODEL', 'gemini-2.0-flash'),
        'base_url' => env('GEMINI_BASE_URL', 'https://generativelanguage.googleapis.com/v1beta'),
    ],
    'cohere' => [
        'api_key' => env('COHERE_API_KEY'),
        'model' => env('COHERE_MODEL', 'command-r'),
        'base_url' => env('COHERE_BASE_URL', 'https://api.cohere.ai/v1'),
    ],
    'ai' => [
        'provider' => env('AI_PROVIDER', 'gemini'),
        'verify_ssl' => env('AI_VERIFY_SSL', true),
        'retry_interval_minutes' => env('AI_RETRY_INTERVAL_MINUTES', 5),
        'retry_limit' => env('AI_RETRY_LIMIT', 20),
        'retry_min_age_minutes' => env('AI_RETRY_MIN_AGE_MINUTES', 10),
        'retry_max_attempts' => env('AI_RETRY_MAX_ATTEMPTS', 3),
    ],

];
