# Vantora build roadmap

Tracks progress against spec section 11 (Development phases). Update this file as
phases move from "not started" to "in progress" to "done" — it's the map back into
the spec for whoever (human or agent) picks this up next.

All extensions are deployed and released to the Partner Dashboard as of app version
**vantora-6** (`shopify app deploy`). The backend is deployed and live on Railway at
`https://vantora-production.up.railway.app`, auto-deploying on every push to `main`.

## Phase 1 — Foundation and theme tools (weeks 1-3): **scaffolded**

Done:
- Laravel 13 backend, full schema for every table in spec section 8 (22 migrations + models)
- Shopify OAuth install/callback (`ShopifyAuthController`), HMAC verification, token exchange
- Session-token auth middleware for the embedded app API (`VerifyShopifySessionToken`)
- `PlanGateService` — single choke point for Starter/Pro limits (spec section 10),
  covered by `tests/Feature/PlanGateServiceTest.php`
- `BillingService` — Shopify Billing API subscription creation + webhook activation
- Compliance + operational webhooks (app/uninstalled, shop/update, themes/*, products/*,
  orders/create, customers/data_request, customers/redact, shop/redact) with idempotent
  `webhook_events` dedup
- Theme app extension: Sticky ATC, Free Shipping Bar (app embeds), Trust Badges, FAQ
  (app blocks) — `extensions/theme-extension/blocks/`
- Embedded admin SPA (Polaris + App Bridge, session-token auth), built by Laravel's own
  Vite pipeline (`backend/resources/js`) — Plans (billing) screen and a Home dashboard
- Railway deploy wiring: Railpack builder (no Dockerfile), Postgres + Redis services,
  three services sharing one repo (web / queue worker / monitoring cron)

Not done / needs a real pass:
- Admin **config screens** for the storefront tools (the SPA has no "Tools" section yet
  — `feature_configs` CRUD exists on the API side, no UI beyond the dashboard's Fix
  buttons, which create configs with empty settings)
- Syncing `feature_configs.settings` into the theme blocks' actual rendered values for
  the Phase 1 tools (Sticky ATC/Shipping Bar/Trust Badges/FAQ only read their own
  theme-editor schema settings today, not what the merchant configured in the admin —
  the Phase 3 tools this session added, cart_upsell/fbt, DO read live config via the
  app proxy, so this is the pattern to extend backward)
- Theme setup helper / guided enable steps for app embeds
- Onboarding flow polish (spec wants first audit + top 3 fixes in under 2 minutes,
  visible progress screen)

## Phase 2 — Audit and fix (weeks 4-6): **scaffolded**

Done: `AuditEngine` (F-01/F-02/F-03), `RunAuditJob`, `AuditController`,
`DashboardController`, One-Click Fix flow end-to-end (`FeatureActivationService`
enforces plan gating, syncs Shopify Functions where needed, and updates
`usage_counters` — shared by both manual activation and Recipe approval).

Simplified vs. spec — needs a real pass before this can be called "done":
- The v1 rule set only checks *whether a feature_config is active*, not actual theme
  content (spec wants sticky-CTA/trust-element/image/description presence detected in
  the theme itself, cart AOV vs. shipping threshold, mobile CTA visibility). Rewriting
  this to actually parse theme assets and shop analytics is the bulk of remaining
  Phase 2 work.
- Score history has an API (`GET /api/analytics/score-history`) but no chart UI yet.

## Phase 3 — Offers and bundles (weeks 7-9): **built, partly verified**

Done and verified by running the actual compiled WASM (`npx vitest run` in each
`extensions/<name>`, not just code review):
- `quantity-discount`, `bogo-discount`, `free-gift-discount`, `bundle-discount` —
  Discount Functions (`purchase.product-discount.run`)
- `bundle-transform` — Cart Transform Function (`cart.transform.run`, `lineExpand`
  operation only; never the Plus-only `update` operation, per spec)

Done, NOT verified against a live store (no dev store installed in this environment):
- `DiscountSyncService` — turns an activated feature_config into a real
  `discountAutomaticAppCreate`/`Update` call + function-configuration metafield,
  resolving function IDs from the Admin API by title rather than hardcoding them.
  Mutation shapes are written carefully but unexercised; sanity-check against
  shopify.dev / a real store before trusting this in production.
- Bundle product metafield sync (`$app:bundle-components`) that the Cart Transform
  function reads — same caveat.

Done: Cart Upsell, Frequently Bought Together, Cart Goal Tracker theme blocks, backed
by a signed App Proxy (`/apps/vantora/*`, `VerifyShopifyAppProxySignature`) for live
config without a theme redeploy.

Not done: admin UI for configuring any of this (product pickers for bundles/BOGO/FBT
sets, tier editors) — the backend (`FeatureConfigController`, `DiscountSyncService`,
`ProxyController`) is ready to be driven by one, but nothing in `resources/js` calls it
yet beyond the dashboard's generic Fix flow.

## Phase 4 — AI, recipes, analytics (weeks 10-11): **built, partly verified**

Done:
- F-06 CRO Recipes: 3 seeded recipes (`RecipeSeeder`), preview/apply endpoints
  (`RecipeController`) sharing `FeatureActivationService` with manual activation so the
  plan gate is enforced identically either way.
- F-21/F-38 analytics: `vantora-pixel` (Web Pixel extension) subscribes to Shopify's
  standard Customer Events — verified field-by-field against
  `@shopify/web-pixels-extension`'s own `.d.ts` files (caught `init.data.shop.domain`
  not existing — it's `myshopifyDomain` — and a fabricated `analytics.visitorId` this
  way). Impression/click tracking added to all 7 theme blocks via a shared asset
  (`vantora-analytics.js`). Order-revenue attribution keyed on a `_vantora_source` cart
  line property the blocks set when adding to cart (Checkout Extensibility shops only —
  `CheckoutLineItem.properties` requires it).
- `GET /api/analytics` (basic totals all plans, per-feature breakdown Pro only per
  F-21/F-22), `GET /api/analytics/score-history`.
- F-19 AI Product Optimizer: `AiProviderClient` (Anthropic Messages API),
  `RunAiOptimizationJob`, `AiOptimizerController` (generate → approve → write to
  product via Admin API, nothing publishes without approval, per spec). **Needs
  `AI_PROVIDER_API_KEY` set** — currently empty, calls will fail until configured.
- F-08 weekly monitoring now actually emails the merchant (`WeeklyMonitoringMail`),
  queued through the same Redis queue the worker service processes. **Needs a real
  transactional mail provider configured** — `MAIL_MAILER` is still `log`
  (Postmark/Resend config already scaffolded in `config/services.php`, just needs
  credentials).

Not done:
- Basic vs. Advanced analytics UI (API exists, no charts/tables in the admin SPA)
- Smart/AI-suggested picks for Cart Upsell/FBT (manual picks only — the `picks` JSON
  shape supports either, nothing generates AI suggestions yet)

## Phase 5 — Launch MVP-1 (week 12): **partial**

Done: `PlanGateServiceTest` (Starter caps, Pro unlimited, Pro-only modules, AI usage
caps, downgrade behavior) — written but **not executed** in this sandbox (no
pdo_sqlite/pdo_pgsql available locally); should run clean in CI or on Railway.

Not done: broader test coverage (audit engine, activation flow, webhooks), weekly email
is wired but unproven end-to-end (no mail provider configured — see Phase 4), App Store
listing assets, App Store review submission.

## Phase 6 — Post-purchase and Thank you, MVP-2 (weeks 13-16): **scaffolded, unverified**

`post-purchase-upsell` extension built against the documented
`post-purchase-ui-extensions-react` API (ShouldRender → fetch best offer from
`POST /post-purchase/best-offer` → ShouldRender caches it → Render shows it →
accept calls `calculateChangeset`/`applyChangeset`). Deploy-validated (bundles and
schema-checks clean) but **functionally untested** — this needs Shopify's
post-purchase extension access grant (beta, access-by-request per spec section 2/13),
which hasn't been applied for yet. **Apply for this early** — spec section 11 flags it
as a lead-time risk, and F-33 is blocked on approval.

Backend: `PostPurchaseController::bestOffer` does simple cart-value-threshold matching
against the `offers` table — a real v1, but also unverified against a live checkout.

Not started: Thank You Page Offers (F-34), Post-purchase Survey (F-35), Order Status
Page Blocks (F-36), Referral/Reorder (F-37), Funnel Analytics UI (F-38 backend partly
covered by the analytics endpoints above).

## Phase 7 — Checkout blocks, MVP-2 (weeks 17-19): **one extension scaffolded, unverified**

`checkout-trust-badges` (F-39) built against the current (api_version 2026-07)
Checkout UI Extensions API — Preact + `<s-*>` web components, extension settings
fields for badge labels rather than a network call, so it renders with no round trip.
Deploy-validated but **functionally untested** — needs a Shopify Plus dev store, which
this environment doesn't have, to confirm the `shopify.settings.value` /
`<s-icon>` usage actually renders correctly.

Not started: F-40 (Checkout Goal Progress), F-41 (Checkout Upsell), F-42 (Checkout
Announcement), F-43 (Custom Fields), F-44 (Checkout FAQ/Guarantee), F-45 (Checkout Gift
Selector), the eligibility screen (spec: show "Requires Shopify Plus" on non-Plus
stores). These follow the same `ui_extension` pattern as checkout-trust-badges; rather
than scaffold all 7 with equally-unverified logic, only one was built out to keep
quality even with the "no bugs" goal.

## Known simplifications / tech debt to revisit

- Polaris React (`@shopify/polaris` v13) is deprecated upstream in favor of Polaris
  web components — kept it because it matches the spec's "Polaris and App Bridge"
  wording and is still functional, but a future pass may want to migrate.
- `backend/railway.json` (Config as Code) is deprecated by Railway in favor of
  `.railway/railway.ts` (Infrastructure as Code); it works until **2026-12-01**. Run
  `railway config migrate` before then.
- No Dockerfile — Railway's Railpack builder handles PHP/Laravel + the Vite frontend
  build natively (confirmed: it runs on FrankenPHP). If deep runtime tuning is ever
  needed (custom nginx/php-fpm config), that's the point to revisit this decision.
- Cart/theme content isn't actually scanned (see Phase 2 note above) — the audit is
  presence-of-active-config only, a defensible v1 but not what section 4.1/F-01
  describes.
- `DiscountSyncService`, `post-purchase-upsell`, and `checkout-trust-badges` are the
  three biggest "written carefully but not exercised against a live store" risks in
  the codebase — each has a docblock/comment saying so. Prioritize verifying these
  against a real dev store before relying on them in front of a merchant.
- `AI_PROVIDER_API_KEY` and a real mail provider (`MAIL_MAILER` + Postmark/Resend
  credentials) both need to be set in Railway variables before F-19 and F-08's email
  actually work in production — the code path is complete, the credentials aren't set.
