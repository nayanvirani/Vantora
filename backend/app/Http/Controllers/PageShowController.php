<?php

namespace App\Http\Controllers;

use App\Models\AppSetting;
use App\Models\Page;
use Illuminate\View\View;

class PageShowController extends Controller
{
    public function show(string $slug): View
    {
        $page = Page::where('slug', $slug)->where('is_published', true)->firstOrFail();

        $page->content = str_replace(
            '{{SUPPORT_EMAIL}}',
            AppSetting::current()->support_email ?? '',
            (string) $page->content
        );

        return view('pages.show', ['page' => $page]);
    }
}
