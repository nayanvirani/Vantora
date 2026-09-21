<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RecipeRun extends Model
{
    use HasFactory;

    protected $fillable = ['shop_id', 'recipe_key', 'status', 'created_ids'];

    protected $casts = [
        'created_ids' => 'array',
    ];

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }
}
