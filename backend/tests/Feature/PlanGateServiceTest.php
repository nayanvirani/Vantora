<?php

namespace Tests\Feature;

use App\Models\AiJob;
use App\Models\AiUsage;
use App\Models\Shop;
use App\Models\Subscription;
use App\Services\PlanGateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlanGateServiceTest extends TestCase
{
    use RefreshDatabase;

    protected PlanGateService $planGate;

    protected function setUp(): void
    {
        parent::setUp();
        $this->planGate = app(PlanGateService::class);
    }

    protected function makeShop(?string $plan = null): Shop
    {
        $shop = Shop::create([
            'domain' => 'test-' . uniqid() . '.myshopify.com',
            'installed_at' => now(),
        ]);

        if ($plan) {
            Subscription::create([
                'shop_id' => $shop->id,
                'plan' => $plan,
                'status' => 'active',
            ]);
        }

        return $shop;
    }

    public function test_starter_shop_can_activate_first_configuration_of_a_gated_feature(): void
    {
        $shop = $this->makeShop();

        $this->assertTrue($this->planGate->canActivateFeature($shop, 'sticky_atc'));
    }

    public function test_starter_shop_cannot_activate_second_configuration_of_a_gated_feature(): void
    {
        $shop = $this->makeShop();

        $this->assertTrue($this->planGate->canActivateFeature($shop, 'sticky_atc'));
        $this->planGate->incrementUsage($shop, 'sticky_atc');

        $this->assertFalse($this->planGate->canActivateFeature($shop, 'sticky_atc'));
    }

    public function test_pro_shop_has_unlimited_configurations_of_a_gated_feature(): void
    {
        $shop = $this->makeShop('pro');

        for ($i = 0; $i < 5; $i++) {
            $this->assertTrue($this->planGate->canActivateFeature($shop, 'sticky_atc'));
            $this->planGate->incrementUsage($shop, 'sticky_atc');
        }
    }

    public function test_decrementing_usage_frees_up_the_starter_slot_again(): void
    {
        $shop = $this->makeShop();

        $this->planGate->incrementUsage($shop, 'trust_badges');
        $this->assertFalse($this->planGate->canActivateFeature($shop, 'trust_badges'));

        $this->planGate->decrementUsage($shop, 'trust_badges');
        $this->assertTrue($this->planGate->canActivateFeature($shop, 'trust_badges'));
    }

    public function test_decrementing_usage_never_goes_below_zero(): void
    {
        $shop = $this->makeShop();

        $this->planGate->decrementUsage($shop, 'trust_badges');

        $this->assertSame(0, $this->planGate->activeCount($shop, 'trust_badges'));
    }

    public function test_starter_shop_cannot_access_pro_only_module(): void
    {
        $shop = $this->makeShop();

        $this->assertFalse($this->planGate->canAccessModule($shop, 'cart_upsell'));
        $this->assertFalse($this->planGate->canActivateFeature($shop, 'cart_upsell'));
    }

    public function test_pro_shop_can_access_pro_only_module(): void
    {
        $shop = $this->makeShop('pro');

        $this->assertTrue($this->planGate->canAccessModule($shop, 'cart_upsell'));
        $this->assertTrue($this->planGate->canActivateFeature($shop, 'cart_upsell'));
    }

    public function test_non_gated_feature_type_is_unrestricted_on_starter(): void
    {
        $shop = $this->makeShop();

        // 'faq' is gated per config/shopify.php, but an arbitrary unlisted
        // type should never be blocked -- the gate is an allowlist of
        // restrictions, not a denylist.
        $this->assertTrue($this->planGate->canActivateFeature($shop, 'some_future_ungated_type'));
    }

    public function test_starter_ai_usage_is_capped_at_one_completed_job_total(): void
    {
        $shop = $this->makeShop();

        $this->assertSame(1, $this->planGate->aiUsageRemaining($shop));

        AiJob::create([
            'shop_id' => $shop->id,
            'product_id' => 'gid://shopify/Product/1',
            'status' => 'completed',
        ]);

        $this->assertSame(0, $this->planGate->aiUsageRemaining($shop));
    }

    public function test_pro_ai_usage_follows_the_monthly_fair_use_cap(): void
    {
        $shop = $this->makeShop('pro');

        $this->assertSame(
            config('shopify.ai_fair_use_cap_per_month'),
            $this->planGate->aiUsageRemaining($shop)
        );

        AiUsage::create([
            'shop_id' => $shop->id,
            'period' => now()->format('Y-m'),
            'count' => 90,
        ]);

        $this->assertSame(10, $this->planGate->aiUsageRemaining($shop));
    }

    public function test_downgrade_from_pro_to_starter_reapplies_the_gate(): void
    {
        $shop = $this->makeShop('pro');

        $this->planGate->incrementUsage($shop, 'sticky_atc');
        $this->planGate->incrementUsage($shop, 'sticky_atc');
        $this->assertSame(2, $this->planGate->activeCount($shop, 'sticky_atc'));

        // Simulate the subscription webhook flipping the plan back to Starter.
        $shop->subscription->update(['status' => 'cancelled']);
        $shop->refresh();

        $this->assertSame('starter', $shop->currentPlan());
        // The gate now blocks new activations even though 2 are already
        // active -- enforcing the cap at *activation* time, matching spec
        // section 10 ("activating the second is blocked"), not by force-
        // deactivating existing configs (that's a separate downgrade job).
        $this->assertFalse($this->planGate->canActivateFeature($shop, 'sticky_atc'));
    }
}
