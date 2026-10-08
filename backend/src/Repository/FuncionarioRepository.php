<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Funcionario;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;

/**
 * @extends ServiceEntityRepository<Funcionario>
 */
class FuncionarioRepository extends ServiceEntityRepository implements PasswordUpgraderInterface
{
    public const POR_PAGINA = 20;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Funcionario::class);
    }

    /** Rehash automático quando o algoritmo de senha evolui. */
    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        if (!$user instanceof Funcionario) {
            throw new UnsupportedUserException(\sprintf('Instances of "%s" are not supported.', $user::class));
        }
        $user->setPassword($newHashedPassword);
        $this->getEntityManager()->flush();
    }

    /**
     * Servidores ativos dos setores chefiados, exceto o próprio chefe.
     *
     * @return list<Funcionario>
     */
    public function equipeDe(Funcionario $chefe): array
    {
        /** @var list<Funcionario> */
        return $this->createQueryBuilder('f')
            ->addSelect('c')
            ->join('f.setor', 's')
            ->join('f.cargo', 'c')
            ->where('s.chefe = :chefe')
            ->andWhere('f != :chefe')
            ->andWhere('f.ativo = true')
            ->setParameter('chefe', $chefe)
            ->orderBy('f.nome')
            ->getQuery()
            ->getResult();
    }

    /**
     * Listagem paginada do painel. Setor, cargo e setores chefiados (para o selo
     * "Chefia") já vêm juntos, sem consultas extras por linha.
     *
     * @return Paginator<Funcionario>
     */
    public function listagemAdmin(?string $busca, int $pagina): Paginator
    {
        $qb = $this->createQueryBuilder('f')
            ->addSelect('s', 'c', 'chefiados')
            ->join('f.setor', 's')
            ->join('f.cargo', 'c')
            ->leftJoin('f.setoresChefiados', 'chefiados')
            ->orderBy('f.nome')
            ->addOrderBy('f.id');

        if (null !== $busca && '' !== trim($busca)) {
            $qb->andWhere('LOWER(f.nome) LIKE :busca OR f.matricula LIKE :busca OR LOWER(f.email) LIKE :busca')
                ->setParameter('busca', '%'.mb_strtolower(trim($busca)).'%');
        }

        $qb->setFirstResult((max(1, $pagina) - 1) * self::POR_PAGINA)->setMaxResults(self::POR_PAGINA);

        // fetchJoinCollection: há join com coleção, então o Paginator limita por
        // funcionário (e não por linha do SQL)
        return new Paginator($qb, fetchJoinCollection: true);
    }

    public function contarAtivos(): int
    {
        return $this->count(['ativo' => true]);
    }
}
