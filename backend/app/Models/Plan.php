<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Display-only mirror of a Shopify Managed Pricing plan -- name, price
 * and feature bullets shown on the landing page and (eventually) the
 * embedded admin's billing screen. NEVER a source of truth for actual
 * gating: config('shopify.feature_limits') is the only thing
 * PlanGateService reads. Keeping these strictly separate on purpose --
 * a reference app's equivalent model blurred the two (one limit field
 * was actually enforced, a second looked enforced but wasn't, checked
 * nowhere in that codebase).
 */
class Plan extends Model
{
    /** @use HasFactory<\Database\Factories\PlanFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'name',
        'handle',
        'price',
        'features',
        'is_active',
        'sort_order',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'features' => 'array',
            'is_active' => 'boolean',
        ];
    }
}
