<?php

declare(strict_types=1);

namespace App\Service;

use App\Domain\Competencia;
use App\Domain\Ponto\CalculadoraEspelho;
use App\Domain\Ponto\EspelhoMensal;
use App\Entity\Funcionario;
use App\Repository\FeriadoRepository;
use App\Repository\JustificativaRepository;
use App\Repository\RegistroPontoRepository;
use Symfony\Component\Clock\ClockInterface;

/** Busca os dados no banco e delega o cálculo à CalculadoraEspelho (classe pura). */
final class EspelhoService
{
    public function __construct(
        private readonly RegistroPontoRepository $registros,
        private readonly FeriadoRepository $feriados,
        private readonly JustificativaRepository $justificativas,
        private readonly CalculadoraEspelho $calculadora,
        private readonly ClockInterface $clock,
    ) {
    }

    public function gerar(Funcionario $funcionario, Competencia $competencia): EspelhoMensal
    {
        return $this->calculadora->calcular(
            competencia: $competencia,
            jornadaDiariaMinutos: $funcionario->getJornadaDiariaMinutos(),
            batidasPorDia: $this->registros->porDiaNaCompetencia($funcionario, $competencia),
            feriados: $this->feriados->mapaDaCompetencia($competencia),
            abonos: $this->justificativas->abonosNaCompetencia($funcionario, $competencia),
            hoje: \DateTimeImmutable::createFromInterface($this->clock->now()),
        );
    }
}
