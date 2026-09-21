<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FeatureConfig;
use App\Models\Recipe;
use App\Models\RecipeRun;
use App\Services\FeatureActivationService;
use App\Services\PlanGateService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RecipeController extends Controller
{
    public function __construct(protected PlanGateService $planGate, protected FeatureActivationService $activation)
    {
    }

    public function index()
    {
        return response()->json(Recipe::query()->orderBy('key')->get());
    }

    /**
     * F-06 step 2: show what the recipe will create, flagging items the
     * Starter plan can't activate (already at its 1-per-feature cap, or a
     * Pro-only module) so the merchant sees the limit before approving.
     */
    public function preview(Request $request, string $key)
    {
        $shop = $request->attributes->get('shop');
        $recipe = Recipe::query()->where('key', $key)->firstOrFail();

        $items = collect($recipe->items)->map(fn ($item) => [
            ...$item,
            'allowed' => $this->planGate->canActivateFeature($shop, $item['type']),
        ]);

        return response()->json([
            'recipe' => $recipe,
            'items' => $items,
            'blocked_count' => $items->where('allowed', false)->count(),
        ]);
    }

    /**
     * F-06 step 3: create every item as a draft, then activate the ones
     * the plan allows. Items over the Starter cap stay as unactivated
     * drafts (visible on the Tools screen) rather than being dropped.
     */
    public function apply(Request $request, string $key)
    {
        $shop = $request->attributes->get('shop');
        $recipe = Recipe::query()->where('key', $key)->firstOrFail();

        $createdIds = [];
        $skipped = [];

        DB::transaction(function () use ($shop, $recipe, &$createdIds, &$skipped) {
            foreach ($recipe->items as $item) {
                $config = FeatureConfig::query()->create([
                    'shop_id' => $shop->id,
                    'type' => $item['type'],
                    'name' => $item['name'] ?? null,
                    'status' => 'draft',
                    'settings' => $item['settings'] ?? [],
                ]);

                $createdIds[] = $config->id;

                $result = $this->activation->activate($shop, $config);
                if (! $result['ok']) {
                    $skipped[] = ['feature_config_id' => $config->id, 'type' => $item['type'], 'reason' => $result['message'] ?? null];
                }
            }
        });

        $run = RecipeRun::query()->create([
            'shop_id' => $shop->id,
            'recipe_key' => $key,
            'status' => empty($skipped) ? 'applied' : 'partially_applied',
            'created_ids' => $createdIds,
        ]);

        return response()->json([
            'recipe_run' => $run,
            'skipped' => $skipped,
        ], 201);
    }
}
