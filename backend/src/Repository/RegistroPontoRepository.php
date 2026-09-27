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
     * Batidas do mês agrupadas por dia, no formato esperado pela CalculadoraEspelho.
     *
     * @return array<string, list<\DateTimeImmutable>>
     */
    public function porDiaNaCompetencia(Funcionario $funcionario, Competencia $competencia): array
    {
        $porDia = [];
        foreach ($this->doPeriodo($funcionario, $competencia->primeiroDia(), $competencia->inicioDoProximoMes()) as $registro) {
            $porDia[$registro->getMomento()->format('Y-m-d')][] = $registro->getMomento();
        }

        return $porDia;
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
