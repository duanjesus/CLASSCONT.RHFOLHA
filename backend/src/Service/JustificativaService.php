<?php

declare(strict_types=1);

namespace App\Service;

use App\Domain\Competencia;
use App\Entity\Funcionario;
use App\Entity\Justificativa;
use App\Enum\TipoJustificativa;
use App\Exception\RegraNegocioException;
use App\Repository\FechamentoCompetenciaRepository;
use App\Repository\JustificativaRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Clock\ClockInterface;

final class JustificativaService
{
    /** Prazo para justificar um dia, contado a partir dele. */
    public const PRAZO_DIAS = 30;

    public function __construct(
        private readonly JustificativaRepository $justificativas,
        private readonly FechamentoCompetenciaRepository $fechamentos,
        private readonly EntityManagerInterface $em,
        private readonly Notificador $notificador,
        private readonly ClockInterface $clock,
    ) {
    }

    public function registrar(Funcionario $funcionario, \DateTimeImmutable $data, TipoJustificativa $tipo, string $motivo): Justificativa
    {
        $data = $data->setTime(0, 0);
        $hoje = \DateTimeImmutable::createFromInterface($this->clock->now())->setTime(0, 0);

        if ($data > $hoje) {
            throw new RegraNegocioException('Não é possível justificar uma data futura.');
        }
        $this->garantirCompetenciaAberta($data);
        if ($data < $hoje->modify(\sprintf('-%d days', self::PRAZO_DIAS))) {
            throw new RegraNegocioException(\sprintf('O prazo para justificar é de %d dias.', self::PRAZO_DIAS));
        }
        if ($this->justificativas->existeAtivaNoDia($funcionario, $data)) {
            throw new RegraNegocioException('Já existe uma justificativa pendente ou aprovada para este dia.');
        }

        $justificativa = new Justificativa($funcionario, $data, $tipo, trim($motivo), $this->agora());
        $this->em->persist($justificativa);
        $this->em->flush();

        return $justificativa;
    }

    /** Permissão já foi verificada pelo AvaliacaoVoter no controller. */
    public function avaliar(Justificativa $justificativa, Funcionario $avaliador, bool $aprovar, ?string $observacao): void
    {
        $this->garantirCompetenciaAberta($justificativa->getData());

        if ($aprovar) {
            $justificativa->aprovar($avaliador, $observacao, $this->agora());
        } else {
            $justificativa->recusar($avaliador, $observacao, $this->agora());
        }
        $this->em->flush();

        $this->notificador->resultadoAvaliacao(
            destinatario: $justificativa->getFuncionario(),
            assunto: \sprintf('Justificativa de %s %s', $justificativa->getData()->format('d/m/Y'), mb_strtolower($justificativa->getStatus()->label())),
            descricaoPedido: \sprintf('Justificativa (%s) do dia %s', $justificativa->getTipo()->label(), $justificativa->getData()->format('d/m/Y')),
            status: $justificativa->getStatus(),
            avaliador: $avaliador,
            observacao: $justificativa->getObservacaoAvaliacao(),
        );
    }

    private function garantirCompetenciaAberta(\DateTimeImmutable $data): void
    {
        if ($this->fechamentos->estaFechada(Competencia::daData($data))) {
            throw new RegraNegocioException('A competência desta data já foi fechada pelo RH.');
        }
    }

    private function agora(): \DateTimeImmutable
    {
        return \DateTimeImmutable::createFromInterface($this->clock->now());
    }
}
