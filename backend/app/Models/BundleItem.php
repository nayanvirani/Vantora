<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BundleItem extends Model
{
    use HasFactory;

    protected $fillable = ['bundle_id', 'product_id', 'variant_id', 'qty'];

    public function bundle(): BelongsTo
    {
        return $this->belongsTo(Bundle::class);
    }
}
