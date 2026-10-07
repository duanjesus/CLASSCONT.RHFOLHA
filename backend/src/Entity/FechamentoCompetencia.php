<?php

declare(strict_types=1);

namespace App\Entity;

use App\Auditoria\Auditavel;
use App\Repository\FechamentoCompetenciaRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Marca um mês como fechado pelo RH: a partir daí o ponto daquele mês
 * não aceita novas justificativas nem avaliações (vai para a folha).
 */
#[ORM\Entity(repositoryClass: FechamentoCompetenciaRepository::class)]
class FechamentoCompetencia implements Auditavel
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    public function __construct(
        /** Formato YYYY-MM */
        #[ORM\Column(length: 7, unique: true)]
        private string $competencia,

        #[ORM\ManyToOne]
        #[ORM\JoinColumn(onDelete: 'SET NULL')]
        private ?Funcionario $fechadoPor,

        #[ORM\Column]
        private \DateTimeImmutable $fechadoEm,
    ) {
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCompetencia(): string
    {
        return $this->competencia;
    }

    public function getFechadoPor(): ?Funcionario
    {
        return $this->fechadoPor;
    }

    public function getFechadoEm(): \DateTimeImmutable
    {
        return $this->fechadoEm;
    }

    public static function tipoAuditoria(): string
    {
        return 'Fechamento mensal';
    }

    public function rotuloAuditoria(): string
    {
        return 'Competência '.$this->competencia;
    }
}
