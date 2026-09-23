<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Shop;
use App\Models\Subscription;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $totalShops = Shop::count();
        $activeShops = Shop::whereNull('uninstalled_at')->whereNull('admin_paused_at')->count();
        $pausedShops = Shop::whereNotNull('admin_paused_at')->count();
        $proShops = Subscription::where('status', 'active')->where('plan', 'pro')->distinct('shop_id')->count('shop_id');

        $recentShops = Shop::query()
            ->with(['subscriptions' => fn ($query) => $query->where('status', 'active')->latest('id')])
            ->latest('installed_at')
            ->limit(8)
            ->get();

        return view('admin.dashboard', [
            'totalShops' => $totalShops,
            'activeShops' => $activeShops,
            'pausedShops' => $pausedShops,
            'proShops' => $proShops,
            'recentShops' => $recentShops,
        ]);
    }
}
