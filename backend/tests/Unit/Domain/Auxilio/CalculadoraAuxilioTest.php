<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Auxilio;

use App\Domain\Auxilio\CalculadoraAuxilio;
use App\Domain\Auxilio\DemonstrativoAuxilio;
use App\Domain\Competencia;
use PHPUnit\Framework\TestCase;

final class CalculadoraAuxilioTest extends TestCase
{
    public function testMesCompleto(): void
    {
        // 2×7,30 + 2×5,25 = R$ 25,10/dia; salário R$ 6.500,00; 6%
        $d = $this->calcular(valorDiario: 2510, trabalhados: 21, uteis: 21, salario: 650000);

        self::assertSame(52710, $d->valorBrutoCentavos);   // 25,10 × 21
        self::assertSame(39000, $d->valorDescontoCentavos); // 6% de 6.500,00
        self::assertSame(13710, $d->valorLiquidoCentavos);
    }

    public function testDescontoProporcionalAosDiasTrabalhados(): void
    {
        // Trabalhou metade do mês: paga metade da cota
        $d = $this->calcular(valorDiario: 2510, trabalhados: 10, uteis: 20, salario: 650000);

        self::assertSame(25100, $d->valorBrutoCentavos);
        self::assertSame(19500, $d->valorDescontoCentavos);
        self::assertSame(5600, $d->valorLiquidoCentavos);
    }

    public function testDescontoNuncaUltrapassaOBruto(): void
    {
        // Salário alto e trajeto barato: nada a receber, mas nunca valor negativo
        $d = $this->calcular(valorDiario: 1050, trabalhados: 21, uteis: 21, salario: 1500000);

        self::assertSame(22050, $d->valorBrutoCentavos);
        self::assertSame(22050, $d->valorDescontoCentavos);
        self::assertSame(0, $d->valorLiquidoCentavos);
    }

    public function testSemDiasTrabalhadosNaoHaValores(): void
    {
        $d = $this->calcular(valorDiario: 2510, trabalhados: 0, uteis: 21, salario: 380000);

        self::assertSame(0, $d->valorBrutoCentavos);
        self::assertSame(0, $d->valorDescontoCentavos);
        self::assertSame(0, $d->valorLiquidoCentavos);
    }

    public function testArredondaODescontoParaOCentavoMaisProximo(): void
    {
        // 6% de 3.800,00 = 228,00 × 5/21 = 54,2857... → 54,29
        $d = $this->calcular(valorDiario: 2510, trabalhados: 5, uteis: 21, salario: 380000);

        self::assertSame(12550, $d->valorBrutoCentavos);
        self::assertSame(5429, $d->valorDescontoCentavos);
        self::assertSame(7121, $d->valorLiquidoCentavos);
    }

    public function testRejeitaPercentualInvalido(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new CalculadoraAuxilio())->calcular(Competencia::fromString('2026-09'), 100, 1, 1, 100, 150);
    }

    private function calcular(int $valorDiario, int $trabalhados, int $uteis, int $salario, float $percentual = 6.0): DemonstrativoAuxilio
    {
        return (new CalculadoraAuxilio())->calcular(
            Competencia::fromString('2026-09'), $valorDiario, $trabalhados, $uteis, $salario, $percentual,
        );
    }
}
