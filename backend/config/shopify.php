<?php

return [
    'api_key' => env('SHOPIFY_API_KEY'),
    'api_secret' => env('SHOPIFY_API_SECRET'),
    'api_version' => env('SHOPIFY_API_VERSION', '2026-01'),
    'scopes' => env('SHOPIFY_APP_SCOPES', 'read_products'),
    'webhook_path' => env('SHOPIFY_WEBHOOK_PATH', '/webhooks/shopify'),

    'plans' => [
        'starter' => [
            'name' => 'Starter',
            'price' => 19.99,
            'per_feature_limit' => 1,
            'trial_days' => 7,
        ],
        'pro' => [
            'name' => 'Pro',
            'price' => 49.99,
            'per_feature_limit' => null, // unlimited
            'trial_days' => 14,
        ],
    ],

    // Feature types gated to 1 active config on Starter, unlimited on Pro.
    'gated_feature_types' => [
        'sticky_atc',
        'shipping_bar',
        'trust_badges',
        'faq',
        'free_gift',
        'bogo',
        'quantity_discount',
        'bundle',
    ],

    // Modules only available on the Pro plan regardless of count.
    'pro_only_modules' => [
        'cart_upsell',
        'fbt',
        'goal_tracker',
        'post_purchase_upsell',
        'thank_you_offers',
        'post_purchase_survey',
        'order_status_blocks',
        'referral_reorder',
        'checkout_extension',
    ],

    'ai_fair_use_cap_per_month' => 100,
];
