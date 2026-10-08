<?php

declare(strict_types=1);

namespace App\Repository;

use App\Domain\Competencia;
use App\Entity\Funcionario;
use App\Entity\Justificativa;
use App\Enum\StatusAvaliacao;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Justificativa>
 */
class JustificativaRepository extends ServiceEntityRepository
{
    public const POR_PAGINA = 10;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Justificativa::class);
    }

    /**
     * Pedidos do funcionário, do mais recente para o mais antigo, paginados.
     *
     * @return Paginator<Justificativa>
     */
    public function doFuncionario(Funcionario $funcionario, int $pagina): Paginator
    {
        $qb = $this->createQueryBuilder('j')
            ->addSelect('avaliador')
            ->leftJoin('j.avaliadoPor', 'avaliador')
            ->where('j.funcionario = :f')
            ->setParameter('f', $funcionario)
            ->orderBy('j.data', 'DESC')
            ->addOrderBy('j.id', 'DESC')
            ->setFirstResult((max(1, $pagina) - 1) * self::POR_PAGINA)
            ->setMaxResults(self::POR_PAGINA);

        return new Paginator($qb, fetchJoinCollection: false);
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

    /**
     * Abonos do mês de vários funcionários numa única consulta.
     *
     * @param list<Funcionario> $funcionarios
     *
     * @return array<int, array<string, string>> id do funcionário => Y-m-d => rótulo do abono
     */
    public function abonosNaCompetenciaDeVarios(array $funcionarios, Competencia $competencia): array
    {
        if ([] === $funcionarios) {
            return [];
        }

        /** @var list<Justificativa> $aprovadas */
        $aprovadas = $this->createQueryBuilder('j')
            ->where('j.funcionario IN (:fs) AND j.status = :status')
            ->andWhere('j.data >= :inicio AND j.data < :fim')
            ->setParameter('fs', $funcionarios)
            ->setParameter('status', StatusAvaliacao::Aprovada)
            ->setParameter('inicio', $competencia->primeiroDia())
            ->setParameter('fim', $competencia->inicioDoProximoMes())
            ->getQuery()
            ->getResult();

        $mapa = [];
        foreach ($aprovadas as $j) {
            // getId() de uma associação já carregada/proxy não dispara consulta
            $mapa[(int) $j->getFuncionario()->getId()][$j->getData()->format('Y-m-d')] = 'Abonado: '.$j->getTipo()->label();
        }

        return $mapa;
    }

    /**
     * Quantidade de justificativas pendentes de cada funcionário, numa única consulta.
     *
     * @param list<Funcionario> $funcionarios
     *
     * @return array<int, int> id do funcionário => pendentes (quem não tem não aparece)
     */
    public function contarPendentesDeVarios(array $funcionarios): array
    {
        if ([] === $funcionarios) {
            return [];
        }

        /** @var list<array{fid: int|string, total: int|string}> $linhas */
        $linhas = $this->createQueryBuilder('j')
            ->select('IDENTITY(j.funcionario) AS fid', 'COUNT(j.id) AS total')
            ->where('j.funcionario IN (:fs) AND j.status = :pendente')
            ->setParameter('fs', $funcionarios)
            ->setParameter('pendente', StatusAvaliacao::Pendente)
            ->groupBy('j.funcionario')
            ->getQuery()
            ->getArrayResult();

        return array_column(
            array_map(static fn (array $l) => ['fid' => (int) $l['fid'], 'total' => (int) $l['total']], $linhas),
            'total',
            'fid',
        );
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

    public function contarPendentes(): int
    {
        return $this->count(['status' => StatusAvaliacao::Pendente]);
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
