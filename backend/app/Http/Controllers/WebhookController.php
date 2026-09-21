<?php

namespace App\Http\Controllers;

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
        [, , , $event, $done] = $this->authenticate($request);

        if (! $done) {
            // TODO(phase 4): attribute the order to funnel events / analytics_daily.
            $this->markProcessed($event);
        }

        return response()->noContent();
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
