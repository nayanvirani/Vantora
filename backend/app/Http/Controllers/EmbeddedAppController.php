<?php

namespace App\Http\Controllers;

class EmbeddedAppController extends Controller
{
    /**
     * Serves the embedded admin SPA shell (Polaris + App Bridge). React
     * mounts into #root; Vite/Laravel handles the built asset URLs.
     */
    public function show()
    {
        return view('app');
    }
}
