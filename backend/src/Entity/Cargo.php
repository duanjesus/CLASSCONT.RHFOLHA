<?php

declare(strict_types=1);

namespace App\Entity;

use App\Auditoria\Auditavel;
use App\Repository\CargoRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: CargoRepository::class)]
#[UniqueEntity('nome', message: 'Já existe um cargo com este nome.')]
class Cargo implements Auditavel
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 120, unique: true)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 120)]
    private string $nome = '';

    /** Valor monetário como string decimal: nunca float para dinheiro. */
    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    #[Assert\NotBlank]
    #[Assert\PositiveOrZero]
    private string $salarioBase = '0.00';

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNome(): string
    {
        return $this->nome;
    }

    public function setNome(string $nome): static
    {
        $this->nome = $nome;

        return $this;
    }

    public function getSalarioBase(): string
    {
        return $this->salarioBase;
    }

    public function setSalarioBase(string $salarioBase): static
    {
        $this->salarioBase = $salarioBase;

        return $this;
    }

    public function getSalarioBaseCentavos(): int
    {
        return (int) round((float) $this->salarioBase * 100);
    }

    public function __toString(): string
    {
        return $this->nome;
    }

    public static function tipoAuditoria(): string
    {
        return 'Cargo';
    }

    public function rotuloAuditoria(): string
    {
        return $this->nome;
    }
}
