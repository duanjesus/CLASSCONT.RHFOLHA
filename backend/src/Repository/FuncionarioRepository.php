<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Funcionario;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;

/**
 * @extends ServiceEntityRepository<Funcionario>
 */
class FuncionarioRepository extends ServiceEntityRepository implements PasswordUpgraderInterface
{
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
            ->join('f.setor', 's')
            ->where('s.chefe = :chefe')
            ->andWhere('f != :chefe')
            ->andWhere('f.ativo = true')
            ->setParameter('chefe', $chefe)
            ->orderBy('f.nome')
            ->getQuery()
            ->getResult();
    }

    /** @return list<Funcionario> */
    public function listagemAdmin(?string $busca): array
    {
        $qb = $this->createQueryBuilder('f')
            ->addSelect('s', 'c')
            ->join('f.setor', 's')
            ->join('f.cargo', 'c')
            ->orderBy('f.nome');

        if (null !== $busca && '' !== trim($busca)) {
            $qb->andWhere('LOWER(f.nome) LIKE :busca OR f.matricula LIKE :busca OR LOWER(f.email) LIKE :busca')
                ->setParameter('busca', '%'.mb_strtolower(trim($busca)).'%');
        }

        /** @var list<Funcionario> */
        return $qb->getQuery()->getResult();
    }

    public function contarAtivos(): int
    {
        return $this->count(['ativo' => true]);
    }
}
