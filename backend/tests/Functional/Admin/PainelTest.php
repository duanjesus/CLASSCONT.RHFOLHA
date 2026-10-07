<?php

declare(strict_types=1);

namespace App\Tests\Functional\Admin;

use App\Tests\Functional\ApiTestCase;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\DataProvider;

/** Smoke test das telas Twig do painel + regras de acesso. */
final class PainelTest extends ApiTestCase
{
    public function testAnonimoERedirecionadoParaLogin(): void
    {
        $this->client->request('GET', '/admin');

        self::assertResponseRedirects('/admin/login');
    }

    public function testServidorSemPerfilRhNaoAcessa(): void
    {
        $this->client->loginUser($this->funcionario('ana@classcont.local'), 'admin');
        $this->client->request('GET', '/admin');

        self::assertResponseStatusCodeSame(403);
    }

    public function testLoginPeloFormulario(): void
    {
        $crawler = $this->client->request('GET', '/admin/login');
        $this->client->submit($crawler->selectButton('Entrar')->form([
            'email' => 'rh@classcont.local',
            'senha' => 'senha123',
        ]));

        self::assertResponseRedirects('/admin');
        $this->client->followRedirect();
        self::assertSelectorTextContains('h1', 'Olá, Marina!');
    }

    #[DataProvider('paginas')]
    public function testPaginasRenderizam(string $url, string $titulo): void
    {
        $this->client->loginUser($this->funcionario('rh@classcont.local'), 'admin');
        $this->client->request('GET', $url);

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', $titulo);
    }

    /** @return iterable<array{string, string}> */
    public static function paginas(): iterable
    {
        yield ['/admin', 'Olá'];
        yield ['/admin/funcionarios', 'Funcionários'];
        yield ['/admin/funcionarios/novo', 'Novo funcionário'];
        yield ['/admin/setores', 'Setores'];
        yield ['/admin/cargos', 'Cargos'];
        yield ['/admin/feriados', 'Feriados'];
        yield ['/admin/linhas', 'Linhas de ônibus'];
        yield ['/admin/fechamentos', 'Fechamento mensal'];
        yield ['/admin/relatorios/auxilio-transporte', 'Folha do auxílio-transporte'];
    }

    public function testPainelAvisaQuandoHaEmailNaFilaDeFalhas(): void
    {
        $this->client->loginUser($this->funcionario('rh@classcont.local'), 'admin');

        $this->client->request('GET', '/admin');
        self::assertSelectorNotExists('[role="alert"]');

        static::getContainer()->get(Connection::class)->insert('messenger_messages', [
            'body' => '{}', 'headers' => '{}', 'queue_name' => 'failed',
            'created_at' => '2026-10-07 10:00:00', 'available_at' => '2026-10-07 10:00:00',
        ]);

        $this->client->request('GET', '/admin');
        self::assertSelectorTextContains('[role="alert"]', '1 e-mail não pôde ser enviado');
    }

    public function testCompetenciaMalformadaNoPainelRetorna400(): void
    {
        $this->client->loginUser($this->funcionario('rh@classcont.local'), 'admin');
        $this->client->request('GET', '/admin/relatorios/auxilio-transporte?competencia=abc');

        self::assertResponseStatusCodeSame(400);
    }

    public function testCadastroDeSetorComValidacao(): void
    {
        $this->client->loginUser($this->funcionario('rh@classcont.local'), 'admin');
        $crawler = $this->client->request('GET', '/admin/setores/novo');

        // Sigla duplicada → formulário volta com erro (422)
        $this->client->submit($crawler->selectButton('Salvar')->form([
            'setor[sigla]' => 'sti',
            'setor[nome]' => 'Duplicado',
        ]));
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('form', 'Já existe um setor com esta sigla.');

        $this->client->submit($crawler->selectButton('Salvar')->form([
            'setor[sigla]' => 'ouv',
            'setor[nome]' => 'Ouvidoria',
        ]));
        self::assertResponseRedirects('/admin/setores');
        $this->client->followRedirect();
        self::assertSelectorTextContains('table', 'OUV');
    }

    public function testFechamentoBloqueiaJustificativasDoMes(): void
    {
        $this->client->loginUser($this->funcionario('rh@classcont.local'), 'admin');
        $crawler = $this->client->request('GET', '/admin/fechamentos');
        $this->client->submit($crawler->selectButton('Fechar mês')->form());
        self::assertResponseRedirects('/admin/fechamentos');

        $erro = $this->api('POST', '/api/justificativas', 'ana@classcont.local', [
            'data' => (new \DateTimeImmutable('last day of last month'))->format('Y-m-d'),
            'tipo' => 'FALTA_JUSTIFICADA',
            'motivo' => 'Tentativa após o fechamento do mês.',
        ]);

        self::assertResponseStatusCodeSame(422);
        self::assertStringContainsString('fechada', $erro['erro']);
    }
}
