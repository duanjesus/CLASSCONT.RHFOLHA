<?php

declare(strict_types=1);

namespace App\Service;

use App\Domain\Competencia;
use App\Entity\Funcionario;
use App\Entity\RegistroPonto;
use App\Enum\TipoBatida;
use App\Exception\RegraNegocioException;
use App\Repository\FechamentoCompetenciaRepository;
use App\Repository\RegistroPontoRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Clock\ClockInterface;

final class PontoService
{
    public const MAX_BATIDAS_DIA = 4;
    /** Intervalo mínimo entre batidas, para evitar duplo clique. */
    public const INTERVALO_MINIMO_SEGUNDOS = 60;

    public function __construct(
        private readonly RegistroPontoRepository $registros,
        private readonly FechamentoCompetenciaRepository $fechamentos,
        private readonly EntityManagerInterface $em,
        private readonly ClockInterface $clock,
    ) {
    }

    /**
     * @return array{data: string, batidas: list<string>, proximaBatida: ?string, competenciaFechada: bool}
     */
    public function situacaoDeHoje(Funcionario $funcionario): array
    {
        $agora = $this->clock->now();
        $batidas = $this->registros->doDia($funcionario, $agora);

        return [
            'data' => $agora->format('Y-m-d'),
            'batidas' => array_map(static fn (RegistroPonto $r) => $r->getMomento()->format('H:i'), $batidas),
            'proximaBatida' => TipoBatida::proxima(\count($batidas))?->value,
            'competenciaFechada' => $this->fechamentos->estaFechada(Competencia::daData($agora)),
        ];
    }

    public function bater(Funcionario $funcionario): RegistroPonto
    {
        $agora = $this->clock->now();

        if (!$funcionario->isAtivo()) {
            throw new RegraNegocioException('Funcionário inativo não pode registrar ponto.');
        }
        if ($this->fechamentos->estaFechada(Competencia::daData($agora))) {
            throw new RegraNegocioException('A competência atual já foi fechada pelo RH.');
        }

        $batidasHoje = $this->registros->doDia($funcionario, $agora);
        if (\count($batidasHoje) >= self::MAX_BATIDAS_DIA) {
            throw new RegraNegocioException('Todas as batidas do dia já foram registradas.');
        }

        $ultima = end($batidasHoje);
        if ($ultima && $agora->getTimestamp() - $ultima->getMomento()->getTimestamp() < self::INTERVALO_MINIMO_SEGUNDOS) {
            throw new RegraNegocioException('Aguarde ao menos 1 minuto entre uma batida e outra.');
        }

        // Precisão de minuto: é o que o espelho exibe e evita saldos "quebrados" em segundos.
        $registro = new RegistroPonto($funcionario, \DateTimeImmutable::createFromInterface($agora)->setTime(
            (int) $agora->format('H'),
            (int) $agora->format('i'),
        ));
        $this->em->persist($registro);
        $this->em->flush();

        return $registro;
    }
}
