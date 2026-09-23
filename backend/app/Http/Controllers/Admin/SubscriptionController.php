<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Subscription;
use Illuminate\View\View;

/**
 * Read-only history -- every Subscription row ever created (webhook-
 * driven or admin plan-override), never overwritten in place, so this
 * is a genuine audit trail of plan changes across all shops.
 */
class SubscriptionController extends Controller
{
    public function index(): View
    {
        $subscriptions = Subscription::query()
            ->with('shop')
            ->latest('id')
            ->paginate(25);

        return view('admin.subscriptions.index', ['subscriptions' => $subscriptions]);
    }
}
