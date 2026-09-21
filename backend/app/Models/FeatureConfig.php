<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FeatureConfig extends Model
{
    use HasFactory;

    protected $fillable = [
        'shop_id',
        'type',
        'name',
        'status',
        'settings',
        'targeting',
        'shopify_ref_ids',
    ];

    protected $casts = [
        'settings' => 'array',
        'targeting' => 'array',
        'shopify_ref_ids' => 'array',
    ];

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function bundles(): HasMany
    {
        return $this->hasMany(Bundle::class, 'config_id');
    }
}
