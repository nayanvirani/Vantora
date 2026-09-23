<?php

return [
    'api_key' => env('SHOPIFY_API_KEY'),
    'api_secret' => env('SHOPIFY_API_SECRET'),

    // Every Admin GraphQL field/mutation this app calls must be checked
    // against this exact version at build time -- keep it in sync with
    // shopify.app.toml and any extensions' own shopify.extension.toml.
    'api_version' => env('SHOPIFY_API_VERSION', '2026-07'),

    'webhook_path' => env('SHOPIFY_WEBHOOK_PATH', '/webhooks/shopify'),

    // Phase 1 scope only (spec section 9): read_products + read_themes for
    // audit/theme-compatibility groundwork (Phase 2), nothing else yet.
    // Discount/Function scopes belong to Phase 3, write_products to
    // Phase 4's AI optimizer -- adding them now would be requesting access
    // the app doesn't use yet, which Shopify's own review guidance flags.
    'scopes' => env('SHOPIFY_APP_SCOPES', 'read_products,read_themes'),

    'app_handle' => env('SHOPIFY_APP_HANDLE', 'vantora-1'),

    // Billing is Shopify Managed Pricing (spec section 3: "Billing is
    // through the Shopify Billing API") -- plan names/prices/trial length
    // are configured in the Partner Dashboard, not called via
    // appSubscriptionCreate. 'name' must match the Partner Dashboard plan
    // name exactly (case-insensitively), since that string is the only
    // thing the app_subscriptions/update webhook gives to identify which
    // internal plan key a subscription maps to.
    'plans' => [
        'starter' => [
            'name' => 'Starter',
            'price' => 19.99,
        ],
        'pro' => [
            'name' => 'Pro',
            'price' => 49.99,
        ],
    ],

    // Per-feature-type plan limits (spec section 3's pricing matrix,
    // section 10's gating rules). null = unlimited on that plan. A type
    // with no entry here is ungated on every plan. Deliberately a map per
    // (type, plan) rather than a flat "gated vs pro-only" split: Sticky
    // ATC and Shipping Bar are capped at 1 on BOTH Starter and Pro --
    // only Trust Badges and FAQ go unlimited on Pro.
    'feature_limits' => [
        'sticky_atc' => ['starter' => 1, 'pro' => 1],
        'shipping_bar' => ['starter' => 1, 'pro' => 1],
        'trust_badges' => ['starter' => 1, 'pro' => null],
        'faq' => ['starter' => 1, 'pro' => null],
    ],

    // Modules gated by plan alone, not by an active-config count -- none
    // exist yet in Phase 1 (pre-purchase funnel, post-purchase funnel and
    // checkout extensions are Phase 3/6/7).
    'pro_only_modules' => [],
];
