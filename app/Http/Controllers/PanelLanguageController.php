<?php

namespace App\Http\Controllers;

use App\Services\PlatformI18nService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PanelLanguageController extends Controller
{
    public function switch(Request $request, PlatformI18nService $i18n): JsonResponse
    {
        $validated = $request->validate([
            'locale' => ['required', 'string', 'max:20'],
        ]);

        $locale = $i18n->persistLocale($request, (string) $validated['locale']);

        return response()->json([
            'ok' => true,
            'locale' => $locale,
        ]);
    }
}
