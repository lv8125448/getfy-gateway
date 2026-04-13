<?php

namespace App\Support;

/**
 * Parseia lista de e-mails para alertas KYC (vírgula, ponto e vírgula ou quebra de linha).
 *
 * @return list<string>
 */
class KycNotificationEmails
{
    public static function parse(?string $raw): array
    {
        if ($raw === null || trim($raw) === '') {
            return [];
        }

        $parts = preg_split('/[\s,;]+/u', $raw, -1, PREG_SPLIT_NO_EMPTY);
        if (! is_array($parts)) {
            return [];
        }

        $out = [];
        foreach ($parts as $p) {
            $e = strtolower(trim($p));
            if ($e === '') {
                continue;
            }
            if (filter_var($e, FILTER_VALIDATE_EMAIL)) {
                $out[] = $e;
            }
        }

        return array_values(array_unique($out));
    }
}
