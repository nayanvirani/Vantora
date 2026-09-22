<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AnalyticsDaily;
use App\Models\Audit;
use App\Models\Event;
use Illuminate\Http\Request;

class AnalyticsController extends Controller
{
    /**
     * F-21 Basic Analytics (all plans): totals over the range.
     * F-22 Advanced Analytics (Pro only): adds the per-feature breakdown.
     */
    public function index(Request $request)
    {
        $shop = $request->attributes->get('shop');

        $days = min(90, max(1, (int) $request->query('days', 30)));
        $since = now()->subDays($days)->toDateString();

        $rows = AnalyticsDaily::query()
            ->where('shop_id', $shop->id)
            ->where('date', '>=', $since)
            ->with('config:id,type,name')
            ->get();

        $totals = [
            'impressions' => $rows->sum('impressions'),
            'clicks' => $rows->sum('clicks'),
            'orders' => $rows->sum('orders'),
            'revenue' => round($rows->sum('revenue'), 2),
        ];

        $response = [
            'range_days' => $days,
            'totals' => $totals,
            'daily' => $rows
                ->groupBy('date')
                ->map(fn ($group, $date) => [
                    'date' => $date,
                    'impressions' => $group->sum('impressions'),
                    'clicks' => $group->sum('clicks'),
                    'orders' => $group->sum('orders'),
                ])
                ->sortBy('date')
                ->values(),
        ];

        if ($shop->currentPlan() === 'pro') {
            $response['by_feature'] = $rows
                ->groupBy(fn ($row) => $row->config?->type ?? 'unknown')
                ->map(fn ($group, $type) => [
                    'type' => $type,
                    'name' => $group->first()->config?->name,
                    'impressions' => $group->sum('impressions'),
                    'clicks' => $group->sum('clicks'),
                    'orders' => $group->sum('orders'),
                    'revenue' => round($group->sum('revenue'), 2),
                ])
                ->values();
        }

        return response()->json($response);
    }

    /**
     * Traffic/engagement view, modeled on what merchants expect from
     * Google Analytics / Microsoft Clarity: sessions, top pages, top
     * products, a mobile/desktop split, and a view -> cart -> checkout
     * funnel -- built from the raw events.data payload the pixel now
     * captures in full (page_viewed, product_viewed, etc.), not just the
     * per-feature impression/click counts F-21 already covered.
     *
     * Aggregates in PHP over a bounded window rather than SQL GROUP BY on
     * the JSON column -- fine at this scale (a v1, matching the rest of
     * the analytics layer), worth revisiting with real DB aggregation if
     * event volume grows large enough for this to matter.
     */
    public function traffic(Request $request)
    {
        $shop = $request->attributes->get('shop');

        $days = min(90, max(1, (int) $request->query('days', 30)));
        $since = now()->subDays($days);

        $events = Event::query()
            ->where('shop_id', $shop->id)
            ->where('occurred_at', '>=', $since)
            ->get(['type', 'session_ref', 'data']);

        $sessions = $events->pluck('session_ref')->filter()->unique()->count();
        $pageViews = $events->where('type', 'page_viewed');

        $topPages = $pageViews
            ->groupBy(fn ($e) => $e->data['path'] ?? 'unknown')
            ->map(fn ($group, $path) => ['path' => $path, 'views' => $group->count()])
            ->sortByDesc('views')
            ->take(10)
            ->values();

        $topProducts = $events->where('type', 'product_viewed')
            ->groupBy(fn ($e) => $e->data['product_id'] ?? 'unknown')
            ->map(fn ($group, $id) => [
                'product_id' => $id,
                'title' => $group->first()->data['product_title'] ?? 'Unknown product',
                'views' => $group->count(),
            ])
            ->sortByDesc('views')
            ->take(10)
            ->values();

        $withViewport = $pageViews->filter(fn ($e) => isset($e->data['viewport_width']));
        $mobile = $withViewport->filter(fn ($e) => ($e->data['viewport_width'] ?? 0) < 750)->count();

        return response()->json([
            'range_days' => $days,
            'sessions' => $sessions,
            'page_views' => $pageViews->count(),
            'top_pages' => $topPages,
            'top_products' => $topProducts,
            'device_split' => $withViewport->count() > 0
                ? ['mobile' => $mobile, 'desktop' => $withViewport->count() - $mobile]
                : null,
            'funnel' => [
                'product_viewed' => $events->where('type', 'product_viewed')->count(),
                'product_added_to_cart' => $events->where('type', 'product_added_to_cart')->count(),
                'checkout_started' => $events->where('type', 'checkout_started')->count(),
                'checkout_completed' => $events->where('type', 'checkout_completed')->count(),
            ],
        ]);
    }

    /**
     * F-07 Score History: line chart of Health Score over time. Starter
     * gets 30 days, Pro gets full history (spec section 4.1).
     */
    public function scoreHistory(Request $request)
    {
        $shop = $request->attributes->get('shop');

        $query = Audit::query()
            ->where('shop_id', $shop->id)
            ->where('status', 'completed')
            ->orderBy('finished_at')
            ->select('id', 'score_total', 'finished_at');

        if ($shop->currentPlan() !== 'pro') {
            $query->where('finished_at', '>=', now()->subDays(30));
        }

        return response()->json($query->get());
    }
}
