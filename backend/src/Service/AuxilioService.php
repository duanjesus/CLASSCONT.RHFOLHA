<?php

declare(strict_types=1);

namespace App\Service;

use App\Domain\Auxilio\CalculadoraAuxilio;
use App\Domain\Auxilio\DemonstrativoAuxilio;
use App\Domain\Competencia;
use App\Entity\Funcionario;
use App\Entity\SolicitacaoAuxilio;
use App\Enum\Sentido;
use App\Exception\RegraNegocioException;
use App\Repository\LinhaOnibusRepository;
use App\Repository\SolicitacaoAuxilioRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Clock\ClockInterface;

final class AuxilioService
{
    public const MAX_CONDUCOES_DIA = 6;

    public function __construct(
        private readonly SolicitacaoAuxilioRepository $solicitacoes,
        private readonly LinhaOnibusRepository $linhas,
        private readonly EspelhoService $espelhos,
        private readonly CalculadoraAuxilio $calculadora,
        private readonly Notificador $notificador,
        private readonly EntityManagerInterface $em,
        private readonly ClockInterface $clock,
        private readonly float $percentualDescontoAuxilio,
    ) {
    }

    /**
     * Cria um novo pedido. Um pedido pendente anterior é substituído; o vigente
     * (aprovado) continua valendo até o novo ser aprovado.
     *
     * @param list<array{linhaId: int, sentido: Sentido}> $trajetos
     */
    public function solicitar(Funcionario $funcionario, array $trajetos): SolicitacaoAuxilio
    {
        if ([] === $trajetos) {
            throw new RegraNegocioException('Informe ao menos uma condução.');
        }
        if (\count($trajetos) > self::MAX_CONDUCOES_DIA) {
            throw new RegraNegocioException(\sprintf('Máximo de %d conduções por dia.', self::MAX_CONDUCOES_DIA));
        }
        $sentidos = array_map(static fn (array $t) => $t['sentido'], $trajetos);
        if (!\in_array(Sentido::Ida, $sentidos, true) || !\in_array(Sentido::Volta, $sentidos, true)) {
            throw new RegraNegocioException('O itinerário precisa ter ao menos uma condução de ida e uma de volta.');
        }

        $solicitacao = new SolicitacaoAuxilio($funcionario, $this->agora());
        foreach ($trajetos as $trajeto) {
            $linha = $this->linhas->find($trajeto['linhaId']);
            if (null === $linha || !$linha->isAtiva()) {
                throw new RegraNegocioException(\sprintf('Linha #%d inexistente ou inativa.', $trajeto['linhaId']));
            }
            $solicitacao->adicionarTrajeto($linha, $trajeto['sentido']);
        }

        $this->solicitacoes->pendente($funcionario)?->substituir();
        $this->em->persist($solicitacao);
        $this->em->flush();

        return $solicitacao;
    }

    public function avaliar(SolicitacaoAuxilio $solicitacao, Funcionario $avaliador, bool $aprovar, ?string $observacao): void
    {
        if ($aprovar) {
            // Valida e aprova primeiro; só então o vigente anterior deixa de valer
            // (se a aprovação falhar, nada muda).
            $anterior = $this->solicitacoes->vigente($solicitacao->getFuncionario());
            $solicitacao->aprovar($avaliador, $observacao, $this->agora());
            $anterior?->substituir();
        } else {
            $solicitacao->recusar($avaliador, $observacao, $this->agora());
        }
        $this->em->flush();

        $this->notificador->resultadoAvaliacao(
            destinatario: $solicitacao->getFuncionario(),
            assunto: 'Auxílio-transporte '.mb_strtolower($solicitacao->getStatus()->label()),
            descricaoPedido: \sprintf('Solicitação de auxílio-transporte de %s', $solicitacao->getCriadoEm()->format('d/m/Y')),
            status: $solicitacao->getStatus(),
            avaliador: $avaliador,
            observacao: $solicitacao->getObservacaoAvaliacao(),
        );
    }

    /** Null quando o funcionário não tem auxílio aprovado. */
    public function demonstrativo(Funcionario $funcionario, Competencia $competencia): ?DemonstrativoAuxilio
    {
        $vigente = $this->solicitacoes->vigente($funcionario);
        if (null === $vigente) {
            return null;
        }

        // Os dias trabalhados vêm do ponto: é aqui que os dois módulos se conectam.
        $espelho = $this->espelhos->gerar($funcionario, $competencia);

        return $this->calculadora->calcular(
            competencia: $competencia,
            valorDiarioCentavos: $vigente->getValorDiarioCentavos(),
            diasTrabalhados: $espelho->diasTrabalhados,
            diasUteis: $espelho->diasUteis,
            salarioBaseCentavos: $funcionario->getCargo()?->getSalarioBaseCentavos() ?? 0,
            percentualDesconto: $this->percentualDescontoAuxilio,
        );
    }

    private function agora(): \DateTimeImmutable
    {
        return \DateTimeImmutable::createFromInterface($this->clock->now());
    }
}
