# Vantora build roadmap

Tracks progress against spec section 11 (Development phases). Update this file as
phases move from "not started" to "in progress" to "done" — it's the map back into
the spec for whoever (human or agent) picks this up next.

## Phase 1 — Foundation and theme tools (weeks 1-3): **scaffolded**

Done:
- Laravel 13 backend, full schema for every table in spec section 8 (22 migrations + models)
- Shopify OAuth install/callback (`ShopifyAuthController`), HMAC verification, token exchange
- Session-token auth middleware for the embedded app API (`VerifyShopifySessionToken`)
- `PlanGateService` — single choke point for Starter/Pro limits (spec section 10)
- `BillingService` — Shopify Billing API subscription creation + webhook activation
- Compliance + operational webhooks (app/uninstalled, shop/update, themes/*, products/*,
  orders/create, customers/data_request, customers/redact, shop/redact) with idempotent
  `webhook_events` dedup
- Theme app extension: Sticky ATC + Free Shipping Bar (app embeds), Trust Badges + FAQ
  (app blocks) — `extensions/theme-extension/blocks/`
- Embedded admin SPA shell (Polaris + App Bridge, session-token auth) with a Plans
  (billing) screen and a Home dashboard
- Dockerfile + Railway deploy wiring (Postgres, Redis, env vars, healthcheck)

Not done / needs a real pass:
- Sticky ATC / Shipping Bar / Trust Badges / FAQ admin **config screens** (the SPA has
  no "Tools" section yet — `feature_configs` CRUD exists on the API side via
  `FeatureConfigController`, just no UI beyond the dashboard's Fix buttons, which create
  configs with empty settings)
- Syncing `feature_configs.settings` into the theme blocks' actual rendered values
  (right now the blocks only read their own theme-editor schema settings, not what the
  merchant configured in the admin — needs a metafield or Settings API bridge)
- Theme setup helper / guided enable steps for app embeds
- Onboarding flow polish (spec wants first audit + top 3 fixes in under 2 minutes,
  visible progress screen)

## Phase 2 — Audit and fix (weeks 4-6): **scaffolded**

Done: `AuditEngine` (F-01/F-02/F-03), `RunAuditJob`, `AuditController`,
`DashboardController`, One-Click Fix flow end-to-end (`FeatureConfigController::activate`
enforces plan gating and updates `usage_counters`).

Simplified vs. spec — needs a real pass before this can be called "done":
- The v1 rule set only checks *whether a feature_config is active*, not actual theme
  content (spec wants sticky-CTA/trust-element/image/description presence detected in
  the theme itself, cart AOV vs. shipping threshold, mobile CTA visibility). Rewriting
  this to actually parse theme assets and shop analytics is the bulk of remaining
  Phase 2 work.
- Score history UI (F-07) — `audits`/`audit_scores` already store history, no chart yet.
- CRO Recipes (F-06) — `recipes`/`recipe_runs` tables exist, no engine or UI.

## Phase 3 — Offers and bundles (weeks 7-9): **not started**

Discount Functions (Quantity, BOGO, Free Gift), Cart Transform bundles (expand/merge,
never the Plus-only update operation), Frequently Bought Together, Cart Upsell, Goal
Tracker. `offers`/`bundles`/`bundle_items` tables exist; no Function code, no Cart
Transform extension, no admin UI for any of it.

## Phase 4 — AI, recipes, analytics (weeks 10-11): **not started**

AI Product Optimizer (`ai_jobs`/`ai_usage` tables exist, no LLM integration —
`AI_PROVIDER_API_KEY` env var is reserved but unused), 3 recipes, web pixel extension,
basic/advanced analytics (`events`/`analytics_daily` tables exist, no aggregation job
or UI), monitoring email delivery (`RunWeeklyMonitoring` command runs and records
`monitoring_runs`, but nothing sends the email yet — `notifications` table is there).

## Phase 5 — Launch MVP-1 (week 12): **not started**

Plan gating test suite, weekly email, App Store listing assets, App Store review.

## Phase 6 — Post-purchase and Thank you, MVP-2 (weeks 13-16): **not started**

Apply for Shopify's post-purchase extension access **early** (spec section 11 and 13 —
it can take time and F-33 depends on approval). Nothing built yet; `survey_responses`,
`extension_status` tables exist for when this starts.

## Phase 7 — Checkout blocks, MVP-2 (weeks 17-19): **not started**

Checkout UI extensions for Plus stores only (F-32, F-39–F-45), eligibility screen.

## Known simplifications / tech debt to revisit

- Polaris React (`@shopify/polaris` v13) is deprecated upstream in favor of Polaris
  web components — kept it because it matches the spec's "Polaris and App Bridge"
  wording and is still functional, but a future pass may want to migrate.
- `railway.json` (Config as Code) is deprecated by Railway in favor of
  `.railway/railway.ts` (Infrastructure as Code); it works until **2026-12-01**. Run
  `railway config migrate` before then.
- Dockerfile uses `php artisan serve`-free nginx+php-fpm+supervisord instead of a
  managed base image — works, but if `docker/nginx.conf.template` or
  `docker/supervisord.conf` ever need real production hardening (rate limits, gzip,
  worker tuning), that's manual.
- Cart/theme content isn't actually scanned (see Phase 2 note above) — the audit is
  presence-of-active-config only, a defensible v1 but not what section 4.1/F-01
  describes.
