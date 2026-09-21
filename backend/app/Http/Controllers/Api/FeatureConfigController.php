<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FeatureConfig;
use App\Services\PlanGateService;
use Illuminate\Http\Request;

class FeatureConfigController extends Controller
{
    public function __construct(protected PlanGateService $planGate)
    {
    }

    public function index(Request $request)
    {
        $shop = $request->attributes->get('shop');

        $query = FeatureConfig::query()->where('shop_id', $shop->id);

        if ($type = $request->query('type')) {
            $query->where('type', $type);
        }

        return response()->json($query->orderByDesc('id')->get());
    }

    public function show(Request $request, FeatureConfig $featureConfig)
    {
        $this->authorizeShop($request, $featureConfig);

        return response()->json($featureConfig);
    }

    /**
     * F-04 One-Click Fix, step 1-2: create a draft configuration with
     * preset values. Drafts never count against the Starter plan limit.
     */
    public function store(Request $request)
    {
        $shop = $request->attributes->get('shop');

        $data = $request->validate([
            'type' => ['required', 'string'],
            'name' => ['nullable', 'string'],
            'settings' => ['nullable', 'array'],
            'targeting' => ['nullable', 'array'],
        ]);

        if (! $this->planGate->canAccessModule($shop, $data['type'])) {
            return response()->json([
                'message' => "You've reached your Starter plan limit. Upgrade to Pro to unlock unlimited tools, funnels and checkout extensions.",
                'upgrade_required' => true,
            ], 403);
        }

        $config = FeatureConfig::query()->create([
            'shop_id' => $shop->id,
            'type' => $data['type'],
            'name' => $data['name'] ?? null,
            'status' => 'draft',
            'settings' => $data['settings'] ?? [],
            'targeting' => $data['targeting'] ?? [],
        ]);

        return response()->json($config, 201);
    }

    public function update(Request $request, FeatureConfig $featureConfig)
    {
        $this->authorizeShop($request, $featureConfig);

        $data = $request->validate([
            'name' => ['nullable', 'string'],
            'settings' => ['nullable', 'array'],
            'targeting' => ['nullable', 'array'],
        ]);

        $featureConfig->update($data);

        return response()->json($featureConfig);
    }

    /**
     * F-04 step 4: Activate. Server checks plan limits before writing the
     * live configuration; upgrade prompt fires if the Starter cap is hit.
     */
    public function activate(Request $request, FeatureConfig $featureConfig)
    {
        $shop = $request->attributes->get('shop');
        $this->authorizeShop($request, $featureConfig);

        if ($featureConfig->status === 'active') {
            return response()->json($featureConfig);
        }

        if (! $this->planGate->canActivateFeature($shop, $featureConfig->type)) {
            return response()->json([
                'message' => "You've reached your Starter plan limit. Upgrade to Pro to unlock unlimited tools, funnels and checkout extensions.",
                'upgrade_required' => true,
            ], 403);
        }

        $featureConfig->update(['status' => 'active']);
        $this->planGate->incrementUsage($shop, $featureConfig->type);

        return response()->json($featureConfig);
    }

    public function deactivate(Request $request, FeatureConfig $featureConfig)
    {
        $shop = $request->attributes->get('shop');
        $this->authorizeShop($request, $featureConfig);

        if ($featureConfig->status === 'active') {
            $this->planGate->decrementUsage($shop, $featureConfig->type);
        }

        $featureConfig->update(['status' => 'paused']);

        return response()->json($featureConfig);
    }

    public function destroy(Request $request, FeatureConfig $featureConfig)
    {
        $shop = $request->attributes->get('shop');
        $this->authorizeShop($request, $featureConfig);

        if ($featureConfig->status === 'active') {
            $this->planGate->decrementUsage($shop, $featureConfig->type);
        }

        $featureConfig->delete();

        return response()->noContent();
    }

    protected function authorizeShop(Request $request, FeatureConfig $featureConfig): void
    {
        $shop = $request->attributes->get('shop');

        abort_unless($featureConfig->shop_id === $shop->id, 404);
    }
}
