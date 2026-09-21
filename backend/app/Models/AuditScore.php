<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditScore extends Model
{
    use HasFactory;

    protected $fillable = ['audit_id', 'area', 'score'];

    public function audit(): BelongsTo
    {
        return $this->belongsTo(Audit::class);
    }
}
