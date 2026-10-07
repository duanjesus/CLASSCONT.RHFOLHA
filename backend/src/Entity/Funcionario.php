<?php

declare(strict_types=1);

namespace App\Entity;

use App\Auditoria\Auditavel;
use App\Repository\FuncionarioRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: FuncionarioRepository::class)]
#[UniqueEntity('email', message: 'Este e-mail já está em uso.')]
#[UniqueEntity('matricula', message: 'Esta matrícula já está em uso.')]
class Funcionario implements UserInterface, PasswordAuthenticatedUserInterface, Auditavel
{
    public const ROLE_FUNCIONARIO = 'ROLE_FUNCIONARIO';
    public const ROLE_CHEFIA = 'ROLE_CHEFIA';
    public const ROLE_RH = 'ROLE_RH';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 20, unique: true)]
    #[Assert\NotBlank]
    #[Assert\Regex('/^\d{4,10}$/', message: 'A matrícula deve ter de 4 a 10 dígitos.')]
    private string $matricula = '';

    #[ORM\Column(length: 150)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 150)]
    private string $nome = '';

    #[ORM\Column(length: 180, unique: true)]
    #[Assert\NotBlank]
    #[Assert\Email]
    private string $email = '';

    #[ORM\Column]
    private string $password = '';

    /**
     * Papéis atribuídos manualmente (hoje só ROLE_RH).
     *
     * @var list<string>
     */
    #[ORM\Column(type: Types::JSON)]
    private array $roles = [];

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull]
    private ?Cargo $cargo = null;

    #[ORM\ManyToOne(inversedBy: 'funcionarios')]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull]
    private ?Setor $setor = null;

    /** Carga horária diária esperada, em minutos (480 = 8h). */
    #[ORM\Column(type: Types::SMALLINT)]
    #[Assert\Range(min: 240, max: 600, notInRangeMessage: 'A jornada deve ficar entre 4h e 10h.')]
    private int $jornadaDiariaMinutos = 480;

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    #[Assert\NotNull]
    private ?\DateTimeImmutable $dataAdmissao = null;

    #[ORM\Column]
    private bool $ativo = true;

    /** @var Collection<int, Setor> */
    #[ORM\OneToMany(targetEntity: Setor::class, mappedBy: 'chefe')]
    private Collection $setoresChefiados;

    public function __construct()
    {
        $this->setoresChefiados = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getMatricula(): string
    {
        return $this->matricula;
    }

    public function setMatricula(string $matricula): static
    {
        $this->matricula = trim($matricula);

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

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = mb_strtolower(trim($email));

        return $this;
    }

    public function getUserIdentifier(): string
    {
        \assert('' !== $this->email);

        return $this->email;
    }

    /**
     * ROLE_FUNCIONARIO é de todos; ROLE_CHEFIA é derivado de chefiar algum setor,
     * assim não existe risco de o papel ficar dessincronizado do cadastro de setores.
     *
     * @return list<string>
     */
    public function getRoles(): array
    {
        $roles = $this->roles;
        $roles[] = self::ROLE_FUNCIONARIO;
        if (!$this->setoresChefiados->isEmpty()) {
            $roles[] = self::ROLE_CHEFIA;
        }

        return array_values(array_unique($roles));
    }

    /** @param list<string> $roles */
    public function setRoles(array $roles): static
    {
        $this->roles = array_values(array_diff($roles, [self::ROLE_FUNCIONARIO, self::ROLE_CHEFIA]));

        return $this;
    }

    public function isRh(): bool
    {
        return \in_array(self::ROLE_RH, $this->roles, true);
    }

    public function setRh(bool $rh): static
    {
        $this->setRoles($rh ? [self::ROLE_RH] : []);

        return $this;
    }

    public function getPassword(): string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;

        return $this;
    }

    public function eraseCredentials(): void
    {
    }

    public function getCargo(): ?Cargo
    {
        return $this->cargo;
    }

    public function setCargo(?Cargo $cargo): static
    {
        $this->cargo = $cargo;

        return $this;
    }

    public function getSetor(): ?Setor
    {
        return $this->setor;
    }

    public function setSetor(?Setor $setor): static
    {
        $this->setor = $setor;

        return $this;
    }

    public function getJornadaDiariaMinutos(): int
    {
        return $this->jornadaDiariaMinutos;
    }

    public function setJornadaDiariaMinutos(int $minutos): static
    {
        $this->jornadaDiariaMinutos = $minutos;

        return $this;
    }

    public function getDataAdmissao(): ?\DateTimeImmutable
    {
        return $this->dataAdmissao;
    }

    public function setDataAdmissao(?\DateTimeImmutable $dataAdmissao): static
    {
        $this->dataAdmissao = $dataAdmissao;

        return $this;
    }

    public function isAtivo(): bool
    {
        return $this->ativo;
    }

    public function setAtivo(bool $ativo): static
    {
        $this->ativo = $ativo;

        return $this;
    }

    /** @return Collection<int, Setor> */
    public function getSetoresChefiados(): Collection
    {
        return $this->setoresChefiados;
    }

    public function isChefia(): bool
    {
        return !$this->setoresChefiados->isEmpty();
    }

    /** Verdadeiro se este funcionário é a chefia imediata de $outro. */
    public function chefia(self $outro): bool
    {
        return $outro !== $this
            && null !== $outro->getSetor()
            && $outro->getSetor()->getChefe() === $this;
    }

    public function __toString(): string
    {
        return \sprintf('%s (%s)', $this->nome, $this->matricula);
    }

    public static function tipoAuditoria(): string
    {
        return 'Funcionário';
    }

    public function rotuloAuditoria(): string
    {
        return \sprintf('%s (%s)', $this->nome, $this->matricula);
    }
}
