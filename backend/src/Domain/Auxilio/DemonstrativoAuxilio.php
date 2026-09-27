<?php

declare(strict_types=1);

namespace App\Domain\Auxilio;

use App\Domain\Competencia;

/** Valores em centavos (inteiros) para não acumular erro de ponto flutuante. */
final readonly class DemonstrativoAuxilio
{
    public function __construct(
        public Competencia $competencia,
        public int $valorDiarioCentavos,
        public int $diasUteis,
        public int $diasTrabalhados,
        public int $valorBrutoCentavos,
        public int $salarioBaseCentavos,
        public float $percentualDesconto,
        public int $valorDescontoCentavos,
        public int $valorLiquidoCentavos,
    ) {
    }
}
