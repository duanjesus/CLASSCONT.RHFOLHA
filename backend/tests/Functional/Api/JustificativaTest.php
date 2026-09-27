<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use App\Tests\Functional\ApiTestCase;

final class JustificativaTest extends ApiTestCase
{
    public function testFluxoCompletoDeAprovacaoPelaChefia(): void
    {
        $ontem = (new \DateTimeImmutable('yesterday'))->format('Y-m-d');

        $criada = $this->api('POST', '/api/justificativas', 'ana@classcont.local', [
            'data' => $ontem,
            'tipo' => 'SERVICO_EXTERNO',
            'motivo' => 'Reunião no órgão parceiro durante todo o expediente.',
        ]);
        self::assertResponseStatusCodeSame(201);
        self::assertSame('PENDENTE', $criada['status']);

        $pendentes = $this->api('GET', '/api/justificativas/pendentes', 'chefe.ti@classcont.local');
        self::assertContains($criada['id'], array_column($pendentes, 'id'));

        $avaliada = $this->api('POST', "/api/justificativas/{$criada['id']}/avaliar", 'chefe.ti@classcont.local', [
            'decisao' => 'APROVAR',
        ]);
        self::assertResponseIsSuccessful();
        self::assertSame('APROVADA', $avaliada['status']);
        self::assertSame('Rafael Lima', $avaliada['avaliadoPor']);

        // E-mail (template Twig) enviado ao servidor
        self::assertEmailCount(1);
        $email = self::getMailerMessage();
        self::assertNotNull($email);
        self::assertEmailAddressContains($email, 'to', 'ana@classcont.local');
        self::assertEmailHtmlBodyContains($email, 'Aprovada');
    }

    public function testNaoAvaliaOProprioPedidoNemDeOutroSetor(): void
    {
        $pendente = $this->pendenteDoBruno();

        $this->api('POST', "/api/justificativas/{$pendente}/avaliar", 'bruno@classcont.local', ['decisao' => 'APROVAR']);
        self::assertResponseStatusCodeSame(403);

        $this->api('POST', "/api/justificativas/{$pendente}/avaliar", 'chefe.financeiro@classcont.local', ['decisao' => 'APROVAR']);
        self::assertResponseStatusCodeSame(403);

        // O RH pode avaliar qualquer setor
        $this->api('POST', "/api/justificativas/{$pendente}/avaliar", 'rh@classcont.local', ['decisao' => 'APROVAR']);
        self::assertResponseIsSuccessful();
    }

    public function testRecusaExigeMotivo(): void
    {
        $erro = $this->api('POST', "/api/justificativas/{$this->pendenteDoBruno()}/avaliar", 'chefe.ti@classcont.local', [
            'decisao' => 'RECUSAR',
        ]);

        self::assertResponseStatusCodeSame(422);
        self::assertSame('Informe o motivo da recusa.', $erro['erro']);
    }

    public function testValidacaoDosCampos(): void
    {
        $erro = $this->api('POST', '/api/justificativas', 'ana@classcont.local', [
            'data' => 'ontem', 'tipo' => 'FERIAS', 'motivo' => 'curto',
        ]);

        self::assertResponseStatusCodeSame(422);
        self::assertSame(['data', 'tipo', 'motivo'], array_keys($erro['detalhes']));
        self::assertSame('Tipo de justificativa inválido.', $erro['detalhes']['tipo']);
    }

    public function testNaoJustificaDataFutura(): void
    {
        $erro = $this->api('POST', '/api/justificativas', 'ana@classcont.local', [
            'data' => (new \DateTimeImmutable('+3 days'))->format('Y-m-d'),
            'tipo' => 'ATESTADO_MEDICO',
            'motivo' => 'Consulta agendada com antecedência.',
        ]);

        self::assertResponseStatusCodeSame(422);
        self::assertSame('Não é possível justificar uma data futura.', $erro['erro']);
    }

    private function pendenteDoBruno(): int
    {
        $pendentes = $this->api('GET', '/api/justificativas/pendentes', 'chefe.ti@classcont.local');
        self::assertNotEmpty($pendentes);

        return $pendentes[0]['id'];
    }
}
