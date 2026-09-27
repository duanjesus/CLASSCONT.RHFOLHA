<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\RegistroPontoRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/** Uma batida de ponto. O tipo (entrada/saída) é deduzido pela ordem no dia. */
#[ORM\Entity(repositoryClass: RegistroPontoRepository::class)]
#[ORM\Index(name: 'idx_ponto_funcionario_momento', columns: ['funcionario_id', 'momento'])]
class RegistroPonto
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    public function __construct(
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private Funcionario $funcionario,

        #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
        private \DateTimeImmutable $momento,
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

    public function getMomento(): \DateTimeImmutable
    {
        return $this->momento;
    }
}
