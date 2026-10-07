<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\RegistroAuditoria;
use App\Enum\AcaoAuditoria;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<RegistroAuditoria>
 */
class RegistroAuditoriaRepository extends ServiceEntityRepository
{
    public const POR_PAGINA = 25;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RegistroAuditoria::class);
    }

    /**
     * Consulta paginada, do mais recente para o mais antigo.
     *
     * @param array{entidade?: string, id?: string, acao?: ?AcaoAuditoria, autor?: string, de?: ?\DateTimeImmutable, ate?: ?\DateTimeImmutable} $filtros
     *
     * @return Paginator<RegistroAuditoria>
     */
    public function buscar(array $filtros, int $pagina): Paginator
    {
        $qb = $this->createQueryBuilder('a')->orderBy('a.ocorridoEm', 'DESC')->addOrderBy('a.id', 'DESC');

        if ('' !== ($filtros['entidade'] ?? '')) {
            $qb->andWhere('a.entidade = :entidade')->setParameter('entidade', $filtros['entidade']);
        }
        if ('' !== ($filtros['id'] ?? '')) {
            $qb->andWhere('a.entidadeId = :id')->setParameter('id', $filtros['id']);
        }
        if (null !== ($filtros['acao'] ?? null)) {
            $qb->andWhere('a.acao = :acao')->setParameter('acao', $filtros['acao']);
        }
        if ('' !== ($filtros['autor'] ?? '')) {
            $qb->andWhere('LOWER(a.autorNome) LIKE :autor')->setParameter('autor', '%'.mb_strtolower($filtros['autor']).'%');
        }
        if (null !== ($filtros['de'] ?? null)) {
            $qb->andWhere('a.ocorridoEm >= :de')->setParameter('de', $filtros['de']->setTime(0, 0));
        }
        if (null !== ($filtros['ate'] ?? null)) {
            // "até" é inclusivo: vale o dia inteiro
            $qb->andWhere('a.ocorridoEm < :ate')->setParameter('ate', $filtros['ate']->setTime(0, 0)->modify('+1 day'));
        }

        $qb->setFirstResult((max(1, $pagina) - 1) * self::POR_PAGINA)->setMaxResults(self::POR_PAGINA);

        return new Paginator($qb, fetchJoinCollection: false);
    }

    /** @return list<string> tipos que já têm algum registro, para o filtro da tela */
    public function entidadesRegistradas(): array
    {
        /** @var list<string> */
        return $this->createQueryBuilder('a')
            ->select('DISTINCT a.entidade')
            ->orderBy('a.entidade')
            ->getQuery()
            ->getSingleColumnResult();
    }
}
