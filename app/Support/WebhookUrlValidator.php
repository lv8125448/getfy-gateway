<?php

namespace App\Support;

use InvalidArgumentException;

/**
 * Evita SSRF em webhooks de saída: esquema permitido, host público, sem credenciais na URL.
 */
final class WebhookUrlValidator
{
    /**
     * @throws InvalidArgumentException
     */
    public static function assertAllowed(string $url): void
    {
        $trimmed = trim($url);
        if ($trimmed === '') {
            throw new InvalidArgumentException('URL vazia.');
        }

        $parts = parse_url($trimmed);
        if ($parts === false || empty($parts['scheme']) || empty($parts['host'])) {
            throw new InvalidArgumentException('URL inválida.');
        }

        $scheme = strtolower((string) $parts['scheme']);
        $allowHttp = app()->environment('local');
        if ($scheme === 'https') {
            // ok
        } elseif ($scheme === 'http' && $allowHttp) {
            // ok
        } else {
            throw new InvalidArgumentException(
                $allowHttp
                    ? 'Apenas HTTPS ou HTTP (ambiente local) é permitido.'
                    : 'Apenas HTTPS é permitido para webhooks.'
            );
        }

        if (! empty($parts['user']) || ! empty($parts['pass'])) {
            throw new InvalidArgumentException('URL não pode conter usuário ou senha.');
        }

        $host = (string) $parts['host'];
        if (str_starts_with($host, '[') && str_ends_with($host, ']')) {
            $host = substr($host, 1, -1);
        }

        if (filter_var($host, FILTER_VALIDATE_IP)) {
            if (! self::isPublicIp($host)) {
                throw new InvalidArgumentException('IP de destino não permitido.');
            }

            return;
        }

        $ips = self::resolveHostToIps($host);
        if ($ips === []) {
            throw new InvalidArgumentException('Não foi possível resolver o host.');
        }

        foreach (array_unique($ips) as $ip) {
            if (! self::isPublicIp($ip)) {
                throw new InvalidArgumentException('O host resolve para um endereço não permitido.');
            }
        }
    }

    private static function isPublicIp(string $ip): bool
    {
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;
        }

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;
        }

        return false;
    }

    /**
     * @return list<string>
     */
    private static function resolveHostToIps(string $host): array
    {
        $ips = [];

        if (function_exists('dns_get_record')) {
            $flags = DNS_A;
            if (\defined('DNS_AAAA')) {
                $flags |= DNS_AAAA;
            }
            $records = @dns_get_record($host, $flags);
            if (is_array($records)) {
                foreach ($records as $r) {
                    if (! empty($r['ip'])) {
                        $ips[] = $r['ip'];
                    }
                    if (! empty($r['ipv6'])) {
                        $ips[] = $r['ipv6'];
                    }
                }
            }
        }

        if ($ips === []) {
            $resolved = @gethostbynamel($host);
            if (is_array($resolved)) {
                foreach ($resolved as $ip) {
                    if ($ip !== '') {
                        $ips[] = $ip;
                    }
                }
            }
        }

        return array_values(array_unique($ips));
    }
}
