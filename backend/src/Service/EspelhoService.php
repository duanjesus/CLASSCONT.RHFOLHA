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
        return $this->gerarParaVarios([$funcionario], $competencia)[(int) $funcionario->getId()];
    }

    /**
     * Espelhos de vários funcionários com um número FIXO de consultas (batidas,
     * feriados e abonos: 3 no total), em vez de 3 por pessoa. O cálculo em si
     * continua individual, na CalculadoraEspelho.
     *
     * @param list<Funcionario> $funcionarios
     *
     * @return array<int, EspelhoMensal> indexado pelo id do funcionário
     */
    public function gerarParaVarios(array $funcionarios, Competencia $competencia): array
    {
        if ([] === $funcionarios) {
            return [];
        }

        $batidas = $this->registros->porDiaNaCompetenciaDeVarios($funcionarios, $competencia);
        $abonos = $this->justificativas->abonosNaCompetenciaDeVarios($funcionarios, $competencia);
        $feriados = $this->feriados->mapaDaCompetencia($competencia);
        $hoje = \DateTimeImmutable::createFromInterface($this->clock->now());

        $espelhos = [];
        foreach ($funcionarios as $funcionario) {
            $id = (int) $funcionario->getId();
            $espelhos[$id] = $this->calculadora->calcular(
                competencia: $competencia,
                jornadaDiariaMinutos: $funcionario->getJornadaDiariaMinutos(),
                batidasPorDia: $batidas[$id] ?? [],
                feriados: $feriados,
                abonos: $abonos[$id] ?? [],
                hoje: $hoje,
                dataAdmissao: $funcionario->getDataAdmissao(),
            );
        }

        return $espelhos;
    }
}
