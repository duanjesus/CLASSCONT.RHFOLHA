<?php

declare(strict_types=1);

namespace App\Twig;

use App\Domain\Competencia;
use Twig\Attribute\AsTwigFilter;

/**
 * Filtros Twig do domínio de RH. Usa a sintaxe de atributos do Twig 3.21+,
 * registrada automaticamente pelo autoconfigure do Symfony.
 *
 *   {{ 485|horas }}            → 08:05
 *   {{ -30|horas(true) }}      → -00:30
 *   {{ 13710|centavos }}       → R$ 137,10
 *   {{ '2026-09'|competencia }} → setembro/2026
 *   {{ '100322'|matricula }}   → 100.322
 */
final class RhExtension
{
    private const MESES = [
        1 => 'janeiro', 'fevereiro', 'março', 'abril', 'maio', 'junho',
        'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro',
    ];

    #[AsTwigFilter('horas')]
    public function horas(int $minutos, bool $comSinal = false): string
    {
        $sinal = $minutos < 0 ? '-' : ($comSinal && $minutos > 0 ? '+' : '');
        $abs = abs($minutos);

        return \sprintf('%s%02d:%02d', $sinal, intdiv($abs, 60), $abs % 60);
    }

    #[AsTwigFilter('centavos')]
    public function centavos(int $centavos): string
    {
        $formatador = new \NumberFormatter('pt_BR', \NumberFormatter::CURRENCY);

        return (string) $formatador->formatCurrency($centavos / 100, 'BRL');
    }

    #[AsTwigFilter('competencia')]
    public function competencia(Competencia|string $competencia): string
    {
        $c = \is_string($competencia) ? Competencia::fromString($competencia) : $competencia;

        return self::MESES[$c->mes].'/'.$c->ano;
    }

    #[AsTwigFilter('matricula')]
    public function matricula(string $matricula): string
    {
        return \strlen($matricula) > 3
            ? substr($matricula, 0, -3).'.'.substr($matricula, -3)
            : $matricula;
    }
}
