<?php

namespace App\Support;

/**
 * Validação de CPF e CNPJ (dígitos verificadores).
 */
final class BrazilianDocuments
{
    public static function digits(?string $value): string
    {
        return preg_replace('/\D/', '', (string) $value) ?? '';
    }

    public static function isValidCpf(string $digits): bool
    {
        $d = self::digits($digits);
        if (strlen($d) !== 11) {
            return false;
        }
        if (preg_match('/^(\d)\1{10}$/', $d)) {
            return false;
        }

        for ($t = 9; $t < 11; $t++) {
            $s = 0;
            for ($i = 0; $i < $t; $i++) {
                $s += (int) $d[$i] * (($t + 1) - $i);
            }
            $r = ($s * 10) % 11;
            if ($r === 10) {
                $r = 0;
            }
            if ($r !== (int) $d[$t]) {
                return false;
            }
        }

        return true;
    }

    public static function isValidCnpj(string $digits): bool
    {
        $d = self::digits($digits);
        if (strlen($d) !== 14) {
            return false;
        }
        if (preg_match('/^(\d)\1{13}$/', $d)) {
            return false;
        }

        $w1 = [5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];
        $s = 0;
        for ($i = 0; $i < 12; $i++) {
            $s += (int) $d[$i] * $w1[$i];
        }
        $r = $s % 11;
        $dv1 = $r < 2 ? 0 : 11 - $r;
        if ($dv1 !== (int) $d[12]) {
            return false;
        }

        $w2 = [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];
        $s = 0;
        for ($i = 0; $i < 13; $i++) {
            $s += (int) $d[$i] * $w2[$i];
        }
        $r = $s % 11;
        $dv2 = $r < 2 ? 0 : 11 - $r;

        return $dv2 === (int) $d[13];
    }
}
