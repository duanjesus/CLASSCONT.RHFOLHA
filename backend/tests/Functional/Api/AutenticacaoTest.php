<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use App\Tests\Functional\ApiTestCase;

final class AutenticacaoTest extends ApiTestCase
{
    public function testLoginDevolveTokenEPerfil(): void
    {
        $me = $this->api('GET', '/api/me', 'chefe.ti@classcont.local');

        self::assertResponseIsSuccessful();
        self::assertSame('Rafael Lima', $me['nome']);
        self::assertTrue($me['ehChefia']);
        self::assertContains('ROLE_CHEFIA', $me['roles']);
    }

    public function testSenhaErradaRetorna401(): void
    {
        $this->client->jsonRequest('POST', '/api/login', ['email' => 'ana@classcont.local', 'password' => 'errada']);

        self::assertResponseStatusCodeSame(401);
    }

    public function testRotaProtegidaSemTokenRetorna401(): void
    {
        $this->client->jsonRequest('GET', '/api/me');

        self::assertResponseStatusCodeSame(401);
    }
}
