<?php

return [
    'demo_mode' => env('SUBSCRIPTIONS_DEMO', true),
    'plans' => [
        'free' => [
            'stripe_price_id' => env('STRIPE_PRICE_FREE'),
            'purchasable' => false,
        ],
        'plus' => [
            'stripe_price_id' => env('STRIPE_PRICE_PLUS'),
            'purchasable' => true,
        ],
        'pro' => [
            'stripe_price_id' => env('STRIPE_PRICE_PRO'),
            'purchasable' => false,
        ],
    ],
    'limits' => [
        'reading_daily' => 3,
        'listening_daily' => 1,
        'ai_daily' => (int) env('AI_FREE_DAILY_LIMIT', 20),
    ],
    'yearly_offer' => [
        'months' => (int) env('SUBSCRIPTION_YEARLY_MONTHS', 12),
        'discount_percent' => (int) env('SUBSCRIPTION_YEARLY_DISCOUNT', 20),
    ],
    'manual_payment' => [
        'card_number' => env('MANUAL_PAYMENT_CARD'),
        'card_holder' => env('MANUAL_PAYMENT_HOLDER', ''),
        'bank_name' => env('MANUAL_PAYMENT_BANK', ''),
        'instructions' => env('MANUAL_PAYMENT_INSTRUCTIONS', ''),
        'default_months' => (int) env('MANUAL_SUBSCRIPTION_MONTHS', 1),
    ],
];
