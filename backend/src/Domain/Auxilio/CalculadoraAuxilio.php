<?php

declare(strict_types=1);

namespace App\Domain\Auxilio;

use App\Domain\Competencia;

/**
 * Cálculo mensal do auxílio-transporte, no modelo usado no serviço público:
 *
 *   bruto    = valor diário das conduções × dias efetivamente trabalhados
 *   desconto = salário-base × percentual × (dias trabalhados / dias úteis do mês)
 *   líquido  = bruto − desconto  (nunca negativo)
 *
 * O desconto é proporcional: quem trabalhou metade do mês paga metade da cota.
 */
final class CalculadoraAuxilio
{
    public function calcular(
        Competencia $competencia,
        int $valorDiarioCentavos,
        int $diasTrabalhados,
        int $diasUteis,
        int $salarioBaseCentavos,
        float $percentualDesconto,
    ): DemonstrativoAuxilio {
        if ($valorDiarioCentavos < 0 || $diasTrabalhados < 0 || $diasUteis < 0 || $salarioBaseCentavos < 0) {
            throw new \InvalidArgumentException('Valores não podem ser negativos.');
        }
        if ($percentualDesconto < 0 || $percentualDesconto > 100) {
            throw new \InvalidArgumentException('Percentual de desconto deve estar entre 0 e 100.');
        }

        $bruto = $valorDiarioCentavos * $diasTrabalhados;

        $cotaMensal = $salarioBaseCentavos * $percentualDesconto / 100;
        $proporcao = $diasUteis > 0 ? min(1, $diasTrabalhados / $diasUteis) : 0;
        $desconto = min($bruto, (int) round($cotaMensal * $proporcao));

        return new DemonstrativoAuxilio(
            competencia: $competencia,
            valorDiarioCentavos: $valorDiarioCentavos,
            diasUteis: $diasUteis,
            diasTrabalhados: $diasTrabalhados,
            valorBrutoCentavos: $bruto,
            salarioBaseCentavos: $salarioBaseCentavos,
            percentualDesconto: $percentualDesconto,
            valorDescontoCentavos: $desconto,
            valorLiquidoCentavos: $bruto - $desconto,
        );
    }
}
