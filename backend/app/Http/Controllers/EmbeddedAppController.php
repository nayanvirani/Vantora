<?php

namespace App\Http\Controllers;

class EmbeddedAppController extends Controller
{
    /**
     * Serves the built Polaris/App Bridge SPA. The admin/ Vite project is
     * built and copied into public/app/ during the Docker image build.
     */
    public function show()
    {
        $indexPath = public_path('app/index.html');

        if (! file_exists($indexPath)) {
            return response('Vantora admin UI not built yet. Run the admin/ build and copy it into public/app.', 200);
        }

        return response()->file($indexPath);
    }
}
