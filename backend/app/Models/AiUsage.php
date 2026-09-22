<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiUsage extends Model
{
    use HasFactory;

    // Eloquent's default table name for this class is 'ai_usages'
    // (pluralized), but the migration created 'ai_usage' (singular) --
    // confirmed live via a QueryException ("relation ai_usages does not
    // exist") the first time this model was actually queried in
    // production. Every Pro-plan AI usage check would have crashed.
    protected $table = 'ai_usage';

    protected $fillable = ['shop_id', 'period', 'count'];

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }
}
