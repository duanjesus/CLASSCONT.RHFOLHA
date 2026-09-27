<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain;

use App\Domain\Competencia;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class CompetenciaTest extends TestCase
{
    public function testConverteDeEParaString(): void
    {
        $c = Competencia::fromString('2026-02');

        self::assertSame(2026, $c->ano);
        self::assertSame(2, $c->mes);
        self::assertSame('2026-02', (string) $c);
    }

    public function testDiasDoMesIncluindoAnoBissexto(): void
    {
        self::assertCount(28, iterator_to_array(Competencia::fromString('2026-02')->dias(), false));
        self::assertCount(29, iterator_to_array(Competencia::fromString('2028-02')->dias(), false));
        self::assertSame('2026-02-28', Competencia::fromString('2026-02')->ultimoDia()->format('Y-m-d'));
    }

    public function testNavegaEntreMesesAtravessandoOAno(): void
    {
        self::assertSame('2025-12', (string) Competencia::fromString('2026-01')->anterior());
        self::assertSame('2027-01', (string) Competencia::fromString('2026-12')->posterior());
        self::assertTrue(Competencia::fromString('2026-08')->antesDe(Competencia::fromString('2026-09')));
    }

    #[DataProvider('valoresInvalidos')]
    public function testRejeitaFormatoInvalido(string $valor): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Competencia::fromString($valor);
    }

    /** @return iterable<string, array{string}> */
    public static function valoresInvalidos(): iterable
    {
        yield 'mês 13' => ['2026-13'];
        yield 'mês zero' => ['2026-00'];
        yield 'formato brasileiro' => ['09/2026'];
        yield 'vazio' => [''];
    }
}
