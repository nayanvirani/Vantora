# Vantora

Shopify CRO Intelligence & Funnel App — Scan → Find → Fix → Measure.
Full spec: `Vantora_MVP_Spec_Starter_19.99_Pro_49.99.docx`.

## Repo layout

```
backend/        Laravel 13 API + admin backend (PostgreSQL, Redis queues/cache/sessions)
admin/           Embedded admin SPA — React + Vite + Polaris + App Bridge
extensions/      Shopify CLI-managed extensions (theme app extension; checkout/post-purchase later)
docker/          nginx + php-fpm + supervisord config used by the production image
Dockerfile       Multi-stage build: builds admin/, installs backend/ deps, assembles the runtime image
railway.json     Tells Railway to build with the Dockerfile instead of auto-detecting
shopify.app.toml Shopify app config (client id, scopes, redirect URLs, compliance webhooks)
```

## How it's wired together

One Laravel service serves everything: the built admin SPA at `/` (from `public/app/`,
copied in at Docker build time from `admin/dist`), the JSON API under `/api/*`
(session-token authenticated, see `VerifyShopifySessionToken`), and Shopify's OAuth
+ webhook endpoints. `docker/supervisord.conf` runs nginx, php-fpm, a Redis queue
worker, and a `schedule:run` loop (for weekly monitoring) in the same container.

Theme app extension blocks (Sticky ATC, Free Shipping Bar, Trust Badges, FAQ) and
future checkout/post-purchase extensions are deployed separately via Shopify CLI
(`shopify app deploy`), not through Railway — Shopify hosts extension code on its
own CDN/runtime.

## Local development

Backend:
```
cd backend
composer install
cp .env.example .env   # fill in DATABASE_URL / REDIS_URL / SHOPIFY_* — see below
php artisan key:generate
php artisan migrate
php artisan serve
```
Note: this machine has no `pdo_pgsql`/`redis` PHP extensions installed locally, and
Railway's internal hostnames (`*.railway.internal`) only resolve from inside Railway's
network — so `migrate`/`serve` won't work against the Railway DB from a local shell.
Use the public proxy connection string from the Railway Postgres dashboard for local
dev, or run Postgres/Redis locally.

Admin SPA:
```
cd admin
npm install
npm run dev   # standalone dev server; set VITE_API_BASE to point at your backend
```

## Deployment (Railway)

Already wired up for this project (`railway link`d to project `Vantora`):
- **Vantora** service — builds `Dockerfile` at the repo root (set via `railway.json`),
  deploys on every push to `main` on `nayanvirani/Vantora`. Public domain:
  `https://vantora-production.up.railway.app`.
- **Postgres** and **Redis-Lhom** services — `DATABASE_URL` / `REDIS_URL` are injected
  into the Vantora service as cross-service variable references
  (`${{Postgres.DATABASE_URL}}`, `${{Redis-Lhom.REDIS_URL}}`), so rotating credentials
  on those services doesn't require touching Vantora's config.
- `APP_KEY`, `SHOPIFY_API_KEY`, `SHOPIFY_API_SECRET`, and friends are set directly as
  Railway variables on the Vantora service (never committed to git).
- The container entrypoint (`docker/entrypoint.sh`) runs `artisan migrate --force` on
  every deploy before starting the web process.

To ship extension changes (theme blocks, later checkout/post-purchase extensions),
use the Shopify CLI separately: `shopify app deploy` from the repo root (reads
`shopify.app.toml`). That's independent of the Railway git-push deploy.

## Shopify Partner Dashboard checklist

- App URL: `https://vantora-production.up.railway.app/`
- Allowed redirection URL: `https://vantora-production.up.railway.app/auth/callback`
- GDPR webhooks: point at `/webhooks/shopify/customers-data-request`,
  `/customers-redact`, `/shop-redact` (already declared in `shopify.app.toml`).
- Apply for **post-purchase extension access** early — F-33 depends on it (see spec
  section 13, Risks).

See `ROADMAP.md` for what's built vs. still open across the seven development phases.
