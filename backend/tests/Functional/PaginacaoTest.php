<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Funcionario;
use App\Entity\Justificativa;
use App\Enum\TipoJustificativa;
use App\Repository\FuncionarioRepository;
use App\Repository\JustificativaRepository;
use Doctrine\ORM\EntityManagerInterface;

final class PaginacaoTest extends ApiTestCase
{
    public function testListaDeFuncionariosDoPainelEPaginadaEPreservaABusca(): void
    {
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $colega = $this->funcionario('ana@classcont.local');
        for ($i = 1; $i <= 30; ++$i) {
            $em->persist((new Funcionario())
                ->setMatricula((string) (800000 + $i))
                ->setNome(\sprintf('Zeta Paginado %02d', $i))
                ->setEmail("zeta$i@classcont.local")
                ->setCargo($colega->getCargo())
                ->setSetor($colega->getSetor())
                ->setDataAdmissao(new \DateTimeImmutable('2020-01-01'))
                ->setPassword('x'));
        }
        $em->flush();

        $this->client->loginUser($this->funcionario('rh@classcont.local'), 'admin');

        $crawler = $this->client->request('GET', '/admin/funcionarios?q=zeta');
        self::assertResponseIsSuccessful();
        self::assertCount(FuncionarioRepository::POR_PAGINA, $crawler->filter('tbody tr'));
        self::assertSelectorTextContains('main', '30 registro(s)');

        // O link da próxima página mantém o termo buscado
        $proxima = $crawler->filter('nav[aria-label="Paginação"] a[rel="next"]');
        self::assertStringContainsString('q=zeta', (string) $proxima->attr('href'));
        self::assertStringContainsString('pagina=2', (string) $proxima->attr('href'));

        $crawler = $this->client->click($proxima->link());
        self::assertCount(10, $crawler->filter('tbody tr'));
        self::assertSelectorTextContains('tbody', 'Zeta Paginado 30');
    }

    public function testMinhasJustificativasVemPaginadasComTotaisNosCabecalhos(): void
    {
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $diego = $this->funcionario('diego@classcont.local');
        for ($i = 1; $i <= 23; ++$i) {
            $em->persist(new Justificativa(
                $diego,
                new \DateTimeImmutable("2025-01-01 +$i days"),
                TipoJustificativa::ServicoExterno,
                "Justificativa de teste número $i.",
                new \DateTimeImmutable(),
            ));
        }
        $em->flush();

        $pagina1 = $this->api('GET', '/api/justificativas', 'diego@classcont.local');
        self::assertResponseHeaderSame('X-Total', '23');
        self::assertResponseHeaderSame('X-Paginas', '3');
        self::assertCount(JustificativaRepository::POR_PAGINA, $pagina1);
        // Mais recentes primeiro
        self::assertSame('2025-01-24', $pagina1[0]['data']);

        $pagina3 = $this->api('GET', '/api/justificativas?pagina=3', 'diego@classcont.local');
        self::assertCount(3, $pagina3);
        self::assertSame('2025-01-02', $pagina3[2]['data']);

        // Página além do fim devolve lista vazia, não erro
        self::assertSame([], $this->api('GET', '/api/justificativas?pagina=99', 'diego@classcont.local'));
        self::assertResponseIsSuccessful();
    }
}
