<?php

declare(strict_types=1);

namespace App\Tests\Unit\Twig;

use App\Twig\RhExtension;
use PHPUnit\Framework\TestCase;

final class RhExtensionTest extends TestCase
{
    private RhExtension $ext;

    protected function setUp(): void
    {
        $this->ext = new RhExtension();
    }

    public function testHoras(): void
    {
        self::assertSame('08:05', $this->ext->horas(485));
        self::assertSame('-00:30', $this->ext->horas(-30));
        self::assertSame('+01:00', $this->ext->horas(60, true));
        self::assertSame('00:00', $this->ext->horas(0, true));
        self::assertSame('131:57', $this->ext->horas(7917));
    }

    public function testCentavos(): void
    {
        // NumberFormatter usa espaço não separável entre "R$" e o valor
        self::assertSame('R$ 1.234,56', str_replace("\u{a0}", ' ', $this->ext->centavos(123456)));
    }

    public function testCompetencia(): void
    {
        self::assertSame('março/2026', $this->ext->competencia('2026-03'));
    }

    public function testMatricula(): void
    {
        self::assertSame('100.322', $this->ext->matricula('100322'));
        self::assertSame('123', $this->ext->matricula('123'));
    }
}
