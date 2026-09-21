<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Audit;
use App\Models\MonitoringRun;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    /**
     * F-05 Today's Actions dashboard: Health Score, top 3 actions, quick
     * stats, latest monitoring status.
     */
    public function show(Request $request)
    {
        $shop = $request->attributes->get('shop');

        $audit = Audit::query()
            ->where('shop_id', $shop->id)
            ->where('status', 'completed')
            ->latest('id')
            ->with(['scores', 'issues' => fn ($q) => $q->where('status', 'open')])
            ->first();

        $topActions = $audit
            ? $audit->issues
                ->sortBy(fn ($issue) => match ($issue->severity) {
                    'high' => 0, 'medium' => 1, default => 2,
                })
                ->take(3)
                ->values()
            : collect();

        $latestMonitoring = MonitoringRun::query()
            ->where('shop_id', $shop->id)
            ->latest('id')
            ->first();

        return response()->json([
            'health_score' => $audit?->score_total,
            'sub_scores' => $audit?->scores,
            'top_actions' => $topActions,
            'last_audit_at' => $audit?->finished_at,
            'monitoring' => $latestMonitoring,
        ]);
    }
}
