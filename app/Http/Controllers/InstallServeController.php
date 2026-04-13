<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Serves the standalone installer when the server routes /install to Laravel.
 * Fallback for hosts that send all requests to index.php.
 */
class InstallServeController extends Controller
{
    public function __invoke(Request $request, ?string $path = null): Response|BinaryFileResponse
    {
        $installDir = public_path('install');
        if (! is_dir($installDir)) {
            $installDir = public_path('.install');
        }
        if (! is_dir($installDir)) {
            abort(404);
        }

        $path = $path ?? '';

        if ($path === 'api.php' || $path === 'api') {
            require $installDir . DIRECTORY_SEPARATOR . 'api.php';
            exit;
        }

        if ($path === 'install.js') {
            $file = $installDir . DIRECTORY_SEPARATOR . 'install.js';
            if (! file_exists($file)) {
                abort(404);
            }
            return response()->file($file, ['Content-Type' => 'application/javascript']);
        }

        if ($path === '' || $path === null || preg_match('/^steps?\/step\d+\.php$/i', $path)) {
            ob_start();
            try {
                $_GET['step'] = $_GET['step'] ?? $_POST['step'] ?? 1;
                require $installDir . DIRECTORY_SEPARATOR . 'index.php';
                return response(ob_get_clean());
            } catch (\Throwable $e) {
                ob_end_clean();
                throw $e;
            }
        }

        abort(404);
    }
}
