<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use App\Repository\FeriadoRepository;
use App\Tests\Functional\ApiTestCase;
use Symfony\Component\Mailer\Messenger\SendEmailMessage;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;

final class JustificativaTest extends ApiTestCase
{
    public function testFluxoCompletoDeAprovacaoPelaChefia(): void
    {
        $diaUtil = $this->ultimoDiaUtil()->format('Y-m-d');

        $criada = $this->api('POST', '/api/justificativas', 'ana@classcont.local', [
            'data' => $diaUtil,
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

        // O e-mail (template Twig) não é enviado dentro da requisição: vai para a fila
        // do Messenger, já renderizado, e o worker é quem fala com o SMTP.
        self::assertEmailCount(0);
        self::assertQueuedEmailCount(1);
        $fila = static::getContainer()->get('messenger.transport.async');
        self::assertInstanceOf(InMemoryTransport::class, $fila);
        self::assertCount(1, $fila->getSent());
        self::assertInstanceOf(SendEmailMessage::class, $fila->getSent()[0]->getMessage());

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

    public function testNaoJustificaFimDeSemana(): void
    {
        $erro = $this->api('POST', '/api/justificativas', 'ana@classcont.local', [
            'data' => (new \DateTimeImmutable('last saturday'))->format('Y-m-d'),
            'tipo' => 'FALTA_JUSTIFICADA',
            'motivo' => 'Tentativa de abonar um sábado.',
        ]);

        self::assertResponseStatusCodeSame(422);
        self::assertStringContainsString('dias úteis', $erro['erro']);
    }

    /** Dia útil mais recente antes de hoje (pula fins de semana e feriados cadastrados). */
    private function ultimoDiaUtil(): \DateTimeImmutable
    {
        $feriados = static::getContainer()->get(FeriadoRepository::class);
        $dia = new \DateTimeImmutable('yesterday');
        while ((int) $dia->format('N') >= 6 || null !== $feriados->findOneBy(['data' => $dia])) {
            $dia = $dia->modify('-1 day');
        }

        return $dia;
    }

    private function pendenteDoBruno(): int
    {
        $pendentes = $this->api('GET', '/api/justificativas/pendentes', 'chefe.ti@classcont.local');
        self::assertNotEmpty($pendentes);

        return $pendentes[0]['id'];
    }
}
