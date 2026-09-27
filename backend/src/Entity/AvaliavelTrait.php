<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\StatusAvaliacao;
use App\Exception\RegraNegocioException;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Fluxo de aprovação compartilhado por Justificativa e SolicitacaoAuxilio.
 * Quem pode avaliar é decidido pelo AvaliacaoVoter; aqui ficam as regras de estado.
 */
trait AvaliavelTrait
{
    #[ORM\Column(length: 20, enumType: StatusAvaliacao::class)]
    private StatusAvaliacao $status = StatusAvaliacao::Pendente;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?Funcionario $avaliadoPor = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $avaliadoEm = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $observacaoAvaliacao = null;

    public function getStatus(): StatusAvaliacao
    {
        return $this->status;
    }

    public function getAvaliadoPor(): ?Funcionario
    {
        return $this->avaliadoPor;
    }

    public function getAvaliadoEm(): ?\DateTimeImmutable
    {
        return $this->avaliadoEm;
    }

    public function getObservacaoAvaliacao(): ?string
    {
        return $this->observacaoAvaliacao;
    }

    public function isPendente(): bool
    {
        return StatusAvaliacao::Pendente === $this->status;
    }

    public function aprovar(Funcionario $avaliador, ?string $observacao, \DateTimeImmutable $quando): void
    {
        $this->registrarAvaliacao(StatusAvaliacao::Aprovada, $avaliador, $observacao, $quando);
    }

    public function recusar(Funcionario $avaliador, ?string $observacao, \DateTimeImmutable $quando): void
    {
        if (null === $observacao || '' === trim($observacao)) {
            throw new RegraNegocioException('Informe o motivo da recusa.');
        }
        $this->registrarAvaliacao(StatusAvaliacao::Recusada, $avaliador, $observacao, $quando);
    }

    public function substituir(): void
    {
        $this->status = StatusAvaliacao::Substituida;
    }

    private function registrarAvaliacao(StatusAvaliacao $status, Funcionario $avaliador, ?string $observacao, \DateTimeImmutable $quando): void
    {
        if (!$this->isPendente()) {
            throw new RegraNegocioException('Este pedido já foi avaliado.');
        }
        $this->status = $status;
        $this->avaliadoPor = $avaliador;
        $this->avaliadoEm = $quando;
        $this->observacaoAvaliacao = $observacao ? trim($observacao) : null;
    }
}
