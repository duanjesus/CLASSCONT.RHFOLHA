<?php

declare(strict_types=1);

namespace App\Entity;

use App\Auditoria\Auditavel;
use App\Repository\SetorRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: SetorRepository::class)]
#[UniqueEntity('sigla', message: 'Já existe um setor com esta sigla.')]
class Setor implements Auditavel
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 120)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 120)]
    private string $nome = '';

    #[ORM\Column(length: 20, unique: true)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 20)]
    private string $sigla = '';

    /** Chefia imediata: avalia justificativas e auxílios dos servidores do setor. */
    #[ORM\ManyToOne(inversedBy: 'setoresChefiados')]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?Funcionario $chefe = null;

    /** @var Collection<int, Funcionario> */
    #[ORM\OneToMany(targetEntity: Funcionario::class, mappedBy: 'setor')]
    #[ORM\OrderBy(['nome' => 'ASC'])]
    private Collection $funcionarios;

    public function __construct()
    {
        $this->funcionarios = new ArrayCollection();
    }

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

    public function getSigla(): string
    {
        return $this->sigla;
    }

    public function setSigla(string $sigla): static
    {
        $this->sigla = mb_strtoupper(trim($sigla));

        return $this;
    }

    public function getChefe(): ?Funcionario
    {
        return $this->chefe;
    }

    public function setChefe(?Funcionario $chefe): static
    {
        $this->chefe = $chefe;

        return $this;
    }

    /** @return Collection<int, Funcionario> */
    public function getFuncionarios(): Collection
    {
        return $this->funcionarios;
    }

    public function __toString(): string
    {
        return \sprintf('%s — %s', $this->sigla, $this->nome);
    }

    public static function tipoAuditoria(): string
    {
        return 'Setor';
    }

    public function rotuloAuditoria(): string
    {
        return \sprintf('%s — %s', $this->sigla, $this->nome);
    }
}
