<?php

namespace App\Http\Controllers;

use App\Models\AnalyticsDaily;
use App\Models\Event;
use App\Models\FeatureConfig;
use App\Models\Shop;
use Illuminate\Http\Request;

/**
 * Ingests Shopify Customer Events reported by extensions/vantora-pixel
 * (F-21/F-38). Public endpoint reached directly from the browser sandbox
 * the pixel runs in -- not behind session-token or app-proxy signature
 * auth, since the pixel can't attach either. Trust is limited to "this
 * shop domain is installed"; rate-limited via routes/web.php.
 */
class PixelEventController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'shop' => ['required', 'string'],
            'type' => ['required', 'string'],
            'client_id' => ['nullable', 'string'],
            'occurred_at' => ['nullable', 'date'],
            'data' => ['nullable', 'array'],
            // Set by theme blocks reporting their own impression/click,
            // since only the block knows when it rendered or was clicked --
            // Shopify's standard Customer Events don't cover that.
            'feature_type' => ['nullable', 'string'],
        ]);

        $shop = Shop::query()->where('domain', $data['shop'])->whereNull('uninstalled_at')->first();
        if (! $shop) {
            return response()->json(['ok' => false], 404);
        }

        $config = null;
        if (! empty($data['feature_type']) && in_array($data['type'], ['impression', 'click'], true)) {
            $config = FeatureConfig::query()
                ->where('shop_id', $shop->id)
                ->where('type', $data['feature_type'])
                ->where('status', 'active')
                ->first();

            if ($config) {
                $row = AnalyticsDaily::query()->firstOrCreate(
                    ['shop_id' => $shop->id, 'config_id' => $config->id, 'date' => now()->toDateString()],
                    ['impressions' => 0, 'clicks' => 0, 'orders' => 0, 'revenue' => 0]
                );
                $row->increment($data['type'] === 'impression' ? 'impressions' : 'clicks');
            }
        }

        Event::query()->create([
            'shop_id' => $shop->id,
            'config_id' => $config?->id,
            'type' => $data['type'],
            'session_ref' => $data['client_id'] ?? null,
            'occurred_at' => $data['occurred_at'] ?? now(),
        ]);

        if ($data['type'] === 'checkout_completed') {
            $this->attributeOrder($shop, $data['data'] ?? []);
        }

        return response()->json(['ok' => true]);
    }

    /**
     * Attributes revenue/order counts to whichever Vantora feature a line
     * item's `_vantora_source` property points at (set by the theme blocks
     * when they add to cart), aggregating into analytics_daily.
     */
    protected function attributeOrder(Shop $shop, array $checkoutData): void
    {
        $lineItems = $checkoutData['line_items'] ?? [];
        $today = now()->toDateString();

        $sources = collect($lineItems)->pluck('vantora_source')->filter()->unique();

        foreach ($sources as $source) {
            $config = FeatureConfig::query()
                ->where('shop_id', $shop->id)
                ->where('type', $source)
                ->where('status', 'active')
                ->first();

            if (! $config) {
                continue;
            }

            $revenue = collect($lineItems)
                ->where('vantora_source', $source)
                ->sum(fn ($line) => (float) ($line['price'] ?? 0) * (int) ($line['quantity'] ?? 1));

            $row = AnalyticsDaily::query()->firstOrCreate(
                ['shop_id' => $shop->id, 'config_id' => $config->id, 'date' => $today],
                ['impressions' => 0, 'clicks' => 0, 'orders' => 0, 'revenue' => 0]
            );

            $row->increment('orders');
            $row->increment('revenue', $revenue);

            Event::query()->create([
                'shop_id' => $shop->id,
                'config_id' => $config->id,
                'type' => 'order',
                'occurred_at' => now(),
            ]);
        }
    }
}
