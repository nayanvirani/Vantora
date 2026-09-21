<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SurveyResponse extends Model
{
    use HasFactory;

    protected $fillable = ['shop_id', 'order_id', 'question_id', 'answer'];

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }
}
