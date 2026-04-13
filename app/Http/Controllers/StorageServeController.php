<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Serve arquivos de storage/app/public em GET /storage/{path}.
 * Não usa symlink: ideal ao mudar de servidor (Windows/Linux).
 */
class StorageServeController extends Controller
{
    public function __invoke(Request $request, string $path): BinaryFileResponse
    {
        $path = rawurldecode($path);
        $path = str_replace(['../', '..\\'], '', $path);
        $path = ltrim($path, '/\\');
        if ($path === '' || preg_match('/\\.\\./', $path)) {
            abort(404);
        }

        $root = storage_path('app/public');
        $fullPath = $root . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
        $realRoot = realpath($root);
        $realFile = realpath($fullPath);

        if ($realRoot === false || $realFile === false) {
            abort(404);
        }
        $rootWithSep = rtrim($realRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        if (! is_file($realFile) || ! str_starts_with($realFile, $rootWithSep)) {
            abort(404);
        }

        $mime = match (strtolower(pathinfo($realFile, PATHINFO_EXTENSION))) {
            'png' => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'svg' => 'image/svg+xml',
            'ico' => 'image/x-icon',
            'pdf' => 'application/pdf',
            'css' => 'text/css',
            'js' => 'application/javascript',
            'woff2' => 'font/woff2',
            'woff' => 'font/woff',
            default => 'application/octet-stream',
        };

        return response()->file($realFile, [
            'Content-Type' => $mime,
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }
}
