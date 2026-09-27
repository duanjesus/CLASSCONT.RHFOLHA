<?php

declare(strict_types=1);

namespace App\Service;

use App\Domain\Competencia;
use App\Entity\FechamentoCompetencia;
use App\Entity\Funcionario;
use App\Exception\RegraNegocioException;
use App\Repository\FechamentoCompetenciaRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Clock\ClockInterface;

final class FechamentoService
{
    public function __construct(
        private readonly FechamentoCompetenciaRepository $fechamentos,
        private readonly EntityManagerInterface $em,
        private readonly ClockInterface $clock,
    ) {
    }

    public function fechar(Competencia $competencia, Funcionario $responsavel): void
    {
        $atual = Competencia::daData($this->clock->now());
        if (!$competencia->antesDe($atual)) {
            throw new RegraNegocioException('Só é possível fechar competências já encerradas.');
        }
        if ($this->fechamentos->estaFechada($competencia)) {
            throw new RegraNegocioException('Esta competência já está fechada.');
        }

        $this->em->persist(new FechamentoCompetencia(
            (string) $competencia,
            $responsavel,
            \DateTimeImmutable::createFromInterface($this->clock->now()),
        ));
        $this->em->flush();
    }

    public function reabrir(Competencia $competencia): void
    {
        $fechamento = $this->fechamentos->daCompetencia($competencia)
            ?? throw new RegraNegocioException('Esta competência não está fechada.');

        $this->em->remove($fechamento);
        $this->em->flush();
    }
}
