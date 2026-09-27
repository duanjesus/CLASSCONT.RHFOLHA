<?php

declare(strict_types=1);

namespace App\Repository;

use App\Domain\Competencia;
use App\Entity\Feriado;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Feriado>
 */
class FeriadoRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Feriado::class);
    }

    /** @return array<string, string> Y-m-d => descrição */
    public function mapaDaCompetencia(Competencia $competencia): array
    {
        /** @var list<Feriado> $feriados */
        $feriados = $this->createQueryBuilder('f')
            ->where('f.data >= :inicio AND f.data < :fim')
            ->setParameter('inicio', $competencia->primeiroDia())
            ->setParameter('fim', $competencia->inicioDoProximoMes())
            ->getQuery()
            ->getResult();

        $mapa = [];
        foreach ($feriados as $feriado) {
            $mapa[$feriado->getData()?->format('Y-m-d') ?? ''] = $feriado->getDescricao();
        }

        return $mapa;
    }

    /** @return list<Feriado> */
    public function doAno(int $ano): array
    {
        /** @var list<Feriado> */
        return $this->createQueryBuilder('f')
            ->where('f.data >= :inicio AND f.data < :fim')
            ->setParameter('inicio', new \DateTimeImmutable("$ano-01-01"))
            ->setParameter('fim', new \DateTimeImmutable(($ano + 1).'-01-01'))
            ->orderBy('f.data')
            ->getQuery()
            ->getResult();
    }
}
