<?php

namespace Database\Factories;

use App\Models\Shop;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Shop>
 */
class ShopFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'domain' => $this->faker->unique()->domainWord().'.myshopify.com',
            'access_token' => 'shpat_'.$this->faker->sha256(),
            'is_plus' => false,
            'installed_at' => now(),
        ];
    }
}
