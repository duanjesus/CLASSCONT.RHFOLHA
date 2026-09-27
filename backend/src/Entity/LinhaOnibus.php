<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\LinhaOnibusRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: LinhaOnibusRepository::class)]
#[UniqueEntity('codigo', message: 'Já existe uma linha com este código.')]
class LinhaOnibus
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 20, unique: true)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 20)]
    private string $codigo = '';

    #[ORM\Column(length: 150)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 150)]
    private string $nome = '';

    #[ORM\Column(type: Types::DECIMAL, precision: 8, scale: 2)]
    #[Assert\NotBlank]
    #[Assert\Positive]
    private string $tarifa = '0.00';

    #[ORM\Column]
    private bool $ativa = true;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCodigo(): string
    {
        return $this->codigo;
    }

    public function setCodigo(string $codigo): static
    {
        $this->codigo = trim($codigo);

        return $this;
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

    public function getTarifa(): string
    {
        return $this->tarifa;
    }

    public function setTarifa(string $tarifa): static
    {
        $this->tarifa = $tarifa;

        return $this;
    }

    public function getTarifaCentavos(): int
    {
        return (int) round((float) $this->tarifa * 100);
    }

    public function isAtiva(): bool
    {
        return $this->ativa;
    }

    public function setAtiva(bool $ativa): static
    {
        $this->ativa = $ativa;

        return $this;
    }

    public function __toString(): string
    {
        return \sprintf('%s — %s', $this->codigo, $this->nome);
    }
}
