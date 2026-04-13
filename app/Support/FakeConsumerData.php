<?php

namespace App\Support;

/**
 * Dados fake para envio aos gateways quando o checkout não coleta nome, CPF ou telefone.
 * CPFs são válidos (algoritmo de dígitos verificadores).
 */
class FakeConsumerData
{
    /** @var array<int, string> */
    private static array $names = [
        'Comprador',
        'Cliente Checkout',
        'Consumidor Final',
        'Usuario Pagador',
        'Comprador Online',
        'Cliente Plataforma',
        'Pagador',
        'Consumidor',
        'Cliente Final',
        'Usuario Sistema',
    ];

    /** Bases de 9 dígitos para gerar CPFs válidos (com dígitos verificadores). */
    private static array $cpfBases = [
        '529982247',
        '111444777',
        '123456789',
        '987654321',
        '123123123',
        '456456456',
        '789789789',
        '321321321',
        '654654654',
        '147147147',
        '258258258',
        '369369369',
        '159159159',
        '357357357',
        '951951951',
    ];

    /** @var array<int, string> */
    private static array $phones = [
        '11999999999',
        '21988888888',
        '31977777777',
        '41966666666',
        '51955555555',
        '61944444444',
        '71933333333',
        '81922222222',
        '11987654321',
        '21976543210',
        '31965432109',
        '41954321098',
        '51943210987',
        '61932109876',
        '71921098765',
    ];

    /**
     * Gera um CPF válido (11 dígitos) a partir de uma base de 9 dígitos.
     */
    private static function makeValidCpf(string $base): string
    {
        $d = array_map('intval', str_split($base, 1));
        $s = 0;
        for ($i = 0; $i < 9; $i++) {
            $s += (10 - $i) * $d[$i];
        }
        $d[9] = ($s * 10) % 11;
        if ($d[9] === 10) {
            $d[9] = 0;
        }
        $s = 0;
        for ($i = 0; $i < 10; $i++) {
            $s += (11 - $i) * $d[$i];
        }
        $d[10] = ($s * 10) % 11;
        if ($d[10] === 10) {
            $d[10] = 0;
        }

        return implode('', $d);
    }

    /**
     * Retorna um conjunto de dados fake (name, document, phone) variado pelo seed.
     * Usado quando customer_fields omitem nome, CPF ou telefone mas o gateway exige.
     * Document é sempre um CPF válido (dígitos verificadores corretos).
     *
     * @return array{name: string, document: string, phone: string}
     */
    public static function getForGateway(int $seed): array
    {
        $n = count(self::$names);
        $c = count(self::$cpfBases);
        $p = count(self::$phones);
        $base = self::$cpfBases[$seed % $c];

        return [
            'name' => self::$names[$seed % $n],
            'document' => self::makeValidCpf($base),
            'phone' => self::$phones[$seed % $p],
        ];
    }
}
