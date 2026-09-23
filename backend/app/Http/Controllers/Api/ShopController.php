<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ShopController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $shop = $request->attributes->get('shop');

        return response()->json([
            'domain' => $shop->domain,
            'plan' => $shop->currentPlan(),
            'is_plus' => $shop->is_plus,
        ]);
    }
}
