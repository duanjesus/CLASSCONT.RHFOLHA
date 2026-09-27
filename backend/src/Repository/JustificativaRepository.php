<?php

declare(strict_types=1);

namespace App\Repository;

use App\Domain\Competencia;
use App\Entity\Funcionario;
use App\Entity\Justificativa;
use App\Enum\StatusAvaliacao;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Justificativa>
 */
class JustificativaRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Justificativa::class);
    }

    /** @return list<Justificativa> */
    public function doFuncionario(Funcionario $funcionario): array
    {
        return $this->findBy(['funcionario' => $funcionario], ['data' => 'DESC', 'id' => 'DESC']);
    }

    /** Existe outra justificativa pendente ou aprovada para o mesmo dia? */
    public function existeAtivaNoDia(Funcionario $funcionario, \DateTimeImmutable $data): bool
    {
        return (int) $this->createQueryBuilder('j')
            ->select('COUNT(j.id)')
            ->where('j.funcionario = :f AND j.data = :d')
            ->andWhere('j.status IN (:status)')
            ->setParameter('f', $funcionario)
            ->setParameter('d', $data->setTime(0, 0))
            ->setParameter('status', [StatusAvaliacao::Pendente, StatusAvaliacao::Aprovada])
            ->getQuery()
            ->getSingleScalarResult() > 0;
    }

    /** @return array<string, string> Y-m-d => rótulo do abono (justificativas aprovadas) */
    public function abonosNaCompetencia(Funcionario $funcionario, Competencia $competencia): array
    {
        /** @var list<Justificativa> $aprovadas */
        $aprovadas = $this->createQueryBuilder('j')
            ->where('j.funcionario = :f AND j.status = :status')
            ->andWhere('j.data >= :inicio AND j.data < :fim')
            ->setParameter('f', $funcionario)
            ->setParameter('status', StatusAvaliacao::Aprovada)
            ->setParameter('inicio', $competencia->primeiroDia())
            ->setParameter('fim', $competencia->inicioDoProximoMes())
            ->getQuery()
            ->getResult();

        $mapa = [];
        foreach ($aprovadas as $j) {
            $mapa[$j->getData()->format('Y-m-d')] = 'Abonado: '.$j->getTipo()->label();
        }

        return $mapa;
    }

    /**
     * Pendentes que o avaliador pode decidir: RH vê todas; chefia vê as do seu setor.
     * Nunca inclui as do próprio avaliador.
     *
     * @return list<Justificativa>
     */
    public function pendentesPara(Funcionario $avaliador): array
    {
        /** @var list<Justificativa> */
        return $this->filtrarPorAvaliador($this->createQueryBuilder('j'), 'j', $avaliador)
            ->andWhere('j.status = :pendente')
            ->setParameter('pendente', StatusAvaliacao::Pendente)
            ->orderBy('j.data', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function contarPendentes(?Funcionario $doFuncionario = null): int
    {
        $qb = $this->createQueryBuilder('j')
            ->select('COUNT(j.id)')
            ->where('j.status = :pendente')
            ->setParameter('pendente', StatusAvaliacao::Pendente);
        if ($doFuncionario) {
            $qb->andWhere('j.funcionario = :f')->setParameter('f', $doFuncionario);
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    /** @return list<Justificativa> */
    public function ultimasPendentes(int $limite): array
    {
        return $this->findBy(['status' => StatusAvaliacao::Pendente], ['criadoEm' => 'DESC'], $limite);
    }

    public static function filtrarPorAvaliador(QueryBuilder $qb, string $alias, Funcionario $avaliador): QueryBuilder
    {
        $qb->join("$alias.funcionario", 'solicitante')
            ->join('solicitante.setor', 'setorSolicitante')
            ->andWhere('solicitante != :avaliador')
            ->setParameter('avaliador', $avaliador);

        if (!$avaliador->isRh()) {
            $qb->andWhere('setorSolicitante.chefe = :avaliador');
        }

        return $qb;
    }
}
