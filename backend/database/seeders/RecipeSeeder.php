<?php

namespace Database\Seeders;

use App\Models\Recipe;
use Illuminate\Database\Seeder;

/**
 * F-06 CRO Recipes -- the three goal-based bundles from spec section 4.1.
 * `items` lists the feature_configs a recipe creates as drafts; the admin
 * shows this list before the merchant approves (RecipeController::preview).
 */
class RecipeSeeder extends Seeder
{
    public function run(): void
    {
        $recipes = [
            [
                'key' => 'increase_aov',
                'goal' => 'Increase average order value',
                'items' => [
                    ['type' => 'shipping_bar', 'name' => 'Free Shipping Bar', 'settings' => ['threshold' => 50]],
                    ['type' => 'quantity_discount', 'name' => 'Quantity Discount', 'settings' => ['tiers' => [
                        ['minQuantity' => 2, 'percentage' => 10],
                        ['minQuantity' => 3, 'percentage' => 15],
                    ]]],
                    ['type' => 'bundle', 'name' => 'Bundle', 'settings' => []],
                ],
            ],
            [
                'key' => 'increase_conversion',
                'goal' => 'Increase product page conversion',
                'items' => [
                    ['type' => 'sticky_atc', 'name' => 'Sticky Add to Cart', 'settings' => ['position' => 'bottom']],
                    ['type' => 'trust_badges', 'name' => 'Trust Badges', 'settings' => []],
                    ['type' => 'faq', 'name' => 'Product FAQ', 'settings' => []],
                ],
            ],
            [
                'key' => 'launch_product',
                'goal' => 'Launch a new product',
                'items' => [
                    ['type' => 'faq', 'name' => 'Product FAQ', 'settings' => []],
                    ['type' => 'trust_badges', 'name' => 'Trust Badges', 'settings' => []],
                    ['type' => 'sticky_atc', 'name' => 'Sticky Add to Cart', 'settings' => ['position' => 'bottom']],
                    ['type' => 'free_gift', 'name' => 'Launch Offer', 'settings' => []],
                ],
            ],
        ];

        foreach ($recipes as $recipe) {
            Recipe::query()->updateOrCreate(['key' => $recipe['key']], [
                'goal' => $recipe['goal'],
                'items' => $recipe['items'],
            ]);
        }
    }
}
