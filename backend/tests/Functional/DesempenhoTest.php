<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Funcionario;
use App\Entity\SolicitacaoAuxilio;
use App\Enum\Sentido;
use App\Repository\LinhaOnibusRepository;
use Doctrine\Bundle\DoctrineBundle\DataCollector\DoctrineDataCollector;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Garante que as telas que calculam um espelho por pessoa não fazem N+1:
 * o número de consultas ao banco não pode crescer com a quantidade de servidores.
 */
final class DesempenhoTest extends ApiTestCase
{
    public function testConsultasDaEquipeNaoCrescemComOTamanhoDaEquipe(): void
    {
        $token = $this->token('chefe.ti@classcont.local');
        $pedir = function () use ($token): int {
            $this->client->enableProfiler();
            $this->client->jsonRequest('GET', '/api/equipe', server: ['HTTP_AUTHORIZATION' => 'Bearer '.$token]);
            self::assertResponseIsSuccessful();

            return $this->consultas();
        };

        $antes = $pedir();
        $equipeAntes = \count($this->json());

        $this->contratar(10, 'chefe.ti@classcont.local');

        $depois = $pedir();
        self::assertCount($equipeAntes + 10, $this->json());
        self::assertLessThanOrEqual($antes, $depois, "A equipe cresceu em 10 pessoas e as consultas foram de $antes para $depois.");
    }

    public function testConsultasDaFolhaDoAuxilioNaoCrescemComOsBeneficiarios(): void
    {
        $this->client->loginUser($this->funcionario('rh@classcont.local'), 'admin');
        $pedir = function (): int {
            $this->client->enableProfiler();
            $this->client->request('GET', '/admin/relatorios/auxilio-transporte');
            self::assertResponseIsSuccessful();

            return $this->consultas();
        };

        $antes = $pedir();

        $em = static::getContainer()->get(EntityManagerInterface::class);
        $linha = static::getContainer()->get(LinhaOnibusRepository::class)->findOneBy(['codigo' => '101']);
        self::assertNotNull($linha);
        $rh = $this->funcionario('rh@classcont.local');
        foreach ($this->contratar(8, 'chefe.ti@classcont.local') as $novo) {
            $solicitacao = new SolicitacaoAuxilio($novo, new \DateTimeImmutable('-40 days'));
            $solicitacao->adicionarTrajeto($linha, Sentido::Ida);
            $solicitacao->adicionarTrajeto($linha, Sentido::Volta);
            $solicitacao->aprovar($rh, null, new \DateTimeImmutable('-39 days'));
            $em->persist($solicitacao);
        }
        $em->flush();
        $em->clear();

        $depois = $pedir();
        self::assertLessThanOrEqual($antes, $depois, "Entraram 8 beneficiários e as consultas foram de $antes para $depois.");
    }

    /**
     * Cria servidores no mesmo setor e cargo de um colega existente.
     *
     * @return list<Funcionario>
     */
    private function contratar(int $quantos, string $emailDoColega): array
    {
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $colega = $this->funcionario($emailDoColega);
        $novos = [];
        for ($i = 1; $i <= $quantos; ++$i) {
            $novos[] = $f = (new Funcionario())
                ->setMatricula((string) (900000 + $i))
                ->setNome("Servidor Teste $i")
                ->setEmail("teste$i@classcont.local")
                ->setCargo($colega->getCargo())
                ->setSetor($colega->getSetor())
                ->setDataAdmissao(new \DateTimeImmutable('2020-01-01'))
                ->setPassword('x');
            $em->persist($f);
        }
        $em->flush();

        return $novos;
    }

    private function consultas(): int
    {
        $coletor = $this->client->getProfile()?->getCollector('db');
        self::assertInstanceOf(DoctrineDataCollector::class, $coletor);

        return $coletor->getQueryCount();
    }
}
