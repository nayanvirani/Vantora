<?php

namespace App\Http\Controllers;

use App\Models\AnalyticsDaily;
use App\Models\FeatureConfig;
use App\Models\Shop;
use App\Models\WebhookEvent;
use App\Services\Shopify\BillingService;
use App\Services\Shopify\ShopifyWebhookVerifier;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class WebhookController extends Controller
{
    public function __construct(protected ShopifyWebhookVerifier $verifier)
    {
    }

    protected function authenticate(Request $request): array
    {
        abort_unless($this->verifier->verify($request), 401, 'Invalid webhook signature.');

        $domain = $request->header('X-Shopify-Shop-Domain');
        $topic = $request->header('X-Shopify-Topic', 'unknown');
        $payload = $request->json()->all();

        $hash = hash('sha256', $request->getContent());
        $event = WebhookEvent::query()->firstOrCreate(
            ['topic' => $topic, 'payload_hash' => $hash],
            ['shop_id' => Shop::query()->where('domain', $domain)->value('id')]
        );

        // Idempotency: Shopify may redeliver the same webhook.
        $alreadyProcessed = $event->wasRecentlyCreated === false && $event->processed_at !== null;

        return [$domain, $topic, $payload, $event, $alreadyProcessed];
    }

    protected function markProcessed(WebhookEvent $event): void
    {
        $event->update(['processed_at' => now()]);
    }

    public function appUninstalled(Request $request)
    {
        [$domain, , , $event, $done] = $this->authenticate($request);

        if (! $done) {
            Shop::query()->where('domain', $domain)->update([
                'uninstalled_at' => now(),
                'access_token' => null,
            ]);

            $this->markProcessed($event);
        }

        return response()->noContent();
    }

    public function appSubscriptionsUpdate(Request $request, BillingService $billing)
    {
        [$domain, , $payload, $event, $done] = $this->authenticate($request);

        if (! $done) {
            $shop = Shop::query()->where('domain', $domain)->first();

            if ($shop) {
                $billing->activateFromWebhook($shop, $payload);
            }

            $this->markProcessed($event);
        }

        return response()->noContent();
    }

    public function shopUpdate(Request $request)
    {
        [$domain, , $payload, $event, $done] = $this->authenticate($request);

        if (! $done) {
            Shop::query()->where('domain', $domain)->update([
                'shopify_plan' => $payload['plan_name'] ?? null,
            ]);

            $this->markProcessed($event);
        }

        return response()->noContent();
    }

    public function themesPublish(Request $request)
    {
        [$domain, , $payload, $event, $done] = $this->authenticate($request);

        if (! $done) {
            Shop::query()->where('domain', $domain)->update([
                'theme_id' => $payload['id'] ?? null,
            ]);

            // TODO(phase 2): re-run the theme compatibility check.
            $this->markProcessed($event);
        }

        return response()->noContent();
    }

    public function themesUpdate(Request $request)
    {
        [, , , $event, $done] = $this->authenticate($request);

        if (! $done) {
            $this->markProcessed($event);
        }

        return response()->noContent();
    }

    public function productsUpdate(Request $request)
    {
        [, , , $event, $done] = $this->authenticate($request);

        if (! $done) {
            $this->markProcessed($event);
        }

        return response()->noContent();
    }

    public function productsDelete(Request $request)
    {
        [, , , $event, $done] = $this->authenticate($request);

        if (! $done) {
            $this->markProcessed($event);
        }

        return response()->noContent();
    }

    public function ordersCreate(Request $request)
    {
        [$domain, , $payload, $event, $done] = $this->authenticate($request);

        if (! $done) {
            $shop = Shop::query()->where('domain', $domain)->first();

            if ($shop) {
                $this->attributeOrderToOffers($shop, $payload);
            }

            $this->markProcessed($event);
        }

        return response()->noContent();
    }

    /**
     * F-21/F-22 analytics for the Offers category (quantity_discount, bogo,
     * free_gift, bundle): unlike the storefront/funnel blocks (which fire
     * their own impression/click events -- see PixelEventController), a
     * Discount Function has no Vantora-rendered UI to attach a client-side
     * event to. The only signal is the order itself: Shopify's
     * orders/create payload lists each applied discount's title, which
     * DiscountSyncService set to the offer's name/type when the discount
     * was created, so it's matched back to a FeatureConfig here. Revenue is
     * the order's full total (same simplification PixelEventController
     * uses for storefront-sourced orders), not just the discounted amount.
     */
    protected function attributeOrderToOffers(Shop $shop, array $payload): void
    {
        $discountTitles = collect($payload['discount_applications'] ?? [])
            ->pluck('title')
            ->filter()
            ->unique();

        if ($discountTitles->isEmpty()) {
            return;
        }

        $orderTotal = (float) ($payload['total_price'] ?? 0);
        $today = now()->toDateString();

        $configs = FeatureConfig::query()
            ->where('shop_id', $shop->id)
            ->whereIn('type', ['quantity_discount', 'bogo', 'free_gift', 'bundle'])
            ->where('status', 'active')
            ->get();

        foreach ($configs as $config) {
            if (! $discountTitles->contains($config->name ?? $config->type)) {
                continue;
            }

            $row = AnalyticsDaily::query()->firstOrCreate(
                ['shop_id' => $shop->id, 'config_id' => $config->id, 'date' => $today],
                ['impressions' => 0, 'clicks' => 0, 'orders' => 0, 'revenue' => 0]
            );

            $row->increment('orders');
            $row->increment('revenue', $orderTotal);
        }
    }

    /**
     * GDPR mandatory webhook: a customer asked the store for their data.
     * This app does not store customer PII beyond survey answers and order
     * references tied to shop_id, so we just log the request for audit.
     */
    public function customersDataRequest(Request $request)
    {
        [, , $payload, $event, $done] = $this->authenticate($request);

        if (! $done) {
            Log::info('GDPR customers/data_request received', ['payload' => $payload]);
            $this->markProcessed($event);
        }

        return response()->noContent();
    }

    /**
     * GDPR mandatory webhook: erase the named customer's data.
     */
    public function customersRedact(Request $request)
    {
        [$domain, , $payload, $event, $done] = $this->authenticate($request);

        if (! $done) {
            $shop = Shop::query()->where('domain', $domain)->first();
            $orderIds = collect($payload['orders_to_redact'] ?? [])->map(fn ($id) => (string) $id);

            if ($shop && $orderIds->isNotEmpty()) {
                \App\Models\SurveyResponse::query()
                    ->where('shop_id', $shop->id)
                    ->whereIn('order_id', $orderIds)
                    ->delete();
            }

            $this->markProcessed($event);
        }

        return response()->noContent();
    }

    /**
     * GDPR mandatory webhook: 48h after uninstall, erase all shop data.
     */
    public function shopRedact(Request $request)
    {
        [$domain, , , $event, $done] = $this->authenticate($request);

        if (! $done) {
            $shop = Shop::query()->where('domain', $domain)->first();

            if ($shop) {
                $shop->delete(); // cascades via FK constraints
            }

            $this->markProcessed($event);
        }

        return response()->noContent();
    }
}
