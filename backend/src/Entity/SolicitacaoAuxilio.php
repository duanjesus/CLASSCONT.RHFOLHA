<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\Sentido;
use App\Repository\SolicitacaoAuxilioRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/** Pedido de auxílio-transporte com o itinerário diário (linhas de ida e volta). */
#[ORM\Entity(repositoryClass: SolicitacaoAuxilioRepository::class)]
class SolicitacaoAuxilio
{
    use AvaliavelTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /** @var Collection<int, TrajetoAuxilio> */
    #[ORM\OneToMany(targetEntity: TrajetoAuxilio::class, mappedBy: 'solicitacao', cascade: ['persist'], orphanRemoval: true)]
    #[ORM\OrderBy(['sentido' => 'ASC', 'id' => 'ASC'])]
    private Collection $trajetos;

    public function __construct(
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private Funcionario $funcionario,

        #[ORM\Column]
        private \DateTimeImmutable $criadoEm,
    ) {
        $this->trajetos = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getFuncionario(): Funcionario
    {
        return $this->funcionario;
    }

    public function getCriadoEm(): \DateTimeImmutable
    {
        return $this->criadoEm;
    }

    public function adicionarTrajeto(LinhaOnibus $linha, Sentido $sentido): void
    {
        $this->trajetos->add(new TrajetoAuxilio($this, $linha, $sentido));
    }

    /** @return Collection<int, TrajetoAuxilio> */
    public function getTrajetos(): Collection
    {
        return $this->trajetos;
    }

    /** Soma das tarifas de todas as conduções de um dia, em centavos. */
    public function getValorDiarioCentavos(): int
    {
        $total = 0;
        foreach ($this->trajetos as $trajeto) {
            $total += $trajeto->getLinha()->getTarifaCentavos();
        }

        return $total;
    }
}
