<?php

namespace App\Http\Controllers;

use App\Services\PluginStoreService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PluginStoreController extends Controller
{
    /**
     * GET /gerenciar-plugins/store-plugin/{slug} - JSON detail from store API (for modal).
     */
    public function show(Request $request, string $slug): JsonResponse
    {
        $store = app(PluginStoreService::class);
        if (! $store->isConfigured()) {
            return response()->json(['message' => 'Not configured'], 404);
        }
        $data = $store->getPluginBySlug($slug);
        if (! $data || ! isset($data['data'])) {
            return response()->json(['message' => 'Plugin not found'], 404);
        }

        return response()->json($data);
    }
}
