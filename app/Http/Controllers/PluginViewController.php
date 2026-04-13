<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PluginViewController extends Controller
{
    /**
     * Render an Inertia page for a plugin. Slug and page are derived from the request path
     * when the route is registered under the plugin's prefix (e.g. /example/dashboard).
     */
    public function show(Request $request): Response
    {
        $slug = $request->segment(1);
        $page = $request->segment(2) ?? 'Index';

        $componentName = 'Plugin/'.ucfirst($slug).'/'.ucfirst($page);

        return Inertia::render($componentName, [
            'pluginSlug' => $slug,
            'pluginPage' => $page,
        ]);
    }
}
