<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use App\Tests\Functional\ApiTestCase;

final class PontoTest extends ApiTestCase
{
    public function testBaterPontoRegistraEBloqueiaDuploClique(): void
    {
        $hoje = $this->api('POST', '/api/ponto/bater', 'ana@classcont.local');
        self::assertResponseStatusCodeSame(201);
        self::assertCount(1, $hoje['batidas']);
        self::assertSame('SAIDA_ALMOCO', $hoje['proximaBatida']);

        $erro = $this->api('POST', '/api/ponto/bater', 'ana@classcont.local');
        self::assertResponseStatusCodeSame(422);
        self::assertStringContainsString('1 minuto', $erro['erro']);
    }

    public function testEspelhoDoProprioMes(): void
    {
        $espelho = $this->api('GET', '/api/ponto/espelho', 'ana@classcont.local');

        self::assertResponseIsSuccessful();
        self::assertSame('Ana Souza', $espelho['funcionario']['nome']);
        self::assertGreaterThanOrEqual(28, \count($espelho['dias']));
        self::assertArrayHasKey('saldoMinutos', $espelho['totais']);
    }

    public function testChefiaVeEspelhoDaEquipeMasColegaNao(): void
    {
        $bruno = $this->funcionario('bruno@classcont.local');

        $this->api('GET', '/api/ponto/espelho?funcionario='.$bruno->getId(), 'chefe.ti@classcont.local');
        self::assertResponseIsSuccessful();

        $erro = $this->api('GET', '/api/ponto/espelho?funcionario='.$bruno->getId(), 'ana@classcont.local');
        self::assertResponseStatusCodeSame(403);
        self::assertSame('Você não tem permissão para esta ação.', $erro['erro']);

        // Chefia de outro setor também não
        $this->api('GET', '/api/ponto/espelho?funcionario='.$bruno->getId(), 'chefe.financeiro@classcont.local');
        self::assertResponseStatusCodeSame(403);
    }

    public function testEspelhoEmPdf(): void
    {
        $this->client->request('GET', '/api/ponto/espelho/pdf', server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$this->token('ana@classcont.local'),
        ]);

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'application/pdf');
        self::assertStringStartsWith('%PDF', (string) $this->client->getInternalResponse()->getContent());
    }

    public function testCompetenciaInvalidaRetorna422(): void
    {
        $erro = $this->api('GET', '/api/ponto/espelho?competencia=13-2026', 'ana@classcont.local');

        self::assertResponseStatusCodeSame(422);
        self::assertStringContainsString('AAAA-MM', $erro['erro']);
    }

    public function testEquipeSoParaChefia(): void
    {
        $equipe = $this->api('GET', '/api/equipe', 'chefe.ti@classcont.local');
        self::assertResponseIsSuccessful();
        self::assertEqualsCanonicalizing(['Ana Souza', 'Bruno Alves'], array_column(array_column($equipe, 'funcionario'), 'nome'));

        $this->api('GET', '/api/equipe', 'ana@classcont.local');
        self::assertResponseStatusCodeSame(403);
    }
}
