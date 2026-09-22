<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class ShopController extends Controller
{
    public function show(Request $request)
    {
        $shop = $request->attributes->get('shop');

        return response()->json([
            'domain' => $shop->domain,
            'email' => $shop->email,
            'shopify_plan' => $shop->shopify_plan,
            'is_plus' => $shop->is_plus,
            'plan' => $shop->currentPlan(),
        ]);
    }
}
