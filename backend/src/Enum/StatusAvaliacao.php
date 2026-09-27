<?php

declare(strict_types=1);

namespace App\Enum;

/** Ciclo de vida de pedidos que passam por aprovação (justificativas e auxílio-transporte). */
enum StatusAvaliacao: string
{
    case Pendente = 'PENDENTE';
    case Aprovada = 'APROVADA';
    case Recusada = 'RECUSADA';
    /** Uma solicitação mais nova tomou o lugar desta. */
    case Substituida = 'SUBSTITUIDA';

    public function label(): string
    {
        return match ($this) {
            self::Pendente => 'Pendente',
            self::Aprovada => 'Aprovada',
            self::Recusada => 'Recusada',
            self::Substituida => 'Substituída',
        };
    }
}
