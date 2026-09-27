<?php

declare(strict_types=1);

namespace App\Repository;

use App\Domain\Competencia;
use App\Entity\FechamentoCompetencia;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<FechamentoCompetencia>
 */
class FechamentoCompetenciaRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, FechamentoCompetencia::class);
    }

    public function daCompetencia(Competencia $competencia): ?FechamentoCompetencia
    {
        return $this->findOneBy(['competencia' => (string) $competencia]);
    }

    public function estaFechada(Competencia $competencia): bool
    {
        return null !== $this->daCompetencia($competencia);
    }
}
