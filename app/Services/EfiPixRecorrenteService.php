<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

/**
 * Serviço para integração com a API Pix Automático da Efí (recorrência).
 * Utiliza os endpoints v2 (locrec, rec, cobr) que não estão no SDK PHP da Efí.
 * Reutiliza a mesma autenticação OAuth2 + certificado P12 do EfiDriver.
 */
class EfiPixRecorrenteService
{
    private const BASE_URI_PRODUCTION = 'https://pix.api.efipay.com.br';

    private const BASE_URI_SANDBOX = 'https://pix-h.api.efipay.com.br';

    /** @var array<string, string> */
    private array $credentials;

    private string $baseUri;

    private ?string $accessToken = null;

    /**
     * @param  array<string, string>  $credentials  Mesmo formato do EfiDriver: client_id, client_secret, certificate_path, pwd_certificate, sandbox, pix_key
     */
    public function __construct(array $credentials)
    {
        $this->credentials = $credentials;
        $sandbox = isset($credentials['sandbox']) && filter_var($credentials['sandbox'], FILTER_VALIDATE_BOOLEAN);
        $this->baseUri = $sandbox ? self::BASE_URI_SANDBOX : self::BASE_URI_PRODUCTION;
    }

    /**
     * Obtém token OAuth2 (client_credentials) e armazena em cache para a instância.
     */
    private function getAccessToken(): string
    {
        if ($this->accessToken !== null) {
            return $this->accessToken;
        }

        $clientId = $this->credentials['client_id'] ?? '';
        $clientSecret = $this->credentials['client_secret'] ?? '';
        $certPath = $this->credentials['certificate_path'] ?? '';
        $pwdCert = $this->credentials['pwd_certificate'] ?? '';

        if ($clientId === '' || $clientSecret === '' || $certPath === '' || ! is_file($certPath)) {
            throw new \RuntimeException('Efí Pix Recorrente: credenciais ou certificado inválidos.');
        }

        $client = $this->buildHttpClient($certPath, $pwdCert);

        $response = $client->post($this->baseUri . '/oauth/token', [
            'auth' => [$clientId, $clientSecret],
            'json' => ['grant_type' => 'client_credentials'],
        ]);

        $body = json_decode((string) $response->getBody(), true);
        if (! is_array($body) || empty($body['access_token'])) {
            throw new \RuntimeException('Efí Pix Recorrente: falha ao obter token OAuth2.');
        }

        $this->accessToken = $body['access_token'];

        return $this->accessToken;
    }

    /**
     * Cliente Guzzle com certificado P12 para mTLS.
     *
     * @return \GuzzleHttp\Client
     */
    private function buildHttpClient(string $certPath, string $pwdCert)
    {
        $certPath = realpath($certPath) ?: $certPath;

        return new \GuzzleHttp\Client([
            'base_uri' => $this->baseUri,
            'timeout' => 30,
            'headers' => [
                'Content-Type' => 'application/json',
            ],
            'cert' => [$certPath, $pwdCert],
            'verify' => true,
        ]);
    }

    /**
     * Requisição autenticada com Bearer token.
     *
     * @param  array<string, mixed>|null  $json
     * @return array<string, mixed>
     */
    private function request(string $method, string $path, ?array $json = null, array $query = []): array
    {
        $certPath = $this->credentials['certificate_path'] ?? '';
        $pwdCert = $this->credentials['pwd_certificate'] ?? '';
        if ($certPath === '' || ! is_file($certPath)) {
            throw new \RuntimeException('Efí Pix Recorrente: certificado não configurado.');
        }

        $client = $this->buildHttpClient($certPath, $pwdCert);
        $options = [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->getAccessToken(),
            ],
        ];
        if ($json !== null) {
            $options['json'] = $json;
        }
        if ($query !== []) {
            $options['query'] = $query;
        }

        $response = $client->request($method, $path, $options);
        $body = (string) $response->getBody();
        if ($body === '') {
            return [];
        }
        $decoded = json_decode($body, true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Cria location para recorrência (POST /v2/locrec).
     * Jornada 3 – passo 1.
     *
     * @return array{id: int, location: string, criacao: string}
     */
    public function createLocRec(): array
    {
        $data = $this->request('POST', '/v2/locrec', null);
        if (empty($data['id'])) {
            Log::warning('EfiPixRecorrenteService createLocRec invalid response', ['response' => $data]);
            throw new \RuntimeException('Efí: não foi possível criar o location de recorrência.');
        }

        return $data;
    }

    /**
     * Cria cobrança imediata PIX com txid controlado (PUT /v2/cob/:txid).
     * Jornada 3 – passo 2. Retorna loc.id para gerar QR Code.
     *
     * @param  array{name: string, document: string, email: string}  $consumer
     * @return array{txid: string, loc: array{id: int}, copy_paste?: string, qrcode?: string}
     */
    public function createCobWithTxid(
        string $txid,
        float $amount,
        array $consumer,
        string $pixKey,
        string $solicitacaoPagador = '',
        array $infoAdicionais = []
    ): array {
        $document = preg_replace('/\D/', '', $consumer['document'] ?? '');
        if (strlen($document) < 11) {
            $document = '00000000000';
        }

        $body = [
            'calendario' => ['expiracao' => 3600],
            'devedor' => [
                'cpf' => $document,
                'nome' => $consumer['name'] ?? '',
            ],
            'valor' => [
                'original' => number_format(round($amount, 2), 2, '.', ''),
            ],
            'chave' => $pixKey,
            'solicitacaoPagador' => $solicitacaoPagador !== '' ? $solicitacaoPagador : 'Pedido PIX automático',
            'infoAdicionais' => array_merge(
                [['nome' => 'order_id', 'valor' => $txid]],
                $infoAdicionais
            ),
        ];

        $data = $this->request('PUT', '/v2/cob/' . $txid, $body);
        if (empty($data['txid'])) {
            Log::warning('EfiPixRecorrenteService createCobWithTxid invalid response', ['response' => $data]);
            throw new \RuntimeException('Efí: não foi possível criar a cobrança imediata.');
        }

        $locId = $data['loc']['id'] ?? null;
        $copyPaste = null;
        $qrcode = null;
        if ($locId !== null) {
            try {
                $qrcodeData = $this->request('GET', '/v2/loc/' . $locId . '/qrcode');
                $copyPaste = $qrcodeData['qrcode'] ?? $qrcodeData['copiaECola'] ?? null;
                $qrcode = $qrcodeData['imagemQrcode'] ?? null;
            } catch (\Throwable $e) {
                Log::warning('EfiPixRecorrenteService get QR Code failed', ['txid' => $txid, 'message' => $e->getMessage()]);
            }
        }

        return [
            'txid' => $data['txid'],
            'loc' => $data['loc'] ?? ['id' => $locId],
            'copy_paste' => $copyPaste,
            'qrcode' => $qrcode,
        ];
    }

    /**
     * Cria recorrência de Pix Automático (POST /v2/rec) – Jornada 3, passo 3.
     *
     * @param  array{name: string, document: string, email: string}  $consumer
     * @return array{idRec: string, status: string, loc: array, valor: array, vinculo: array, calendario: array}
     */
    public function createRecurrence(
        int $locId,
        string $txidCob,
        array $consumer,
        float $valorRec,
        string $dataInicial,
        string $dataFinal,
        string $contrato = '',
        string $objeto = 'Assinatura'
    ): array {
        $document = preg_replace('/\D/', '', $consumer['document'] ?? '');
        if (strlen($document) < 11) {
            $document = '00000000000';
        }

        $contratoVal = $contrato !== '' ? $contrato : str_pad((string) time(), 8, '0', STR_PAD_LEFT);
        $body = [
            'loc' => $locId,
            'vinculo' => [
                'contrato' => preg_replace('/\D/', '', $contratoVal),
                'devedor' => [
                    'cpf' => $document,
                    'nome' => mb_substr($consumer['name'] ?? '', 0, 200),
                ],
                'objeto' => mb_substr($objeto, 0, 140),
            ],
            'calendario' => [
                'dataInicial' => $dataInicial,
                'dataFinal' => $dataFinal,
                'periodicidade' => 'MENSAL',
            ],
            'valor' => [
                'valorRec' => number_format(round($valorRec, 2), 2, '.', ''),
            ],
            'politicaRetentativa' => 'NAO_PERMITE',
            'ativacao' => [
                'dadosJornada' => [
                    'txid' => $txidCob,
                ],
            ],
        ];

        $data = $this->request('POST', '/v2/rec', $body);
        if (empty($data['idRec'])) {
            Log::warning('EfiPixRecorrenteService createRecurrence invalid response', ['response' => $data]);
            throw new \RuntimeException('Efí: não foi possível criar a recorrência PIX automático.');
        }

        return $data;
    }

    /**
     * Consulta recorrência (GET /v2/rec/:idRec). Opcionalmente com txid para obter copia e cola da primeira cobrança.
     *
     * @return array{idRec: string, dadosQR?: array{pixCopiaECola?: string}, status?: string}
     */
    public function getRecurrence(string $idRec, ?string $txid = null): array
    {
        $query = [];
        if ($txid !== null && $txid !== '') {
            $query['txid'] = $txid;
        }

        return $this->request('GET', '/v2/rec/' . $idRec, null, $query);
    }

    /**
     * Cria cobrança recorrente associada à recorrência (PUT /v2/cobr/:txid ou POST /v2/cobr).
     * Usado após aprovação do pagador (após webhook da primeira cob) e nas renovações.
     *
     * @param  array{name?: string, document?: string, email?: string}  $devedor
     * @return array{txid: string, idRec: string, status: string}
     */
    public function createCobrancaRecorrente(
        string $idRec,
        float $valor,
        string $dataDeVencimento,
        ?string $txid = null,
        array $devedor = [],
        string $infoAdicional = ''
    ): array {
        $body = [
            'idRec' => $idRec,
            'valor' => ['original' => number_format(round($valor, 2), 2, '.', '')],
            'calendario' => ['dataDeVencimento' => $dataDeVencimento],
            'ajusteDiaUtil' => true,
            'devedor' => array_filter([
                'nome' => $devedor['name'] ?? $devedor['nome'] ?? null,
                'email' => $devedor['email'] ?? null,
                'logradouro' => $devedor['logradouro'] ?? null,
                'cidade' => $devedor['cidade'] ?? null,
                'uf' => $devedor['uf'] ?? null,
                'cep' => $devedor['cep'] ?? null,
            ]),
        ];
        if ($infoAdicional !== '') {
            $body['infoAdicional'] = $infoAdicional;
        }

        if ($txid !== null && $txid !== '') {
            $data = $this->request('PUT', '/v2/cobr/' . $txid, $body);
        } else {
            $data = $this->request('POST', '/v2/cobr', $body);
        }

        if (empty($data['idRec']) && empty($data['txid'])) {
            Log::warning('EfiPixRecorrenteService createCobrancaRecorrente invalid response', ['response' => $data]);
            throw new \RuntimeException('Efí: não foi possível criar a cobrança recorrente.');
        }

        return $data;
    }
}
