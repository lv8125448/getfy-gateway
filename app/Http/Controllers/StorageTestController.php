<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Support\PlatformConfigContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;

class StorageTestController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $provider = $request->input('storage_provider', 'local');

        if ($provider === 'local') {
            return response()->json([
                'success' => true,
                'message' => 'Storage local está ativo.',
            ]);
        }

        $cloudMode = (bool) config('getfy.cloud_mode', false);
        $r2EnvKey = (string) env('R2_ACCESS_KEY_ID', '');
        $r2EnvSecret = (string) env('R2_SECRET_ACCESS_KEY', '');
        $r2EnvBucket = (string) env('R2_BUCKET', '');
        $r2EnvEndpoint = (string) env('R2_ENDPOINT', '');
        $r2EnvConfigured = $r2EnvKey !== '' && $r2EnvSecret !== '' && $r2EnvBucket !== '' && $r2EnvEndpoint !== '';

        $keyInput = (string) $request->input('storage_s3_key', '');
        $bucketInput = (string) $request->input('storage_s3_bucket', '');
        $endpointInput = (string) $request->input('storage_s3_endpoint', '');

        $useEnvR2 = $cloudMode
            && $provider === 'r2'
            && $r2EnvConfigured
            && trim($keyInput) === ''
            && trim($bucketInput) === ''
            && trim($endpointInput) === '';

        if (! $useEnvR2) {
            $request->validate([
                'storage_provider' => ['required', 'string', 'in:s3,wasabi,r2'],
                'storage_s3_key' => ['required', 'string', 'max:255'],
                'storage_s3_secret' => ['nullable', 'string', 'max:512'],
                'storage_s3_bucket' => ['required', 'string', 'max:255'],
                'storage_s3_region' => ['nullable', 'string', 'max:64'],
                'storage_s3_endpoint' => ['nullable', 'string', 'max:512'],
            ], [
                'storage_provider.required' => 'Selecione um provedor de storage (S3, Wasabi ou R2).',
                'storage_provider.in' => 'Provedor inválido. Use S3, Wasabi ou R2.',
                'storage_s3_key.required' => 'O campo Access Key é obrigatório.',
                'storage_s3_bucket.required' => 'O campo Bucket é obrigatório.',
            ]);
        }

        $tenantId = PlatformConfigContext::settingsTenantId();
        $key = $useEnvR2 ? $r2EnvKey : $request->input('storage_s3_key');
        $secret = $useEnvR2 ? $r2EnvSecret : $request->input('storage_s3_secret');
        if ($secret === null || $secret === '') {
            $secretRaw = Setting::get('storage_s3_secret', '', $tenantId);
            if ($secretRaw !== '') {
                try {
                    $secret = Crypt::decryptString($secretRaw);
                } catch (\Throwable) {
                    $secret = '';
                }
            }
        }
        if ($secret === '') {
            return response()->json([
                'success' => false,
                'message' => 'O campo Secret Key é obrigatório. Preencha e salve as configurações uma vez para que fique guardado.',
            ], 422);
        }
        $bucket = $useEnvR2 ? $r2EnvBucket : $request->input('storage_s3_bucket');
        $region = $provider === 'r2' ? 'auto' : $request->input('storage_s3_region', 'us-east-1');
        $endpoint = $useEnvR2 ? $r2EnvEndpoint : $request->input('storage_s3_endpoint', '');

        $isR2 = $endpoint && str_contains($endpoint, 'r2.cloudflarestorage.com');
        $regionForConfig = $isR2 ? 'auto' : ($region ?: 'us-east-1');

        $config = [
            'driver' => 's3',
            'key' => $key,
            'secret' => $secret,
            'region' => $regionForConfig,
            'bucket' => $bucket,
            'throw' => false,
            'report' => false,
        ];

        if ($endpoint) {
            $config['endpoint'] = $endpoint;
            $config['use_path_style_endpoint'] = str_contains($endpoint, 'r2.cloudflarestorage.com')
                || str_contains($endpoint, 'wasabisys.com')
                || str_contains($endpoint, 'digitaloceanspaces.com');
        }

        try {
            $disk = Storage::build($config);
            $disk->files('/');
            return response()->json([
                'success' => true,
                'message' => 'Conexão estabelecida com sucesso.',
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'error' => $e->getMessage(),
            ], 422);
        }
    }
}
