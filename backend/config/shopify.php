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

    // The app's handle in Shopify's own URLs, e.g.
    // admin.shopify.com/store/{shop}/apps/{app_handle} -- needed to build
    // the link to Shopify's own hosted Managed Pricing plan-selection page.
    // Visible in the Partner Dashboard / any admin.shopify.com/.../apps/...
    // URL for this app; Shopify may suffix it (e.g. "vantora-1") if the
    // bare name collided with an existing app.
    'app_handle' => env('SHOPIFY_APP_HANDLE', 'vantora-1'),

    // Billing is Shopify Managed Pricing: plan names, prices and trial
    // lengths are configured in the Partner Dashboard, not here -- this app
    // never calls appSubscriptionCreate. 'name' below must match the plan
    // name exactly as typed into the Partner Dashboard (case-insensitively),
    // since that's the only string the app_subscriptions/update webhook
    // gives it to identify which local plan a subscription is for.
    'plans' => [
        'starter' => [
            'name' => 'Starter',
            'price' => 19.99,
            'per_feature_limit' => 1,
        ],
        'pro' => [
            'name' => 'Pro',
            'price' => 49.99,
            'per_feature_limit' => null, // unlimited
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
