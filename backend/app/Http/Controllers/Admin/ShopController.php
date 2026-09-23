<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Shop;
use App\Models\Subscription;
use App\Services\Shopify\BillingService;
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
    public function __construct(private readonly BillingService $billing) {}

    public function index(Request $request): View
    {
        $shops = Shop::query()
            ->with(['subscriptions' => fn ($query) => $query->where('status', 'active')->latest('id')])
            ->when($request->filled('q'), fn ($query) => $query->where('domain', 'like', '%'.$request->string('q').'%'))
            ->when($request->filled('status'), function ($query) use ($request) {
                match ($request->string('status')->value()) {
                    'paused' => $query->whereNotNull('admin_paused_at'),
                    'uninstalled' => $query->whereNotNull('uninstalled_at'),
                    'active' => $query->whereNull('admin_paused_at')->whereNull('uninstalled_at'),
                    default => null,
                };
            })
            ->when($request->filled('plan'), fn ($query) => $query->whereHas(
                'subscriptions',
                fn ($sub) => $sub->where('status', 'active')->where('plan', $request->string('plan'))
            ))
            ->orderByDesc('installed_at')
            ->paginate(25)
            ->withQueryString();

        return view('admin.shops.index', ['shops' => $shops, 'filters' => $request->only(['q', 'status', 'plan'])]);
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

    /**
     * Manual safety net for the rare case app_subscriptions/update was
     * missed or delayed -- re-queries Shopify's own record of the shop's
     * active subscription (GraphQL currentAppInstallation.activeSubscriptions)
     * rather than trusting only what our webhook history says happened.
     */
    public function resyncPlan(Shop $shop): RedirectResponse
    {
        $changed = $this->billing->syncActivePlanViaApi($shop);

        return back()->with('status', $changed
            ? "Resynced {$shop->domain} -- local plan was out of date, now matches Shopify."
            : "Resynced {$shop->domain} -- already matched Shopify, no change.");
    }
}
