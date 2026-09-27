<?php

declare(strict_types=1);

namespace App\Enum;

/** Batidas esperadas numa jornada com intervalo, na ordem. */
enum TipoBatida: string
{
    case Entrada = 'ENTRADA';
    case SaidaAlmoco = 'SAIDA_ALMOCO';
    case RetornoAlmoco = 'RETORNO_ALMOCO';
    case Saida = 'SAIDA';

    public static function proxima(int $batidasNoDia): ?self
    {
        return self::cases()[$batidasNoDia] ?? null;
    }
}
