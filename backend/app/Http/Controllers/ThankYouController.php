<?php

namespace App\Http\Controllers;

use App\Models\Offer;
use App\Models\SurveyResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * F-34 Thank You Page Offers, F-35 Post-purchase Survey, F-37 Referral
 * backend, called by extensions/thank-you-blocks. Authenticated via
 * VerifyShopifyExtensionSessionToken (the checkout/thank-you surface does
 * expose shopify.sessionToken.get(), unlike the legacy post-purchase
 * extension) -- shop comes from the verified token, never a request field.
 *
 * UNTESTED: no dev store in this environment to verify the extension's
 * network calls actually reach these routes with the expected shape.
 */
class ThankYouController extends Controller
{
    public function data(Request $request)
    {
        $shop = $request->attributes->get('shop');

        if ($shop->currentPlan() !== 'pro') {
            return response()->json(null, 404);
        }

        $data = $request->validate([
            'order_id' => ['nullable', 'string'],
            'is_first_order' => ['nullable', 'boolean'],
        ]);

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
        $shop = $request->attributes->get('shop');

        $data = $request->validate([
            'order_id' => ['required', 'string'],
            'question_id' => ['nullable', 'string'],
            'answer' => ['required', 'string'],
        ]);

        SurveyResponse::query()->create([
            'shop_id' => $shop->id,
            'order_id' => $data['order_id'],
            'question_id' => $data['question_id'] ?? 'default',
            'answer' => $data['answer'],
        ]);

        return response()->json(['ok' => true]);
    }
}
