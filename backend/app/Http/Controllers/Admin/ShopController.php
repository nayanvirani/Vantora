<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Shop;
use App\Models\Subscription;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Super-admin shop management -- view every installed shop, pause a
 * misbehaving one, or override its plan (comp Pro, extend a trial). Not a
 * merchant-facing surface: this is behind the 'admin' guard only.
 */
class ShopController extends Controller
{
    public function index(): View
    {
        $shops = Shop::query()
            ->with(['subscriptions' => fn ($query) => $query->where('status', 'active')->latest('id')])
            ->orderByDesc('installed_at')
            ->paginate(25);

        return view('admin.shops.index', ['shops' => $shops]);
    }

    public function show(Shop $shop): View
    {
        $shop->load(['subscriptions' => fn ($query) => $query->latest('id')]);

        return view('admin.shops.show', ['shop' => $shop]);
    }

    public function pause(Shop $shop): RedirectResponse
    {
        $shop->update(['admin_paused_at' => now()]);

        return back()->with('status', "Paused {$shop->domain}.");
    }

    public function unpause(Shop $shop): RedirectResponse
    {
        $shop->update(['admin_paused_at' => null]);

        return back()->with('status', "Unpaused {$shop->domain}.");
    }

    public function overridePlan(Request $request, Shop $shop): RedirectResponse
    {
        $data = $request->validate([
            'plan' => ['required', 'string', 'in:starter,pro'],
        ]);

        $current = $shop->subscriptions()->where('status', 'active')->latest('id')->first();
        $current?->update(['status' => 'superseded']);

        Subscription::create([
            'shop_id' => $shop->id,
            'plan' => $data['plan'],
            'status' => 'active',
            'overridden_by_admin' => true,
        ]);

        return back()->with('status', "Set {$shop->domain} to {$data['plan']} (admin override).");
    }
}
