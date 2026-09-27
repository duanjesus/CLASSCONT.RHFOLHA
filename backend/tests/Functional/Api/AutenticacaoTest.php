<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use App\Tests\Functional\ApiTestCase;
use Doctrine\ORM\EntityManagerInterface;

final class AutenticacaoTest extends ApiTestCase
{
    public function testLoginDevolveTokenQueDaAcessoAoPerfil(): void
    {
        $this->login('chefe.ti@classcont.local');
        self::assertResponseIsSuccessful();
        $token = $this->json()['token'];

        $this->client->jsonRequest('GET', '/api/me', server: ['HTTP_AUTHORIZATION' => 'Bearer '.$token]);
        $me = $this->json();

        self::assertResponseIsSuccessful();
        self::assertSame('Rafael Lima', $me['nome']);
        self::assertTrue($me['ehChefia']);
        self::assertContains('ROLE_CHEFIA', $me['roles']);
    }

    public function testSenhaErradaRetorna401(): void
    {
        $this->login('ana@classcont.local', 'errada');

        self::assertResponseStatusCodeSame(401);
    }

    public function testFuncionarioDesativadoPerdeOAcessoInclusiveComTokenJaEmitido(): void
    {
        $token = $this->token('diego@classcont.local');

        $this->funcionario('diego@classcont.local')->setAtivo(false);
        static::getContainer()->get(EntityManagerInterface::class)->flush();

        // O token emitido antes da desativação deixa de valer
        $this->client->jsonRequest('GET', '/api/me', server: ['HTTP_AUTHORIZATION' => 'Bearer '.$token]);
        self::assertResponseStatusCodeSame(401);

        // E não é possível logar de novo
        $this->login('diego@classcont.local');
        self::assertResponseStatusCodeSame(401);
        self::assertStringContainsString('desativado', $this->json()['message']);
    }

    public function testMuitasTentativasErradasBloqueiamOLogin(): void
    {
        $email = 'forca-bruta-'.uniqid().'@classcont.local';
        for ($i = 0; $i < 5; ++$i) {
            $this->login($email, 'errada');
        }

        $this->login($email, 'errada');
        self::assertStringContainsString('tentativas', $this->json()['message']);
    }

    public function testRotaProtegidaSemTokenRetorna401(): void
    {
        $this->client->jsonRequest('GET', '/api/me');

        self::assertResponseStatusCodeSame(401);
    }
}
