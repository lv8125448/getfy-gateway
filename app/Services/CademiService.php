<?php

namespace App\Services;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class CademiService
{
    public function __construct(
        private string $baseUrl,
        private string $apiKey
    ) {
        $this->baseUrl = rtrim($this->baseUrl, '/');
    }

    private function apiBase(): string
    {
        return $this->baseUrl.'/api/v1';
    }

    private function postbackUrl(): string
    {
        return $this->baseUrl.'/api/postback/custom';
    }

    private function client()
    {
        return Http::timeout(20)
            ->connectTimeout(10)
            ->retry(2, 500)
            ->withHeaders([
                'Authorization' => $this->apiKey,
                'Accept' => 'application/json',
            ]);
    }

    private function postbackClient()
    {
        return Http::timeout(20)
            ->connectTimeout(10)
            ->retry(2, 500)
            ->asForm()
            ->acceptJson();
    }

    /**
     * Fetch a user by identifier (id/email/document).
     *
     * @return array<string, mixed>|null
     */
    public function getUser(string $identifier): ?array
    {
        $identifier = trim($identifier);
        if ($identifier === '') {
            return null;
        }

        $res = $this->client()->get($this->apiBase().'/usuario/'.$identifier);
        if ($res->status() === 404) {
            return null;
        }
        if (! $res->successful()) {
            throw new \RuntimeException('Cademí API error (get user): HTTP '.$res->status().' '.$res->body());
        }

        $json = $res->json();
        $user = $json['data']['usuario'] ?? null;

        return is_array($user) ? $user : null;
    }

    /**
     * Create or update a user. Returns user array (must include id).
     *
     * Cademí docs show update via POST with `usuario_id` + fields.
     * If user doesn't exist, we fallback to "update" with empty usuario_id isn't possible,
     * so we currently require that the user exists or can be created via /usuario endpoint.
     *
     * For now, we try to locate by email first; if found, we update.
     */
    public function upsertUser(array $payload): array
    {
        $email = trim((string) ($payload['email'] ?? ''));
        if ($email === '') {
            throw new \InvalidArgumentException('Cademí upsertUser: email is required');
        }

        $existing = $this->getUser($email);
        if ($existing && isset($existing['id'])) {
            $id = (int) $existing['id'];
            $this->updateUser($id, $payload);
            $fresh = $this->getUser((string) $id);
            if (is_array($fresh) && isset($fresh['id'])) {
                return $fresh;
            }
            $existing['id'] = $id;
            return $existing;
        }

        // Create user (endpoint varies; many Cademí setups accept POST /usuario).
        // If the instance doesn't support it, the integration will surface the error.
        $res = $this->client()->post($this->apiBase().'/usuario', [
            'nome' => $payload['nome'] ?? ($payload['name'] ?? ''),
            'email' => $email,
            'doc' => $payload['doc'] ?? ($payload['cpf'] ?? null),
            'celular' => $payload['celular'] ?? ($payload['phone'] ?? null),
        ]);
        if (! $res->successful()) {
            throw new \RuntimeException('Cademí API error (create user): HTTP '.$res->status().' '.$res->body());
        }

        $json = $res->json();
        $user = $json['data']['usuario'] ?? null;
        if (! is_array($user) || ! isset($user['id'])) {
            // Some instances may not return full user; try to refetch by email.
            $refetched = $this->getUser($email);
            if ($refetched && isset($refetched['id'])) {
                return $refetched;
            }
            throw new \RuntimeException('Cademí API create user: invalid response');
        }

        return $user;
    }

    public function updateUser(int $userId, array $payload): Response
    {
        $body = array_filter([
            'usuario_id' => $userId,
            'nome' => $payload['nome'] ?? ($payload['name'] ?? null),
            'email' => $payload['email'] ?? null,
            'doc' => $payload['doc'] ?? ($payload['cpf'] ?? null),
            'celular' => $payload['celular'] ?? ($payload['phone'] ?? null),
        ], fn ($v) => $v !== null);

        $res = $this->client()->post($this->apiBase().'/usuario', $body);
        if (! $res->successful()) {
            throw new \RuntimeException('Cademí API error (update user): HTTP '.$res->status().' '.$res->body());
        }

        return $res;
    }

    public function addTagToUser(int $userId, int $tagId): Response
    {
        $res = $this->client()->post($this->apiBase().'/tag/adicionar_usuario', [
            'usuario_id' => $userId,
            'tag_id' => $tagId,
        ]);

        if (! $res->successful()) {
            throw new \RuntimeException('Cademí API error (add tag): HTTP '.$res->status().' '.$res->body());
        }

        return $res;
    }

    /**
     * List all tags (id + nome).
     *
     * @return array<int, array{id: int, nome: string}>
     */
    public function listTags(): array
    {
        $res = $this->client()->get($this->apiBase().'/tag');
        if (! $res->successful()) {
            throw new \RuntimeException('Cademí API error (list tags): HTTP '.$res->status().' '.$res->body());
        }

        $json = $res->json();
        $items = $json['data']['itens'] ?? $json['data']['tags'] ?? null;
        if (! is_array($items)) {
            return [];
        }

        $out = [];
        foreach ($items as $t) {
            if (! is_array($t)) {
                continue;
            }
            $id = isset($t['id']) ? (int) $t['id'] : 0;
            $nome = isset($t['nome']) ? trim((string) $t['nome']) : '';
            if ($id > 0) {
                $out[] = ['id' => $id, 'nome' => $nome];
            }
        }

        return $out;
    }

    /**
     * Resolve tag name(s) from id(s) using cache.
     *
     * @param  array<int, int>  $tagIds
     * @return array<int, string>
     */
    public function resolveTagNames(array $tagIds): array
    {
        $tagIds = array_values(array_filter(array_map('intval', $tagIds), fn ($id) => $id > 0));
        if ($tagIds === []) {
            return [];
        }

        $cacheKey = 'cademi.tags.'.$this->baseUrl;
        $tags = Cache::remember($cacheKey, now()->addMinutes(30), function () {
            return $this->listTags();
        });

        $byId = [];
        foreach ($tags as $t) {
            $id = (int) ($t['id'] ?? 0);
            $nome = trim((string) ($t['nome'] ?? ''));
            if ($id > 0 && $nome !== '') {
                $byId[$id] = $nome;
            }
        }

        $names = [];
        foreach ($tagIds as $id) {
            if (isset($byId[$id])) {
                $names[] = $byId[$id];
            }
        }

        return array_values(array_unique($names));
    }

    /**
     * Send custom delivery postback (recommended gateway integration).
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function sendCustomPostback(array $data): array
    {
        $res = $this->postbackClient()->post($this->postbackUrl(), $data);
        if (! $res->successful()) {
            throw new \RuntimeException('Cademí API error (postback custom): HTTP '.$res->status().' '.$res->body());
        }

        $json = $res->json();
        return is_array($json) ? $json : ['raw' => $res->body()];
    }
}

