<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExtensionStatus extends Model
{
    use HasFactory;

    protected $fillable = ['shop_id', 'extension_key', 'eligible', 'reason', 'enabled'];

    protected $casts = [
        'eligible' => 'boolean',
        'enabled' => 'boolean',
    ];

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }
}
