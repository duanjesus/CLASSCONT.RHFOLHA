<?php

declare(strict_types=1);

namespace App\Tests\Functional\Admin;

use App\Entity\RegistroAuditoria;
use App\Enum\AcaoAuditoria;
use App\Repository\RegistroAuditoriaRepository;
use App\Tests\Functional\ApiTestCase;

final class AuditoriaTest extends ApiTestCase
{
    public function testCriacaoEAlteracaoPeloPainelFicamRegistradas(): void
    {
        $this->client->loginUser($this->funcionario('rh@classcont.local'), 'admin');

        $crawler = $this->client->request('GET', '/admin/cargos/novo');
        $this->client->submit($crawler->selectButton('Salvar')->form([
            'cargo[nome]' => 'Auditor Interno',
            'cargo[salarioBase]' => '7000,00',
        ]));
        self::assertResponseRedirects('/admin/cargos');

        $criacao = $this->ultimo();
        self::assertSame(AcaoAuditoria::Criacao, $criacao->getAcao());
        self::assertSame('Cargo', $criacao->getEntidade());
        self::assertSame('Auditor Interno', $criacao->getRotulo());
        self::assertSame('Marina Costa', $criacao->getAutorNome());
        self::assertSame([null, 'Auditor Interno'], $criacao->getAlteracoes()['nome']);

        $crawler = $this->client->request('GET', '/admin/cargos/'.$criacao->getEntidadeId().'/editar');
        $this->client->submit($crawler->selectButton('Salvar')->form(['cargo[salarioBase]' => '7500,00']));

        $alteracao = $this->ultimo();
        self::assertSame(AcaoAuditoria::Alteracao, $alteracao->getAcao());
        // Só o campo que mudou, com antes e depois
        self::assertSame(['salarioBase' => ['7000.00', '7500.00']], $alteracao->getAlteracoes());
    }

    public function testAvaliacaoPelaApiRegistraQuemDecidiu(): void
    {
        $pendentes = $this->api('GET', '/api/justificativas/pendentes', 'chefe.ti@classcont.local');
        $this->api('POST', "/api/justificativas/{$pendentes[0]['id']}/avaliar", 'chefe.ti@classcont.local', ['decisao' => 'APROVAR']);
        self::assertResponseIsSuccessful();

        $registro = $this->ultimo();
        self::assertSame('Justificativa', $registro->getEntidade());
        self::assertSame('Rafael Lima', $registro->getAutorNome());
        self::assertSame(['Pendente', 'Aprovada'], $registro->getAlteracoes()['status']);
        self::assertSame([null, 'Rafael Lima (100310)'], $registro->getAlteracoes()['avaliadoPor']);
    }

    public function testTrocaDeSenhaERegistradaSemOValor(): void
    {
        $this->client->loginUser($this->funcionario('rh@classcont.local'), 'admin');
        $ana = $this->funcionario('ana@classcont.local');

        $crawler = $this->client->request('GET', '/admin/funcionarios/'.$ana->getId().'/editar');
        $this->client->submit($crawler->selectButton('Salvar')->form(['funcionario[senha]' => 'novaSenhaForte1']));
        self::assertResponseRedirects('/admin/funcionarios');

        $registro = $this->ultimo();
        self::assertSame('Funcionário', $registro->getEntidade());
        self::assertArrayHasKey('password', $registro->getAlteracoes());
        self::assertStringNotContainsString('$2y$', (string) json_encode($registro->getAlteracoes()));
        self::assertStringNotContainsString('novaSenhaForte1', (string) json_encode($registro->getAlteracoes()));
    }

    public function testExclusaoFicaRegistrada(): void
    {
        $this->client->loginUser($this->funcionario('rh@classcont.local'), 'admin');

        $crawler = $this->client->request('GET', '/admin/feriados/novo');
        $this->client->submit($crawler->selectButton('Salvar')->form([
            'feriado[data]' => '2027-06-24',
            'feriado[descricao]' => 'São João',
        ]));
        $crawler = $this->client->request('GET', '/admin/feriados?ano=2027');
        $this->client->submit($crawler->selectButton('Excluir')->form());

        $registro = $this->ultimo();
        self::assertSame(AcaoAuditoria::Exclusao, $registro->getAcao());
        self::assertSame('24/06/2027 — São João', $registro->getRotulo());
        self::assertNotSame('', $registro->getEntidadeId());
    }

    public function testTelaListaFiltraEExigeRh(): void
    {
        $this->api('POST', '/api/justificativas/'.$this->api('GET', '/api/justificativas/pendentes', 'chefe.ti@classcont.local')[0]['id'].'/avaliar', 'chefe.ti@classcont.local', ['decisao' => 'APROVAR']);

        $this->client->loginUser($this->funcionario('rh@classcont.local'), 'admin');
        $this->client->request('GET', '/admin/auditoria');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Trilha de auditoria');
        self::assertSelectorTextContains('table', 'Rafael Lima');
        self::assertSelectorTextContains('table', 'Aprovada');

        // Filtro que não casa com nada + data inválida não quebram a tela
        $this->client->request('GET', '/admin/auditoria?autor=ninguem&de=xx&pagina=99');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('table', 'Nenhum registro');

        $this->client->loginUser($this->funcionario('ana@classcont.local'), 'admin');
        $this->client->request('GET', '/admin/auditoria');
        self::assertResponseStatusCodeSame(403);
    }

    private function ultimo(): RegistroAuditoria
    {
        $registro = static::getContainer()->get(RegistroAuditoriaRepository::class)->findOneBy([], ['id' => 'DESC']);
        self::assertNotNull($registro, 'Nenhum registro de auditoria foi gravado.');

        return $registro;
    }
}
