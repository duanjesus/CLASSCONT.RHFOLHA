<?php

declare(strict_types=1);

namespace App\Domain\Ponto;

use App\Domain\Competencia;
use App\Enum\SituacaoDia;

final readonly class EspelhoMensal
{
    public int $trabalhadoMinutos;
    public int $esperadoMinutos;
    public int $saldoMinutos;
    public int $faltas;
    /** Dias em que houve deslocamento (ao menos uma batida): base do auxílio-transporte. */
    public int $diasTrabalhados;
    public int $diasAbonados;

    /**
     * @param list<DiaEspelho> $dias
     * @param int              $diasUteis dias úteis do mês inteiro (seg–sex, fora feriados)
     */
    public function __construct(
        public Competencia $competencia,
        public int $jornadaDiariaMinutos,
        public array $dias,
        public int $diasUteis,
    ) {
        $trabalhado = $esperado = $saldo = $faltas = $trabalhados = $abonados = 0;
        foreach ($dias as $dia) {
            $trabalhado += $dia->trabalhadoMinutos;
            $esperado += $dia->esperadoMinutos;
            $saldo += $dia->saldoMinutos;
            $faltas += SituacaoDia::Falta === $dia->situacao ? 1 : 0;
            $abonados += SituacaoDia::Abonado === $dia->situacao ? 1 : 0;
            $trabalhados += $dia->teveBatida() ? 1 : 0;
        }
        $this->trabalhadoMinutos = $trabalhado;
        $this->esperadoMinutos = $esperado;
        $this->saldoMinutos = $saldo;
        $this->faltas = $faltas;
        $this->diasTrabalhados = $trabalhados;
        $this->diasAbonados = $abonados;
    }
}
