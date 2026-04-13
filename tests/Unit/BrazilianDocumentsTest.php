<?php

namespace Tests\Unit;

use App\Support\BrazilianDocuments;
use PHPUnit\Framework\TestCase;

class BrazilianDocumentsTest extends TestCase
{
    public function test_valid_cpf(): void
    {
        $this->assertTrue(BrazilianDocuments::isValidCpf('52998224725'));
        $this->assertFalse(BrazilianDocuments::isValidCpf('11111111111'));
    }

    public function test_valid_cnpj(): void
    {
        $this->assertTrue(BrazilianDocuments::isValidCnpj('11222333000181'));
        $this->assertFalse(BrazilianDocuments::isValidCnpj('11111111111111'));
    }
}
