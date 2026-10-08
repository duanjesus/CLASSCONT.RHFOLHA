<?php

declare(strict_types=1);

namespace App\Repository;

use App\Domain\Competencia;
use App\Entity\Funcionario;
use App\Entity\RegistroPonto;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<RegistroPonto>
 */
class RegistroPontoRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RegistroPonto::class);
    }

    /** @return list<RegistroPonto> */
    public function doDia(Funcionario $funcionario, \DateTimeImmutable $dia): array
    {
        $inicio = $dia->setTime(0, 0);

        return $this->doPeriodo($funcionario, $inicio, $inicio->modify('+1 day'));
    }

    /**
     * Batidas do mês de VÁRIOS funcionários numa única consulta (evita N+1 nas
     * telas de equipe e de folha). Seleciona só as duas colunas necessárias.
     *
     * @param list<Funcionario> $funcionarios
     *
     * @return array<int, array<string, list<\DateTimeImmutable>>> id do funcionário => dia => batidas
     */
    public function porDiaNaCompetenciaDeVarios(array $funcionarios, Competencia $competencia): array
    {
        if ([] === $funcionarios) {
            return [];
        }

        /** @var list<array{fid: int|string, momento: \DateTimeImmutable}> $linhas */
        $linhas = $this->createQueryBuilder('r')
            ->select('IDENTITY(r.funcionario) AS fid', 'r.momento')
            ->where('r.funcionario IN (:fs)')
            ->andWhere('r.momento >= :inicio AND r.momento < :fim')
            ->setParameter('fs', $funcionarios)
            ->setParameter('inicio', $competencia->primeiroDia())
            ->setParameter('fim', $competencia->inicioDoProximoMes())
            ->orderBy('r.momento', 'ASC')
            ->getQuery()
            ->getArrayResult();

        $resultado = [];
        foreach ($linhas as $linha) {
            $resultado[(int) $linha['fid']][$linha['momento']->format('Y-m-d')][] = $linha['momento'];
        }

        return $resultado;
    }

    /** @return list<RegistroPonto> */
    private function doPeriodo(Funcionario $funcionario, \DateTimeImmutable $inicio, \DateTimeImmutable $fim): array
    {
        /** @var list<RegistroPonto> */
        return $this->createQueryBuilder('r')
            ->where('r.funcionario = :f')
            ->andWhere('r.momento >= :inicio AND r.momento < :fim')
            ->setParameter('f', $funcionario)
            ->setParameter('inicio', $inicio)
            ->setParameter('fim', $fim)
            ->orderBy('r.momento', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
