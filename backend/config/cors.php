<?php

return [

    /*
     * Storefront extensions (vantora-analytics.js, the web pixel,
     * post-purchase, thank-you) call these directly from the browser on
     * the shop's own domain, never ours -- confirmed live: Laravel has no
     * config/cors.php published, and HandleCors' built-in default only
     * covers `api/*`, so every one of these was silently blocked by the
     * browser's CORS preflight (no Access-Control-Allow-Origin header)
     * despite the backend itself responding fine to a same-origin/curl
     * request. None of these carry cookies or session-based auth (public
     * + rate-limited, session-token bearer, or HMAC-signed instead), so a
     * wildcard origin doesn't expose anything a credentialed CORS policy
     * would need to guard.
     */
    'paths' => ['pixel/*', 'post-purchase/*', 'thank-you/*', 'apps/*'],

    'allowed_methods' => ['*'],

    'allowed_origins' => ['*'],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => false,

];
