<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UsageCounter extends Model
{
    use HasFactory;

    protected $fillable = ['shop_id', 'feature_type', 'active_count'];

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }
}
