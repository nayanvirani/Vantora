<?php

namespace App\Http\Controllers;

use App\Models\Page;
use App\Models\Plan;
use Illuminate\View\View;

class LandingController extends Controller
{
    public function index(): View
    {
        $plans = Plan::where('is_active', true)->orderBy('sort_order')->get();

        $footerPages = Page::where('is_published', true)
            ->whereIn('slug', ['privacy', 'terms', 'faq'])
            ->orderBy('sort_order')
            ->get();

        return view('landing', ['plans' => $plans, 'footerPages' => $footerPages]);
    }
}
