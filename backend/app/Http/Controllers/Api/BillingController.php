<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Shopify\BillingService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class BillingController extends Controller
{
    public function subscribe(Request $request, BillingService $billing)
    {
        $shop = $request->attributes->get('shop');

        $data = $request->validate([
            'plan' => ['required', Rule::in(['starter', 'pro'])],
        ]);

        $returnUrl = config('app.url') . "/?shop={$shop->domain}&billing=confirmed";

        $result = $billing->startSubscription($shop, $data['plan'], $returnUrl);

        return response()->json([
            'confirmation_url' => $result['confirmationUrl'] ?? null,
        ]);
    }
}
