<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiJob extends Model
{
    use HasFactory;

    protected $fillable = ['shop_id', 'product_id', 'status', 'input', 'output', 'tokens'];

    protected $casts = [
        'input' => 'array',
        'output' => 'array',
    ];

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }
}
