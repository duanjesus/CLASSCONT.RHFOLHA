<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Funcionario;
use App\Entity\SolicitacaoAuxilio;
use App\Enum\StatusAvaliacao;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SolicitacaoAuxilio>
 */
class SolicitacaoAuxilioRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SolicitacaoAuxilio::class);
    }

    public function vigente(Funcionario $funcionario): ?SolicitacaoAuxilio
    {
        return $this->ultimaComStatus($funcionario, StatusAvaliacao::Aprovada);
    }

    public function pendente(Funcionario $funcionario): ?SolicitacaoAuxilio
    {
        return $this->ultimaComStatus($funcionario, StatusAvaliacao::Pendente);
    }

    /** @return list<SolicitacaoAuxilio> */
    public function pendentesPara(Funcionario $avaliador): array
    {
        /** @var list<SolicitacaoAuxilio> */
        return JustificativaRepository::filtrarPorAvaliador($this->createQueryBuilder('s'), 's', $avaliador)
            ->andWhere('s.status = :pendente')
            ->setParameter('pendente', StatusAvaliacao::Pendente)
            ->orderBy('s.criadoEm', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function contarPendentes(): int
    {
        return $this->count(['status' => StatusAvaliacao::Pendente]);
    }

    /** @return list<SolicitacaoAuxilio> */
    public function vigentes(): array
    {
        /** @var list<SolicitacaoAuxilio> */
        return $this->createQueryBuilder('s')
            ->addSelect('f', 'cargo', 'setor', 't', 'l')
            ->join('s.funcionario', 'f')
            ->join('f.cargo', 'cargo')
            ->join('f.setor', 'setor')
            ->leftJoin('s.trajetos', 't')
            ->leftJoin('t.linha', 'l')
            ->where('s.status = :aprovada')
            ->setParameter('aprovada', StatusAvaliacao::Aprovada)
            ->orderBy('f.nome')
            ->getQuery()
            ->getResult();
    }

    private function ultimaComStatus(Funcionario $funcionario, StatusAvaliacao $status): ?SolicitacaoAuxilio
    {
        return $this->findOneBy(['funcionario' => $funcionario, 'status' => $status], ['criadoEm' => 'DESC']);
    }
}
