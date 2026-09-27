<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\TipoJustificativa;
use App\Repository\JustificativaRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/** Pedido de abono de um dia do ponto, avaliado pela chefia imediata ou pelo RH. */
#[ORM\Entity(repositoryClass: JustificativaRepository::class)]
#[ORM\Index(name: 'idx_justificativa_funcionario_data', columns: ['funcionario_id', 'data'])]
class Justificativa
{
    use AvaliavelTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    public function __construct(
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private Funcionario $funcionario,

        #[ORM\Column(type: Types::DATE_IMMUTABLE)]
        private \DateTimeImmutable $data,

        #[ORM\Column(length: 30, enumType: TipoJustificativa::class)]
        private TipoJustificativa $tipo,

        #[ORM\Column(type: Types::TEXT)]
        private string $motivo,

        #[ORM\Column]
        private \DateTimeImmutable $criadoEm,
    ) {
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getFuncionario(): Funcionario
    {
        return $this->funcionario;
    }

    public function getData(): \DateTimeImmutable
    {
        return $this->data;
    }

    public function getTipo(): TipoJustificativa
    {
        return $this->tipo;
    }

    public function getMotivo(): string
    {
        return $this->motivo;
    }

    public function getCriadoEm(): \DateTimeImmutable
    {
        return $this->criadoEm;
    }
}
