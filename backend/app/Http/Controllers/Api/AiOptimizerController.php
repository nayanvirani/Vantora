<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\RunAiOptimizationJob;
use App\Models\AiJob;
use App\Services\PlanGateService;
use App\Services\Shopify\ShopifyApiClient;
use Illuminate\Http\Request;

class AiOptimizerController extends Controller
{
    public function __construct(protected PlanGateService $planGate)
    {
    }

    public function usage(Request $request)
    {
        $shop = $request->attributes->get('shop');

        return response()->json([
            'plan' => $shop->currentPlan(),
            'remaining' => $this->planGate->aiUsageRemaining($shop),
            'cap' => $shop->currentPlan() === 'starter' ? 1 : config('shopify.ai_fair_use_cap_per_month'),
        ]);
    }

    public function index(Request $request)
    {
        $shop = $request->attributes->get('shop');

        return response()->json(
            AiJob::query()->where('shop_id', $shop->id)->latest('id')->paginate(20)
        );
    }

    /**
     * F-19 step 1: queue a generation job for one product. Starter is
     * capped at 1 product lifetime, Pro at the monthly fair-use cap
     * (config('shopify.ai_fair_use_cap_per_month')), enforced here before
     * the job is even queued so it never burns quota on a rejected request.
     */
    public function store(Request $request)
    {
        $shop = $request->attributes->get('shop');

        $data = $request->validate([
            'product_id' => ['required', 'string'],
        ]);

        if ($this->planGate->aiUsageRemaining($shop) <= 0) {
            return response()->json([
                'message' => "You've reached your Starter plan limit. Upgrade to Pro to unlock unlimited tools, funnels and checkout extensions.",
                'upgrade_required' => true,
            ], 403);
        }

        $product = (new ShopifyApiClient($shop))->graphql(<<<'GQL'
            query product($id: ID!) {
                product(id: $id) { title description productType }
            }
            GQL, ['id' => $data['product_id']])->json('data.product');

        $job = AiJob::query()->create([
            'shop_id' => $shop->id,
            'product_id' => $data['product_id'],
            'status' => 'queued',
            'input' => $product,
        ]);

        RunAiOptimizationJob::dispatch($job->id);

        return response()->json($job, 202);
    }

    public function show(Request $request, AiJob $aiJob)
    {
        $shop = $request->attributes->get('shop');
        abort_unless($aiJob->shop_id === $shop->id, 404);

        return response()->json($aiJob);
    }

    /**
     * F-19 step 2: merchant approves suggested copy, which writes it to the
     * real product via the Admin API. Nothing reaches Shopify before this.
     */
    public function approve(Request $request, AiJob $aiJob)
    {
        $shop = $request->attributes->get('shop');
        abort_unless($aiJob->shop_id === $shop->id, 404);
        abort_unless($aiJob->status === 'completed', 422, 'Job is not ready to approve.');

        $data = $request->validate([
            'title' => ['nullable', 'string'],
            'description' => ['nullable', 'string'],
        ]);

        (new ShopifyApiClient($shop))->graphql(<<<'GQL'
            mutation productUpdate($input: ProductInput!) {
                productUpdate(input: $input) {
                    userErrors { field message }
                }
            }
            GQL, [
            'input' => [
                'id' => $aiJob->product_id,
                'title' => $data['title'] ?? $aiJob->output['title'] ?? null,
                'descriptionHtml' => $data['description'] ?? $aiJob->output['description'] ?? null,
            ],
        ]);

        $aiJob->update(['status' => 'approved']);

        return response()->json($aiJob);
    }

    public function discard(Request $request, AiJob $aiJob)
    {
        $shop = $request->attributes->get('shop');
        abort_unless($aiJob->shop_id === $shop->id, 404);

        $aiJob->update(['status' => 'discarded']);

        return response()->json($aiJob);
    }
}
