<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditIssue extends Model
{
    use HasFactory;

    protected $fillable = [
        'audit_id',
        'code',
        'severity',
        'area',
        'title',
        'explanation',
        'status',
        'fix_type',
    ];

    public function audit(): BelongsTo
    {
        return $this->belongsTo(Audit::class);
    }
}
