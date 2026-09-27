<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use App\Tests\Functional\ApiTestCase;

final class AuxilioTest extends ApiTestCase
{
    public function testSolicitarEAprovarSubstituiOVigente(): void
    {
        $linhas = $this->api('GET', '/api/linhas', 'ana@classcont.local');
        $l101 = $this->linha($linhas, '101');

        $nova = $this->api('POST', '/api/auxilio', 'ana@classcont.local', ['trajetos' => [
            ['linhaId' => $l101['id'], 'sentido' => 'IDA'],
            ['linhaId' => $l101['id'], 'sentido' => 'VOLTA'],
        ]]);
        self::assertResponseStatusCodeSame(201);
        self::assertSame(10.5, $nova['valorDiario']);

        // Enquanto não aprovado, o vigente continua sendo o antigo
        $meu = $this->api('GET', '/api/auxilio', 'ana@classcont.local');
        self::assertSame(25.1, $meu['vigente']['valorDiario']);
        self::assertSame($nova['id'], $meu['pendente']['id']);

        $this->api('POST', "/api/auxilio/{$nova['id']}/avaliar", 'chefe.ti@classcont.local', ['decisao' => 'APROVAR']);
        self::assertResponseIsSuccessful();

        $meu = $this->api('GET', '/api/auxilio', 'ana@classcont.local');
        self::assertSame($nova['id'], $meu['vigente']['id']);
        self::assertNull($meu['pendente']);
    }

    public function testItinerarioPrecisaDeIdaEVolta(): void
    {
        $l101 = $this->linha($this->api('GET', '/api/linhas', 'ana@classcont.local'), '101');

        $erro = $this->api('POST', '/api/auxilio', 'ana@classcont.local', ['trajetos' => [
            ['linhaId' => $l101['id'], 'sentido' => 'IDA'],
        ]]);

        self::assertResponseStatusCodeSame(422);
        self::assertStringContainsString('ida e uma de volta', $erro['erro']);
    }

    public function testDemonstrativoUsaDiasTrabalhadosDoPonto(): void
    {
        $mesAnterior = (new \DateTimeImmutable('first day of last month'))->format('Y-m');
        $d = $this->api('GET', '/api/auxilio/demonstrativo?competencia='.$mesAnterior, 'ana@classcont.local');

        self::assertResponseIsSuccessful();
        self::assertSame(25.1, $d['valorDiario']);
        self::assertSame(round($d['valorDiario'] * $d['diasTrabalhados'], 2), $d['valorBruto']);
        self::assertEqualsWithDelta($d['valorBruto'] - $d['valorDesconto'], $d['valorLiquido'], 0.001);
    }

    public function testSemAuxilioVigenteRetorna404(): void
    {
        $this->api('GET', '/api/auxilio/demonstrativo', 'joao@classcont.local');

        self::assertResponseStatusCodeSame(404);
    }

    /**
     * @param list<array{id: int, codigo: string}> $linhas
     *
     * @return array{id: int, codigo: string}
     */
    private function linha(array $linhas, string $codigo): array
    {
        foreach ($linhas as $linha) {
            if ($linha['codigo'] === $codigo) {
                return $linha;
            }
        }
        self::fail("Linha $codigo não encontrada.");
    }
}
