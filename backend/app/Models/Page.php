<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * General-purpose admin-editable content page (Privacy, Terms, FAQ, or
 * anything else) -- content is raw HTML, rendered inside a Tailwind
 * `prose` wrapper by resources/views/pages/show.blade.php. Only the
 * super admin can write this (no merchant/public input path), so no
 * HTML sanitization is applied before rendering.
 */
class Page extends Model
{
    /** @use HasFactory<\Database\Factories\PageFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'title',
        'slug',
        'content',
        'is_published',
        'sort_order',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_published' => 'boolean',
        ];
    }
}
