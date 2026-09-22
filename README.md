# Vantora

Shopify CRO Intelligence & Funnel App — Scan → Find → Fix → Measure.
Full spec: `Vantora_MVP_Spec_Starter_19.99_Pro_49.99.docx`.

## Repo layout

```
backend/         Laravel 13 app: API, admin backend, AND the embedded admin SPA
  resources/js/    React + Polaris + App Bridge admin UI, built by Laravel's own Vite pipeline
  resources/views/app.blade.php  the single page the embedded app boots from
extensions/       Shopify CLI-managed extensions (theme app extension; checkout/post-purchase later)
shopify.app.toml  Shopify app config (client id, scopes, redirect URLs, compliance webhooks)
```

There is no separate frontend project and no Dockerfile. The admin UI lives inside
`backend/resources/js` and is built by `laravel-vite-plugin` — the same mechanism
every stock Laravel app uses for its frontend. Railway builds and runs this with its
own native PHP/Laravel builder (Railpack): no hand-written nginx/php-fpm/supervisord
config to maintain.

## How it's wired together

One Laravel app serves everything: the embedded admin SPA at `/` (`resources/views/app.blade.php`,
which mounts the React app built from `resources/js/app.tsx`), the JSON API under
`/api/*` (session-token authenticated — see `VerifyShopifySessionToken`), and
Shopify's OAuth + webhook endpoints.

Three Railway services run this one codebase with three different start commands
(Railway's standard pattern for a web + worker + cron split, instead of bundling all
three processes into one container):
- **Vantora** — the web process. Public domain, health-checked at `/up`.
- **Vantora-worker** — `php artisan queue:work redis ...`. Processes audits and other
  queued jobs. No public domain.
- **Vantora-monitoring** — a Railway cron service (`0 6 * * 1`, i.e. weekly) running
  `php artisan vantora:run-weekly-monitoring` directly. No long-running scheduler loop.

Theme app extension blocks (Sticky ATC, Free Shipping Bar, Trust Badges, FAQ) and
future checkout/post-purchase extensions are deployed separately via Shopify CLI
(`shopify app deploy`), not through Railway — Shopify hosts extension code on its
own CDN/runtime.

## Local development

```
cd backend
composer install
npm install
cp .env.example .env   # fill in DATABASE_URL / REDIS_URL / SHOPIFY_* — see below
php artisan key:generate
php artisan migrate
npm run build           # or `npm run dev` for hot reload alongside `php artisan serve`
php artisan serve
```
Note: this machine has no `pdo_pgsql`/`redis` PHP extensions installed locally, and
Railway's internal hostnames (`*.railway.internal`) only resolve from inside Railway's
network — so `migrate`/`serve` won't work against the Railway DB from a local shell.
Use the public proxy connection string from the Railway Postgres dashboard for local
dev, or run Postgres/Redis locally.

## Deployment (Railway)

Already wired up for this project (`railway link`d to project `Vantora`):
- **Vantora**, **Vantora-worker**, **Vantora-monitoring** services all build from
  `nayanvirani/Vantora` on `main`, rooted at `/backend`, using Railway's Railpack
  builder (config in `backend/railway.json`) — no Dockerfile. `preDeployCommand`
  runs `artisan migrate --force` before each web deploy.
- **Postgres** and **Redis-Lhom** services back all three. `DATABASE_URL` / `REDIS_URL`
  are injected as cross-service variable references (`${{Postgres.DATABASE_URL}}`,
  `${{Redis-Lhom.REDIS_URL}}`), and the worker/monitoring services reference the web
  service's own vars for secrets (`${{Vantora.APP_KEY}}`, `${{Vantora.SHOPIFY_API_KEY}}`,
  etc.) so there's one place to rotate each secret.
- Push to `main` → all three services redeploy automatically.

To ship extension changes (theme blocks, later checkout/post-purchase extensions),
use the Shopify CLI separately: `shopify app deploy` from the repo root (reads
`shopify.app.toml`). That's independent of the Railway git-push deploy.

## Shopify Partner Dashboard checklist

- App URL: `https://vantora-production.up.railway.app/`
- Allowed redirection URL: `https://vantora-production.up.railway.app/auth/callback`
- GDPR webhooks: point at `/webhooks/shopify/customers-data-request`,
  `/customers-redact`, `/shop-redact` (already declared in `shopify.app.toml`).
- Post-purchase extension access and `network_access` are both approved on this
  app's listing (spec section 13's risk about F-33's access grant is resolved).

See `ROADMAP.md` for what's built vs. still open across the seven development phases.
