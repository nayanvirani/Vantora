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
 * v1 rule set: whether the shop has an *active* configuration of each core
 * conversion tool, plus a theme (Online Store 2.0) compatibility check.
 * Each missing/inactive tool becomes a scored issue with a linked one-click
 * fix. Deeper theme-content scanning (actual sticky-CTA/trust markup
 * detection, cart AOV vs shipping threshold) is a Phase 2 refinement --
 * tracked in ROADMAP.md.
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

            $areaScores['mobile'] = $this->themeCompatibilityScore($shop, $audit);

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
