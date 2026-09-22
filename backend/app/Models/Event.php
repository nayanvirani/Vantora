<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Event extends Model
{
    use HasFactory;

    public $timestamps = true;

    protected $fillable = ['shop_id', 'config_id', 'type', 'session_ref', 'occurred_at', 'data'];

    protected $casts = [
        'occurred_at' => 'datetime',
        'data' => 'array',
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
