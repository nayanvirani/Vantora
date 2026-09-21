<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MonitoringRun extends Model
{
    use HasFactory;

    protected $fillable = ['shop_id', 'score_before', 'score_after', 'changes', 'notified_at'];

    protected $casts = [
        'changes' => 'array',
        'notified_at' => 'datetime',
    ];

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }
}
