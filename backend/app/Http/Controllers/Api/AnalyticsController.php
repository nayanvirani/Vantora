<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AnalyticsDaily;
use App\Models\Audit;
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

        $response = ['range_days' => $days, 'totals' => $totals];

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
