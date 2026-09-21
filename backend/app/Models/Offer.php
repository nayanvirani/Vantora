<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Offer extends Model
{
    use HasFactory;

    protected $fillable = [
        'shop_id',
        'surface',
        'trigger',
        'product_id',
        'discount',
        'timer_seconds',
        'chain_order',
        'status',
    ];

    protected $casts = [
        'trigger' => 'array',
        'discount' => 'array',
    ];

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }
}
