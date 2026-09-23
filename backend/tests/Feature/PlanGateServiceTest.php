<?php

namespace Tests\Feature;

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
        $shop = Shop::factory()->create();

        if ($plan) {
            Subscription::create([
                'shop_id' => $shop->id,
                'plan' => $plan,
                'status' => 'active',
            ]);
        }

        return $shop;
    }

    // Sticky ATC and Shipping Bar: capped at 1 on BOTH Starter and Pro
    // (spec section 3 pricing matrix) -- the bug this rebuild set out to
    // fix, so these two types get explicit both-plan coverage.
    public function test_sticky_atc_is_capped_at_one_on_starter(): void
    {
        $shop = $this->makeShop();

        $this->assertTrue($this->planGate->canActivateFeature($shop, 'sticky_atc'));
        $this->planGate->incrementUsage($shop, 'sticky_atc');
        $this->assertFalse($this->planGate->canActivateFeature($shop, 'sticky_atc'));
    }

    public function test_sticky_atc_is_capped_at_one_on_pro_too(): void
    {
        $shop = $this->makeShop('pro');

        $this->assertTrue($this->planGate->canActivateFeature($shop, 'sticky_atc'));
        $this->planGate->incrementUsage($shop, 'sticky_atc');
        $this->assertFalse($this->planGate->canActivateFeature($shop, 'sticky_atc'));
    }

    public function test_shipping_bar_is_capped_at_one_on_pro_too(): void
    {
        $shop = $this->makeShop('pro');

        $this->assertTrue($this->planGate->canActivateFeature($shop, 'shipping_bar'));
        $this->planGate->incrementUsage($shop, 'shipping_bar');
        $this->assertFalse($this->planGate->canActivateFeature($shop, 'shipping_bar'));
    }

    // Trust Badges and FAQ: capped at 1 on Starter, unlimited on Pro.
    public function test_trust_badges_is_capped_at_one_on_starter(): void
    {
        $shop = $this->makeShop();

        $this->assertTrue($this->planGate->canActivateFeature($shop, 'trust_badges'));
        $this->planGate->incrementUsage($shop, 'trust_badges');
        $this->assertFalse($this->planGate->canActivateFeature($shop, 'trust_badges'));
    }

    public function test_trust_badges_is_unlimited_on_pro(): void
    {
        $shop = $this->makeShop('pro');

        for ($i = 0; $i < 5; $i++) {
            $this->assertTrue($this->planGate->canActivateFeature($shop, 'trust_badges'));
            $this->planGate->incrementUsage($shop, 'trust_badges');
        }
    }

    public function test_faq_is_capped_at_one_on_starter_and_unlimited_on_pro(): void
    {
        $starter = $this->makeShop();
        $pro = $this->makeShop('pro');

        $this->planGate->incrementUsage($starter, 'faq');
        $this->assertFalse($this->planGate->canActivateFeature($starter, 'faq'));

        for ($i = 0; $i < 3; $i++) {
            $this->planGate->incrementUsage($pro, 'faq');
        }
        $this->assertTrue($this->planGate->canActivateFeature($pro, 'faq'));
    }

    public function test_decrementing_usage_frees_up_the_slot_again(): void
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

    public function test_an_ungated_feature_type_is_unrestricted(): void
    {
        $shop = $this->makeShop();

        $this->assertTrue($this->planGate->canActivateFeature($shop, 'some_future_type'));
    }

    public function test_admin_paused_shop_cannot_activate_anything_regardless_of_limit(): void
    {
        $shop = $this->makeShop('pro');
        $shop->update(['admin_paused_at' => now()]);

        $this->assertFalse($this->planGate->canActivateFeature($shop, 'trust_badges'));
    }

    public function test_shop_with_no_subscription_defaults_to_starter_limits(): void
    {
        $shop = $this->makeShop();

        $this->assertSame('starter', $shop->currentPlan());
        $this->assertSame(1, $this->planGate->limitFor($shop, 'sticky_atc'));
    }
}
