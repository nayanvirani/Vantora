<?php

namespace App\Services;

use App\Models\Audit;
use App\Models\AuditIssue;
use App\Models\AuditScore;
use App\Models\FeatureConfig;
use App\Models\Recommendation;
use App\Models\Shop;
use App\Services\Shopify\ShopifyApiClient;
use Illuminate\Support\Facades\Log;

/**
 * F-01 CRO Audit / F-02 Store Health Score / F-03 Recommendations.
 *
 * Beyond "is a Vantora tool active" (the original v1 rule set), this also
 * inspects real store content per spec section 4.1: product image/description
 * coverage (product_page), the shipping bar's threshold against actual AOV
 * from real order data (cart), and whether an active Sticky ATC is actually
 * visible on mobile (mobile) rather than just switched on -- a merchant can
 * activate a tool and still have it configured in a way that doesn't help.
 */
class AuditEngine
{
    /**
     * area => [feature type, title, explanation, severity]
     */
    protected const RULES = [
        'product_page' => [
            ['sticky_atc', 'Sticky Add to Cart', 'A sticky Add to Cart bar keeps the buy button visible while shoppers scroll, especially on mobile.', 'high'],
            ['faq', 'Product FAQ', 'A visible FAQ block answers objections on the product page instead of losing the shopper to search.', 'medium'],
        ],
        'trust' => [
            ['trust_badges', 'Trust Badges', 'Payment and guarantee badges near the buy button reduce checkout hesitation.', 'medium'],
        ],
        'cart' => [
            ['shipping_bar', 'Free Shipping Bar', 'A progress bar toward a free-shipping threshold is one of the highest-converting AOV levers.', 'high'],
        ],
        'offers' => [
            ['bundle', 'Bundles', 'Bundling related products increases average order value with minimal setup.', 'low'],
            ['quantity_discount', 'Quantity Discount', 'Tiered quantity pricing nudges shoppers to buy more per order.', 'low'],
        ],
    ];

    public function run(Shop $shop): Audit
    {
        $audit = Audit::query()->create([
            'shop_id' => $shop->id,
            'status' => 'running',
            'started_at' => now(),
        ]);

        try {
            $activeTypes = FeatureConfig::query()
                ->where('shop_id', $shop->id)
                ->where('status', 'active')
                ->pluck('type')
                ->all();

            $areaScores = [];

            foreach (self::RULES as $area => $checks) {
                $total = count($checks);
                $passed = 0;

                foreach ($checks as [$type, $title, $explanation, $severity]) {
                    $ok = in_array($type, $activeTypes, true);
                    $passed += $ok ? 1 : 0;

                    if (! $ok) {
                        AuditIssue::query()->create([
                            'audit_id' => $audit->id,
                            'code' => "missing-{$type}",
                            'severity' => $severity,
                            'area' => $area,
                            'title' => $title,
                            'explanation' => $explanation,
                            'status' => 'open',
                            'fix_type' => $type,
                        ]);
                    }
                }

                $areaScores[$area] = $total > 0 ? (int) round(($passed / $total) * 100) : 100;
            }

            $themeScore = $this->themeCompatibilityScore($shop, $audit);
            $mobileCtaScore = $this->mobileCtaVisibilityScore($shop, $audit, $activeTypes);
            $areaScores['mobile'] = (int) round(($themeScore + $mobileCtaScore) / 2);

            $productContentScore = $this->productContentScore($shop, $audit);
            if ($productContentScore !== null) {
                $areaScores['product_page'] = (int) round((($areaScores['product_page'] ?? 100) + $productContentScore) / 2);
            }

            $shippingThresholdScore = $this->shippingThresholdScore($shop, $audit, $activeTypes);
            if ($shippingThresholdScore !== null) {
                $areaScores['cart'] = (int) round((($areaScores['cart'] ?? 100) + $shippingThresholdScore) / 2);
            }

            foreach ($areaScores as $area => $score) {
                AuditScore::query()->create([
                    'audit_id' => $audit->id,
                    'area' => $area,
                    'score' => $score,
                ]);
            }

            $scoreTotal = (int) round(array_sum($areaScores) / count($areaScores));

            $audit->update([
                'status' => 'completed',
                'score_total' => $scoreTotal,
                'finished_at' => now(),
            ]);

            $this->rebuildRecommendations($shop, $audit);
        } catch (\Throwable $e) {
            Log::error('Audit failed', ['shop' => $shop->domain, 'error' => $e->getMessage()]);
            $audit->update(['status' => 'failed', 'finished_at' => now()]);
        }

        return $audit->fresh(['scores', 'issues']);
    }

    protected function themeCompatibilityScore(Shop $shop, Audit $audit): int
    {
        try {
            $client = new ShopifyApiClient($shop);

            $theme = $client->graphql(<<<'GQL'
                query {
                    themes(first: 1, roles: [MAIN]) {
                        nodes { id name role }
                    }
                }
                GQL)->json('data.themes.nodes.0');

            if (! $theme) {
                return 50;
            }

            $shop->update(['theme_id' => (string) $theme['id']]);

            // OS 2.0 themes expose JSON templates; vintage themes do not.
            $assets = $client->rest('GET', str_replace('gid://shopify/OnlineStoreTheme/', 'themes/', $theme['id']) . '/assets.json?asset[key]=templates/index.json');
            $isOs2 = $assets->successful() && ! empty($assets->json('asset'));

            if (! $isOs2) {
                AuditIssue::query()->create([
                    'audit_id' => $audit->id,
                    'code' => 'vintage-theme',
                    'severity' => 'high',
                    'area' => 'mobile',
                    'title' => 'Theme is not Online Store 2.0',
                    'explanation' => 'App blocks and app embeds only render on Online Store 2.0 themes. Upgrade the theme to use Vantora\'s storefront tools.',
                    'status' => 'open',
                    'fix_type' => null,
                ]);

                return 40;
            }

            return 100;
        } catch (\Throwable $e) {
            Log::warning('Theme compatibility check failed', ['shop' => $shop->domain, 'error' => $e->getMessage()]);

            return 50;
        }
    }

    /**
     * Spec explicitly calls out "mobile CTA visibility" as its own check,
     * not just "is Sticky ATC active" -- a merchant can activate it with
     * show_mobile switched off (e.g. picked it for desktop only) and the
     * original rule set would still mark this area healthy.
     */
    protected function mobileCtaVisibilityScore(Shop $shop, Audit $audit, array $activeTypes): int
    {
        if (! in_array('sticky_atc', $activeTypes, true)) {
            // Already flagged as a missing tool under product_page; not
            // double-counted here.
            return 100;
        }

        $config = FeatureConfig::query()
            ->where('shop_id', $shop->id)->where('type', 'sticky_atc')->where('status', 'active')
            ->first();

        if ($config && ($config->settings['show_mobile'] ?? true) === false) {
            AuditIssue::query()->create([
                'audit_id' => $audit->id,
                'code' => 'sticky-atc-hidden-on-mobile',
                'severity' => 'high',
                'area' => 'mobile',
                'title' => 'Sticky Add to Cart is hidden on mobile',
                'explanation' => 'Most storefront traffic is mobile, and Sticky Add to Cart is switched off there in its current settings -- most shoppers never see it.',
                'status' => 'open',
                'fix_type' => 'sticky_atc',
            ]);

            return 40;
        }

        return 100;
    }

    /**
     * Real product-content scan per spec (images, description) rather than
     * just Vantora tool state -- samples up to 50 products (a full-catalog
     * scan is unnecessary for a directional score and keeps this fast).
     * Returns null (skip, don't affect the score) if the shop has no
     * products yet or the API call fails, rather than penalizing a report
     * that couldn't actually check anything.
     */
    protected function productContentScore(Shop $shop, Audit $audit): ?int
    {
        try {
            $client = new ShopifyApiClient($shop);

            $products = $client->graphql(<<<'GQL'
                query {
                    products(first: 50, query: "status:active") {
                        nodes { id featuredMedia { id } description }
                    }
                }
                GQL)->json('data.products.nodes') ?? [];

            $total = count($products);
            if ($total === 0) {
                return null;
            }

            $missingImages = collect($products)->filter(fn ($p) => empty($p['featuredMedia']))->count();
            $missingDescriptions = collect($products)->filter(fn ($p) => trim((string) ($p['description'] ?? '')) === '')->count();

            if ($missingImages > 0) {
                AuditIssue::query()->create([
                    'audit_id' => $audit->id,
                    'code' => 'products-missing-images',
                    'severity' => $missingImages / $total > 0.2 ? 'high' : 'medium',
                    'area' => 'product_page',
                    'title' => "{$missingImages} of {$total} products have no image",
                    'explanation' => 'A product with no image converts far below one with a clear photo -- shoppers skip past it in search and collections.',
                    'status' => 'open',
                    'fix_type' => null,
                ]);
            }

            if ($missingDescriptions > 0) {
                AuditIssue::query()->create([
                    'audit_id' => $audit->id,
                    'code' => 'products-missing-descriptions',
                    'severity' => $missingDescriptions / $total > 0.2 ? 'medium' : 'low',
                    'area' => 'product_page',
                    'title' => "{$missingDescriptions} of {$total} products have no description",
                    'explanation' => 'A missing description leaves shoppers with only a photo and price to decide with, and hurts search ranking on the product page itself.',
                    'status' => 'open',
                    'fix_type' => null,
                ]);
            }

            $imageRate = 1 - ($missingImages / $total);
            $descriptionRate = 1 - ($missingDescriptions / $total);

            return (int) round((($imageRate + $descriptionRate) / 2) * 100);
        } catch (\Throwable $e) {
            Log::warning('Product content scan failed', ['shop' => $shop->domain, 'error' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * Spec: "cart (shipping threshold vs AOV...)" -- a threshold set far
     * above or below actual average order value doesn't work as a CRO
     * lever either way (unreachable, or already the default outcome).
     * Uses real recent order data, not Vantora's own attributed orders.
     */
    protected function shippingThresholdScore(Shop $shop, Audit $audit, array $activeTypes): ?int
    {
        if (! in_array('shipping_bar', $activeTypes, true)) {
            return null;
        }

        $config = FeatureConfig::query()
            ->where('shop_id', $shop->id)->where('type', 'shipping_bar')->where('status', 'active')
            ->first();

        $threshold = (float) ($config->settings['threshold'] ?? 0);
        if ($threshold <= 0) {
            return null;
        }

        try {
            $client = new ShopifyApiClient($shop);

            $orders = $client->graphql(<<<'GQL'
                query {
                    orders(first: 50, sortKey: CREATED_AT, reverse: true) {
                        nodes { totalPriceSet { shopMoney { amount } } }
                    }
                }
                GQL)->json('data.orders.nodes') ?? [];

            if (empty($orders)) {
                return null;
            }

            $aov = collect($orders)->avg(fn ($o) => (float) ($o['totalPriceSet']['shopMoney']['amount'] ?? 0));
            if ($aov <= 0) {
                return null;
            }

            $ratio = $threshold / $aov;

            if ($ratio > 2.0) {
                AuditIssue::query()->create([
                    'audit_id' => $audit->id,
                    'code' => 'shipping-threshold-too-high',
                    'severity' => 'high',
                    'area' => 'cart',
                    'title' => 'Free shipping threshold is far above your average order',
                    'explanation' => sprintf('Your threshold is $%.2f but recent orders average $%.2f -- most shoppers will never see the bar move and it stops working as an AOV lever.', $threshold, $aov),
                    'status' => 'open',
                    'fix_type' => 'shipping_bar',
                ]);

                return 40;
            }

            if ($ratio < 0.5) {
                AuditIssue::query()->create([
                    'audit_id' => $audit->id,
                    'code' => 'shipping-threshold-too-low',
                    'severity' => 'medium',
                    'area' => 'cart',
                    'title' => 'Free shipping threshold is well below your average order',
                    'explanation' => sprintf('Your threshold is $%.2f but recent orders average $%.2f -- most shoppers already qualify by default, so it isn\'t pushing anyone to add more.', $threshold, $aov),
                    'status' => 'open',
                    'fix_type' => 'shipping_bar',
                ]);

                return 70;
            }

            return 100;
        } catch (\Throwable $e) {
            Log::warning('Shipping threshold check failed', ['shop' => $shop->domain, 'error' => $e->getMessage()]);

            return null;
        }
    }

    protected function rebuildRecommendations(Shop $shop, Audit $audit): void
    {
        $severityWeight = ['high' => 3, 'medium' => 2, 'low' => 1];

        Recommendation::query()->where('shop_id', $shop->id)->where('status', 'open')->delete();

        foreach ($audit->issues as $issue) {
            if (! $issue->fix_type) {
                continue;
            }

            Recommendation::query()->create([
                'shop_id' => $shop->id,
                'issue_code' => $issue->code,
                'priority' => $severityWeight[$issue->severity] ?? 1,
                'impact' => $issue->severity,
                'effort' => 'low',
                'preset' => ['fix_type' => $issue->fix_type, 'title' => $issue->title],
                'status' => 'open',
            ]);
        }
    }
}
