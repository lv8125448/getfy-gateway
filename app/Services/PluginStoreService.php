<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class PluginStoreService
{
    protected string $baseUrl;

    protected ?string $apiKey;

    protected ?string $lastError = null;

    public function __construct()
    {
        $config = config('services.plugin_store', []);
        $this->baseUrl = rtrim($config['url'] ?? '', '/');
        $this->apiKey = $config['api_key'] ?? null;
    }

    public function isConfigured(): bool
    {
        return $this->baseUrl !== '';
    }

    public function getBaseUrl(): string
    {
        return $this->baseUrl;
    }

    public function getLastError(): ?string
    {
        return $this->lastError;
    }

    /**
     * @return array{data: array, error?: string}
     */
    public function listPlugins(?string $category = null, ?string $search = null): array
    {
        $this->lastError = null;
        if (! $this->isConfigured()) {
            return ['data' => []];
        }
        $url = $this->baseUrl . '/api/v1/plugins';
        $query = array_filter([
            'category' => $category,
            'search' => $search,
        ]);
        $response = $this->get($url, $query);
        if ($response === null) {
            $err = $this->lastError ?: 'Não foi possível conectar à loja.';

            return ['data' => [], 'error' => $err];
        }
        $decoded = is_array($response) ? $response : json_decode($response, true);
        if (! is_array($decoded) || ! isset($decoded['data'])) {
            return ['data' => [], 'error' => 'Resposta inválida da loja.'];
        }

        return ['data' => is_array($decoded['data']) ? $decoded['data'] : []];
    }

    /**
     * @return array{data: array{slug: string, name: string, short_description: string|null, description: string|null, banner_url: string|null, price: float, category: string|null, developer_name: string|null, developer_url: string|null, version: string}}|null
     */
    public function getPluginBySlug(string $slug): ?array
    {
        if (! $this->isConfigured()) {
            return null;
        }
        $url = $this->baseUrl . '/api/v1/plugins/' . $slug;
        $response = $this->get($url);
        if ($response === null) {
            return null;
        }
        $decoded = is_array($response) ? $response : json_decode($response, true);
        return is_array($decoded) ? $decoded : null;
    }

    /**
     * Request a short-lived download URL.
     * Free: no purchase_token. Paid: pass purchase_token from checkout return.
     *
     * @return array{download_url: string, expires_at: string}|null
     */
    public function requestDownloadUrl(string $slug, ?string $purchaseToken = null, ?string $platformId = null): ?array
    {
        if (! $this->isConfigured()) {
            return null;
        }
        $this->lastError = null;
        $url = $this->baseUrl . '/api/v1/plugins/' . $slug . '/request-download';
        $headers = [];
        if ($platformId !== null) {
            $headers['X-Platform-Id'] = $platformId;
        }
        if ($this->apiKey !== null) {
            $headers['Authorization'] = 'Bearer ' . $this->apiKey;
        }
        $body = $purchaseToken !== null ? ['purchase_token' => $purchaseToken] : [];
        $response = $this->post($url, $body, $headers);
        if ($response === null) {
            return null;
        }
        $decoded = is_array($response) ? $response : json_decode($response, true);
        if (! is_array($decoded) || ! isset($decoded['download_url'])) {
            $this->lastError = $this->lastError ?: 'Resposta da loja sem link de download.';

            return null;
        }
        return [
            'download_url' => $decoded['download_url'],
            'expires_at' => $decoded['expires_at'] ?? '',
        ];
    }

    public function getCheckoutUrl(string $slug, string $returnUrl): string
    {
        $base = $this->baseUrl ?: '';
        $params = http_build_query(['plugin' => $slug, 'return_url' => $returnUrl]);

        return $base . '/checkout?' . $params;
    }

    public function getSubmitPluginUrl(): ?string
    {
        return config('services.plugin_store.submit_url');
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>|string|null
     */
    private function get(string $url, array $query = []): array|string|null
    {
        $this->lastError = null;
        try {
            $hostHeader = parse_url($url, PHP_URL_HOST);
            $headers = array_merge($this->authHeaders(), $hostHeader ? ['Host' => $hostHeader] : []);
            $response = Http::timeout(10)
                ->connectTimeout(5)
                ->withHeaders($headers)
                ->get($url, $query);
            if (! $response->successful()) {
                $body = $response->json();
                $msg = is_array($body) && isset($body['error']) ? $body['error'] : null;
                $this->lastError = $msg ?: ('Loja retornou HTTP ' . $response->status() . '. URL: ' . $url);

                return null;
            }

            return $response->json() ?? $response->body();
        } catch (\Throwable $e) {
            $this->lastError = $e->getMessage();
            report($e);

            return null;
        }
    }

    /**
     * @param  array<string, mixed>  $body
     * @param  array<string, string>  $headers
     * @return array<string, mixed>|string|null
     */
    private function post(string $url, array $body, array $headers = []): array|string|null
    {
        $this->lastError = null;
        try {
            $req = Http::timeout(15)
                ->asJson()
                ->withHeaders(array_merge($this->authHeaders(), $headers));
            $response = $req->post($url, $body);
            if (! $response->successful()) {
                $resBody = $response->json();
                $msg = null;
                if (is_array($resBody)) {
                    $msg = $resBody['message'] ?? $resBody['error'] ?? null;
                }
                $this->lastError = $msg ?: ('Loja retornou HTTP ' . $response->status() . '.');

                return null;
            }

            return $response->json() ?? $response->body();
        } catch (\Throwable $e) {
            $this->lastError = $e->getMessage();
            report($e);

            return null;
        }
    }

    /** @return array<string, string> */
    private function authHeaders(): array
    {
        if ($this->apiKey === null) {
            return [];
        }

        return ['Authorization' => 'Bearer ' . $this->apiKey];
    }
}
