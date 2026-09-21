<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AnalyticsDaily extends Model
{
    use HasFactory;

    protected $table = 'analytics_daily';

    protected $fillable = [
        'shop_id',
        'config_id',
        'date',
        'impressions',
        'clicks',
        'orders',
        'revenue',
    ];

    protected $casts = [
        'date' => 'date',
    ];

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function config(): BelongsTo
    {
        return $this->belongsTo(FeatureConfig::class, 'config_id');
    }
}
