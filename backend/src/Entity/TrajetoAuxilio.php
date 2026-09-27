<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\Sentido;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class TrajetoAuxilio
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    public function __construct(
        #[ORM\ManyToOne(inversedBy: 'trajetos')]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private SolicitacaoAuxilio $solicitacao,

        #[ORM\ManyToOne]
        #[ORM\JoinColumn(nullable: false)]
        private LinhaOnibus $linha,

        #[ORM\Column(length: 10, enumType: Sentido::class)]
        private Sentido $sentido,
    ) {
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSolicitacao(): SolicitacaoAuxilio
    {
        return $this->solicitacao;
    }

    public function getLinha(): LinhaOnibus
    {
        return $this->linha;
    }

    public function getSentido(): Sentido
    {
        return $this->sentido;
    }
}
