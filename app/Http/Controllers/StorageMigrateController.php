<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Services\StorageService;
use App\Support\PlatformConfigContext;
use App\Services\StorageUrlNormalizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class StorageMigrateController extends Controller
{
    private const STREAM_THRESHOLD_BYTES = 5 * 1024 * 1024; // 5 MB

    private const MAX_ERRORS_RETURNED = 10;

    public function __invoke(Request $request): JsonResponse
    {
        $tenantId = PlatformConfigContext::settingsTenantId();
        $cloudMode = (bool) config('getfy.cloud_mode', false);
        $r2EnvKey = (string) env('R2_ACCESS_KEY_ID', '');
        $r2EnvSecret = (string) env('R2_SECRET_ACCESS_KEY', '');
        $r2EnvBucket = (string) env('R2_BUCKET', '');
        $r2EnvEndpoint = (string) env('R2_ENDPOINT', '');
        $r2EnvConfigured = $r2EnvKey !== '' && $r2EnvSecret !== '' && $r2EnvBucket !== '' && $r2EnvEndpoint !== '';

        $provider = Setting::get('storage_provider', null, $tenantId);
        if ($provider === null || $provider === '') {
            $provider = ($cloudMode && $r2EnvConfigured) ? 'r2' : 'local';
        }

        if ($provider === 'local' || $provider === '' || ! in_array($provider, ['s3', 'wasabi', 'r2'], true)) {
            return response()->json([
                'success' => false,
                'message' => 'Configure e salve o storage S3 ou R2 antes de usar a migração.',
            ], 422);
        }

        $storageService = new StorageService($tenantId);
        $destinationDisk = $storageService->disk();

        if ($storageService->isLocal()) {
            return response()->json([
                'success' => false,
                'message' => 'O storage atual ainda é o local. Salve as credenciais S3/R2 e tente novamente.',
            ], 422);
        }

        $localDisk = Storage::disk('public');
        $paths = $localDisk->allFiles('');

        set_time_limit(600);

        $transferred = 0;
        $failed = 0;
        $errors = [];

        foreach ($paths as $path) {
            try {
                $size = $localDisk->size($path);
                if ($size !== false && $size >= self::STREAM_THRESHOLD_BYTES) {
                    $stream = $localDisk->readStream($path);
                    if ($stream === false) {
                        throw new \RuntimeException('Falha ao abrir stream do arquivo.');
                    }
                    $written = $destinationDisk->writeStream($path, $stream);
                    if (is_resource($stream)) {
                        fclose($stream);
                    }
                    if (! $written) {
                        throw new \RuntimeException('Falha ao gravar stream no destino.');
                    }
                } else {
                    $content = $localDisk->get($path);
                    $destinationDisk->put($path, $content);
                }
                $transferred++;
            } catch (\Throwable $e) {
                $failed++;
                if (count($errors) < self::MAX_ERRORS_RETURNED) {
                    $errors[] = ['path' => $path, 'message' => $e->getMessage()];
                }
            }
        }

        $total = count($paths);
        $message = $failed === 0
            ? "{$transferred} arquivo(s) transferido(s) com sucesso."
            : "{$transferred} arquivo(s) transferido(s), {$failed} falha(s).";

        $normalized = (new StorageUrlNormalizer)->normalizeAll();
        if ($normalized['updated'] > 0) {
            $message .= ' ' . $normalized['updated'] . ' referência(s) no banco atualizada(s) para o novo storage.';
        }

        return response()->json([
            'success' => true,
            'transferred' => $transferred,
            'failed' => $failed,
            'total' => $total,
            'message' => $message,
            'errors' => $errors,
            'normalized' => $normalized['updated'],
            'normalized_details' => $normalized['details'],
        ]);
    }
}
