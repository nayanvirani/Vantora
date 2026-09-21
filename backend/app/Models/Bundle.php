<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Bundle extends Model
{
    use HasFactory;

    protected $fillable = ['config_id', 'mode', 'discount_type', 'discount_value'];

    public function config(): BelongsTo
    {
        return $this->belongsTo(FeatureConfig::class, 'config_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(BundleItem::class);
    }
}
