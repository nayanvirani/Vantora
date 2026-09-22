<?php

return [
    'api_key' => env('SHOPIFY_API_KEY'),
    'api_secret' => env('SHOPIFY_API_SECRET'),
    // Every Admin GraphQL field/mutation this app calls (BillingService,
    // DiscountSyncService, ShopifyAuthController's webhookSubscriptionCreate)
    // was checked against the 2026-07 schema this session, not 2026-01 --
    // keep this in sync with the extensions' api_version in shopify.app.toml
    // and each extensions/*/shopify.extension.toml, or field availability
    // can silently drift between what the backend calls and what was verified.
    'api_version' => env('SHOPIFY_API_VERSION', '2026-07'),
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

    /*
    |--------------------------------------------------------------------------
    | Billing
    |--------------------------------------------------------------------------
    |
    | Whether appSubscriptionCreate charges are marked `test: true`. This is
    | about the SHOPIFY STORE being charged, not this app's own environment:
    | a development store silently refuses non-test charges regardless of
    | APP_ENV here, so this must default to true and only flip to false once
    | the app is actually installed on real merchant stores.
    |
    */
    'billing_test_mode' => (bool) env('SHOPIFY_BILLING_TEST_MODE', true),
];
