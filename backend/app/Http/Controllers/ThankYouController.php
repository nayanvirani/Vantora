<?php

namespace App\Http\Controllers;

use App\Models\Offer;
use App\Models\Shop;
use App\Models\SurveyResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * F-34 Thank You Page Offers, F-35 Post-purchase Survey, F-37 Referral
 * backend, called directly by extensions/thank-you-blocks (sandboxed like
 * the pixel and post-purchase extension -- no session token available).
 *
 * UNTESTED: no dev store in this environment to verify the extension's
 * network calls actually reach these routes with the expected shape.
 */
class ThankYouController extends Controller
{
    public function data(Request $request)
    {
        $data = $request->validate([
            'shop' => ['required', 'string'],
            'order_id' => ['nullable', 'string'],
            'is_first_order' => ['nullable', 'boolean'],
        ]);

        $shop = Shop::query()->where('domain', $data['shop'])->whereNull('uninstalled_at')->first();
        if (! $shop || $shop->currentPlan() !== 'pro') {
            return response()->json(null, 404);
        }

        $crossSell = Offer::query()
            ->where('shop_id', $shop->id)
            ->where('surface', 'thank_you')
            ->where('status', 'active')
            ->first();

        return response()->json([
            'cross_sell' => $crossSell ? [
                'heading' => $crossSell->discount['heading'] ?? 'A gift for you',
                'description' => $crossSell->discount['description'] ?? null,
                'discount_code' => $crossSell->discount['next_order_code'] ?? null,
            ] : null,
            'survey' => $shop->featureConfigs()->where('type', 'post_purchase_survey')->where('status', 'active')->first()?->settings,
            'referral' => $shop->featureConfigs()->where('type', 'referral_reorder')->where('status', 'active')->exists() ? [
                'description' => 'Share your link and earn rewards when friends buy.',
                'link' => rtrim(config('app.url'), '/') . '/r/' . Str::random(8),
            ] : null,
        ]);
    }

    public function survey(Request $request)
    {
        $data = $request->validate([
            'shop' => ['required', 'string'],
            'order_id' => ['required', 'string'],
            'question_id' => ['nullable', 'string'],
            'answer' => ['required', 'string'],
        ]);

        $shop = Shop::query()->where('domain', $data['shop'])->whereNull('uninstalled_at')->first();
        if (! $shop) {
            return response()->json(['ok' => false], 404);
        }

        SurveyResponse::query()->create([
            'shop_id' => $shop->id,
            'order_id' => $data['order_id'],
            'question_id' => $data['question_id'] ?? 'default',
            'answer' => $data['answer'],
        ]);

        return response()->json(['ok' => true]);
    }
}
