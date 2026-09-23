<?php

namespace App\Http\Controllers;

use App\Models\Page;
use Illuminate\View\View;

class PageShowController extends Controller
{
    public function show(string $slug): View
    {
        $page = Page::where('slug', $slug)->where('is_published', true)->firstOrFail();

        return view('pages.show', ['page' => $page]);
    }
}
