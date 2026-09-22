# Vantora build roadmap

Tracks progress against spec section 11 (Development phases). Update this file as
phases move from "not started" to "in progress" to "done" — it's the map back into
the spec for whoever (human or agent) picks this up next.

All extensions are deployed and released to the Partner Dashboard as of app version
**vantora-8** (`shopify app deploy`). The backend is deployed and live on Railway at
`https://vantora-production.up.railway.app`, auto-deploying on every push to `main`.

**`network_access` and post-purchase extension access are both approved** on this
app's Partner Dashboard listing. That resolves a mystery from the last deploy: the
first deploy of `thank-you-blocks` (which requests `network_access`) produced a
version that built and validated but did **not** auto-release — Shopify said the
capability "must be requested and approved" first — and the very next deploy
(`vantora-8`) released cleanly with no such warning, which now makes sense: approval
came through in between. Both capabilities being granted means F-33
(`post-purchase-upsell`) and the `network_access`-gated Thank You blocks are no
longer blocked on Shopify's side. They're still **functionally unverified** in the
sense that matters most now — no dev store has been used to actually load them in a
real checkout and click through the flow — but that's a testing gap, not a
permissions gap, and the next step is straightforwardly to connect a dev store and
try them rather than waiting on anything further from Shopify.

## Live install bug fix + billing rebuilt on Shopify Managed Pricing

A real merchant install (`speedpilot-dev.myshopify.com`) surfaced the embedded app
showing "Failed to load your store: Unknown or uninstalled shop." — confirming a gap
flagged but not yet fixed in the reference-app review below: `shopify.app.toml` has
no `use_legacy_install_flow = true`, so Shopify's default "managed installation"
grants scopes and embeds the app itself, **never calling `/auth/callback`**. The
classic OAuth flow this app relied on for provisioning a `Shop` row simply never ran.

Fixed: `VerifyShopifySessionToken` now performs **Token Exchange** itself the first
time it sees a session token for a shop it doesn't have a valid token for --
`ShopifyAuthService::exchangeSessionTokenForOfflineToken()` (request shape verified
against shopify.dev directly), sharing the same webhook-registration/shop-detail-sync
logic (`ShopProvisioningService`) the classic `/auth/callback` path uses, extracted
so the two provisioning paths can't drift apart. `/auth` + `/auth/callback` are kept
as a fallback/manual-install path, not removed.

Also, per direction: billing rebuilt on **Shopify Managed Pricing**, matching the
reference app exactly rather than the self-managed `appSubscriptionCreate` flow from
before -- the user is creating "Starter" and "Pro" plans directly in the Partner
Dashboard, and this app now never calls the Billing API itself. `BillingService`
only builds the link to Shopify's hosted plan page (`managePlanUrl()`) and syncs
whatever the `app_subscriptions/update` webhook reports (`resolvePlanKey()` matches
the webhook's free-text plan `name` against `config('shopify.plans.*.name')`
case-insensitively). `POST /api/billing/subscribe` is gone; `GET /api/billing/status`
replaces it.

**Hard paywall added**, matching the reference app's `active_subscription` gate: a
shop with no `Subscription` row in `status = 'active'` can now reach only `/api/shop`
and `/api/billing/status` -- every other API route (dashboard, feature-configs,
recipes, analytics, ai) is wrapped in a new `active_subscription` middleware
(`EnsureActiveSubscription`) in `routes/api.php`. Previously `Shop::currentPlan()`
defaulted an unsubscribed shop to `'starter'` and nothing blocked API access at
all -- there was no real paywall, just a frontend redirect a merchant could
route around by hitting the API directly. Also added `Shop::activeSubscription()`
(filters on `status = 'active'`, unlike `subscription()`'s "most recently created
row regardless of status") and scoped the weekly-monitoring cron to only
actively-subscribed shops, for the same "nothing without a subscription" reason.

Frontend: `Plans.tsx` is now an in-app preview only, every button opens Shopify's
hosted pricing page (`window.open(managePlanUrl, '_top')`); `Settings.tsx`'s
plan-switch buttons became a single "Manage plan" link there too.

**Deployed and live** (Railway), fixing the reported install bug going forward for
any shop hitting the embedded app after this ships. Not yet re-tested against the
specific store from the bug report at time of writing.

## Cross-cutting fixes from reviewing another Shopify/Laravel app's billing + checkout code

Compared Vantora's billing and checkout-extension code against a separate, more
mature Shopify+Laravel app in this environment (not copied from — used to sanity-check
Vantora's own, independently-written code, then verified anything non-obvious against
shopify.dev directly rather than trusting either app blindly). Found and fixed four
real issues:

1. **Offline access tokens now expire and weren't being refreshed.** Verified against
   shopify.dev (not just the reference app): new public apps must request expiring
   tokens (`expiring=1`) -- a 1-hour access token + 90-day refresh token -- and
   Vantora's OAuth callback was storing a token as if it were permanent, with no
   refresh path at all. Fixed: `add_token_refresh_columns_to_shops_table` migration,
   `Shop::needsTokenRefresh()`, `ShopifyAuthService::refreshAccessToken()`, and
   `ShopifyApiClient` now refreshes transparently before every call. This was a
   ticking bug -- API calls would have started failing outright once the first
   token expired, silently, some time after install.
2. **Billing had `test: false` hardcoded** in the `appSubscriptionCreate` mutation.
   Development stores (the only kind installed so far) silently refuse non-test
   charges, so billing likely couldn't have been tested at all as it stood. Moot now
   -- billing was rebuilt on Shopify Managed Pricing shortly after (see the section
   above), which doesn't call `appSubscriptionCreate` at all, but worth recording:
   the same "dev stores refuse non-test charges" fact applies to *any* future
   self-managed billing code in any Shopify app, not just this one.
3. **`thank-you-blocks` and `post-purchase-upsell` backend endpoints were fully
   public**, trusting a client-supplied `shop` field with no verification. Checked
   whether either extension surface actually has a way to authenticate itself:
   checkout/thank-you extensions do (`shopify.sessionToken.get()`, confirmed in
   `@shopify/ui-extensions`'s own types); the legacy post-purchase extension API does
   not (confirmed absent from `@shopify/post-purchase-ui-extensions`'s types) so it
   correctly stays public. Fixed the one that could be fixed:
   `VerifyShopifyExtensionSessionToken` (deliberately skips the `aud` claim check
   `VerifyShopifySessionToken` enforces for the embedded admin app, since it's
   unconfirmed whether an extension-issued token carries the same `aud` -- a wrong
   strict check fails silently, and that's worse than the looser check), wired into
   `/thank-you/*`, extension updated to attach the token.
4. **API version mismatch**: the backend's Admin GraphQL calls were pinned to
   `2026-01` while every field used in this session (functions, Checkout UI
   Extensions) was checked against `2026-07`. Aligned `SHOPIFY_API_VERSION` (backend
   + Railway vars) and `shopify.app.toml`'s webhook `api_version` to `2026-07` to
   match the extensions.

Also confirmed as **already correct** rather than changed: `purchase.thank-you.block.render`
as the Thank You page target, `shopify.orderConfirmation.value.order.id`, and the
overall shape of the webhook-driven billing sync -- the reference app's `Subscription`
sync additionally cancels other stale-`active` rows when a new one activates (handles
a plan switch whose own webhook arrived out of order); adopted that same fix in
`BillingService::activateFromWebhook` since Vantora's `Shop::subscription()` relation
has the identical latent gap.

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

Now done (was the top gap, closed this session): the admin SPA has a real **Tools**
screen (`resources/js/pages/Tools.tsx`) covering every feature type, grouped by
category, with activate/pause/delete and a JSON settings editor (shape hints per
type, no dedicated form or product picker yet — see below).

Not done / needs a real pass:
- Product/variant **pickers** for Tools' settings editor — still a raw JSON textarea
  for anything needing a Shopify GID (bundle components, BOGO products, gift variant,
  cart upsell/FBT picks). `lib/resourcePicker.ts` (`pickProduct()`, wrapping App
  Bridge's `shopify.resourcePicker()`, confirmed against shopify.dev) now exists and
  is wired into the AI Optimizer's product selection — a merchant flagged that screen
  specifically as needing a real picker instead of a pasted GID. Extending the same
  helper into Tools' bundle/BOGO/free-gift/cart-upsell forms is a smaller lift now
  that it exists, just not done yet.
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
- Score history now has both the API and a chart (`resources/js/components/ScoreChart.tsx`,
  on the Analytics tab) — hand-built SVG, single series so no legend needed
  (dataviz-skill guidance), 2px line, rounded data-ends, hover crosshair/tooltip.

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

Admin UI: the Tools screen now creates/activates configs of every type in this phase
(`FeatureConfigController` → `FeatureActivationService` → `DiscountSyncService`), just
through the generic JSON settings editor rather than dedicated tier/product-picker
forms — see the Phase 1 "product/variant pickers" note above.

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

Now done: Basic/Advanced analytics UI (`resources/js/pages/Analytics.tsx` — totals for
all plans, per-feature breakdown gated to Pro) and an AI Optimizer screen
(`resources/js/pages/AiOptimizer.tsx` — submit a product, see usage remaining,
approve/discard).

Not done:
- Smart/AI-suggested picks for Cart Upsell/FBT (manual picks only — the `picks` JSON
  shape supports either, nothing generates AI suggestions yet)

## Phase 5 — Launch MVP-1 (week 12): **partial**

Done: `PlanGateServiceTest` (Starter caps, Pro unlimited, Pro-only modules, AI usage
caps, downgrade behavior) — written but **not executed** in this sandbox (no
pdo_sqlite/pdo_pgsql available locally); should run clean in CI or on Railway.

Not done: broader test coverage (audit engine, activation flow, webhooks), weekly email
is wired but unproven end-to-end (no mail provider configured — see Phase 4), App Store
listing assets, App Store review submission.

## Phase 6 — Post-purchase and Thank you, MVP-2 (weeks 13-16): **built, functionally unverified**

`post-purchase-upsell` extension built against the documented
`post-purchase-ui-extensions-react` API (ShouldRender → fetch best offer from
`POST /post-purchase/best-offer` → ShouldRender caches it → Render shows it →
accept calls `calculateChangeset`/`applyChangeset`). Deploy-validated (bundles and
schema-checks clean). **Shopify's post-purchase extension access grant is approved**
on this app's Partner Dashboard listing — F-33 is not blocked on Shopify's side
anymore. What's left is ordinary testing: no dev store has been used yet to actually
install the app, place a test order, and click through the one-click-upsell flow.

Backend: `PostPurchaseController::bestOffer` does simple cart-value-threshold matching
against the `offers` table — a real v1, but also unverified against a live checkout.

`thank-you-blocks` extension built covering F-34 (cross-sell + next-order discount
code), F-35 (survey), F-37 (referral link) — one extension, three blocks, since all
three target the same `purchase.thank-you.block.render` surface. Uses the *modern*
Checkout UI Extensions API (`<s-*>` web components), confirmed correct against
`@shopify/ui-extensions`'s own component/prop type definitions this time (not just
reviewed) — this is a **different, newer API** than `post-purchase-upsell`'s
`post-purchase-ui-extensions-react`, which is legacy and specific to the one-click
upsell interstitial only. It requests `network_access` (needed to call the backend
for offer/survey/referral content) — that capability is **approved** on this app's
Partner Dashboard listing (see the note near the top of this file for the `vantora-7`
→ `vantora-8` release history that reflects when approval landed), so it's live as of
`vantora-8`. Backend: `ThankYouController` (`/thank-you/data`, `/thank-you/survey`,
now session-token authenticated) — still functionally unverified against a real
checkout, same as everything else in this phase; that's a testing gap now, not a
permissions one.

**Not started: F-36 Order Status Page Blocks.** Investigated and deliberately not
guessed at: Order Status extensions live on a *different, separate* extension surface
(`customer-account.order-status.block.render` and friends — part of the "customer
accounts" API, distinct from the `purchase.*` checkout/thank-you targets used above),
which needs its own research pass and likely the new Customer Accounts system enabled
to test. Building it against the wrong surface API would just be another unverified
guess, so it's left undone rather than faked.

F-38 Funnel Analytics UI: backend covered by the analytics endpoints from Phase 4; no
dedicated funnel view (views/acceptance-rate/drop-off) in the admin SPA yet.

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
- `network_access` for checkout/thank-you `ui_extension`s is approved on this app's
  listing already (see Phase 6), so this isn't a blocker for any future F-40–F-45
  extension that needs a network call — worth knowing it's a real Partner Dashboard
  approval step in general, just not one this app is waiting on anymore.
