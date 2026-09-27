<?php

declare(strict_types=1);

namespace App\Domain\Ponto;

use App\Domain\Competencia;
use App\Enum\SituacaoDia;

/**
 * Monta o espelho de ponto de um mês a partir das batidas, feriados e abonos.
 *
 * Regras:
 *  - Batidas são pareadas na ordem (entrada→saída, retorno→saída...). Batida ímpar
 *    deixa o dia INCOMPLETO: o período aberto não conta.
 *  - Dia útil sem batida e sem abono = FALTA (saldo = −jornada).
 *  - Dia abonado (justificativa aprovada) não gera saldo negativo.
 *  - Fim de semana e feriado têm jornada esperada zero; se houver trabalho, vira saldo positivo.
 *  - Tolerância de 10 min/dia (CLT, art. 58 §1º): diferenças até esse limite são desconsideradas.
 *  - Hoje fica "em andamento" e dias futuros não entram no saldo.
 *  - Dias anteriores à admissão não geram jornada nem falta.
 */
final class CalculadoraEspelho
{
    public const TOLERANCIA_DIARIA_MINUTOS = 10;

    /**
     * @param array<string, list<\DateTimeImmutable>> $batidasPorDia chave Y-m-d, batidas ordenadas
     * @param array<string, string>                   $feriados      chave Y-m-d => descrição
     * @param array<string, string>                   $abonos        chave Y-m-d => motivo do abono
     */
    public function calcular(
        Competencia $competencia,
        int $jornadaDiariaMinutos,
        array $batidasPorDia,
        array $feriados,
        array $abonos,
        \DateTimeImmutable $hoje,
        ?\DateTimeImmutable $dataAdmissao = null,
    ): EspelhoMensal {
        $hojeStr = $hoje->format('Y-m-d');
        $admissaoStr = $dataAdmissao?->format('Y-m-d');
        $dias = [];
        $diasUteis = 0;

        foreach ($competencia->dias() as $data) {
            $chave = $data->format('Y-m-d');
            $batidas = $batidasPorDia[$chave] ?? [];
            $fimDeSemana = (int) $data->format('N') >= 6;
            $feriado = $feriados[$chave] ?? null;
            $diaUtil = !$fimDeSemana && null === $feriado;
            $diasUteis += $diaUtil ? 1 : 0;

            if (null !== $admissaoStr && $chave < $admissaoStr) {
                $dias[] = new DiaEspelho($data, [], 0, 0, 0, SituacaoDia::AntesDaAdmissao);
                continue;
            }

            $dias[] = $this->calcularDia(
                $data,
                $batidas,
                $diaUtil ? $jornadaDiariaMinutos : 0,
                $fimDeSemana,
                $feriado,
                $abonos[$chave] ?? null,
                $chave <=> $hojeStr,
            );
        }

        return new EspelhoMensal($competencia, $jornadaDiariaMinutos, $dias, $diasUteis);
    }

    /**
     * @param list<\DateTimeImmutable> $batidas
     * @param int                      $emRelacaoAHoje -1 passado, 0 hoje, 1 futuro
     */
    private function calcularDia(
        \DateTimeImmutable $data,
        array $batidas,
        int $esperado,
        bool $fimDeSemana,
        ?string $feriado,
        ?string $abono,
        int $emRelacaoAHoje,
    ): DiaEspelho {
        $trabalhado = self::minutosTrabalhados($batidas);

        if (1 === $emRelacaoAHoje) {
            return new DiaEspelho($data, $batidas, 0, 0, 0, SituacaoDia::Futuro, $feriado);
        }
        if (0 === $emRelacaoAHoje) {
            return new DiaEspelho($data, $batidas, $trabalhado, 0, 0, SituacaoDia::EmAndamento, $feriado);
        }
        if (null !== $feriado) {
            return new DiaEspelho($data, $batidas, $trabalhado, 0, $trabalhado, SituacaoDia::Feriado, $feriado);
        }
        if ($fimDeSemana) {
            return new DiaEspelho($data, $batidas, $trabalhado, 0, $trabalhado, SituacaoDia::FimDeSemana);
        }
        if (null !== $abono) {
            // Abono cobre a jornada: só o que exceder vira crédito.
            return new DiaEspelho($data, $batidas, $trabalhado, $esperado, max(0, $trabalhado - $esperado), SituacaoDia::Abonado, $abono);
        }
        if ([] === $batidas) {
            return new DiaEspelho($data, $batidas, 0, $esperado, -$esperado, SituacaoDia::Falta);
        }
        if (1 === \count($batidas) % 2) {
            return new DiaEspelho(
                $data, $batidas, $trabalhado, $esperado, $trabalhado - $esperado,
                SituacaoDia::Incompleto, 'Quantidade ímpar de batidas — registre uma justificativa.',
            );
        }

        $saldo = $trabalhado - $esperado;
        if (abs($saldo) <= self::TOLERANCIA_DIARIA_MINUTOS) {
            $saldo = 0;
        }

        return new DiaEspelho($data, $batidas, $trabalhado, $esperado, $saldo, SituacaoDia::Normal);
    }

    /**
     * Soma os intervalos fechados (pares de batidas). Um período aberto no fim é ignorado.
     *
     * @param list<\DateTimeImmutable> $batidas
     */
    public static function minutosTrabalhados(array $batidas): int
    {
        $total = 0;
        for ($i = 0; $i + 1 < \count($batidas); $i += 2) {
            $total += intdiv($batidas[$i + 1]->getTimestamp() - $batidas[$i]->getTimestamp(), 60);
        }

        return $total;
    }
}
