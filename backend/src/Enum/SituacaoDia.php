<?php

declare(strict_types=1);

namespace App\Enum;

enum SituacaoDia: string
{
    case Normal = 'NORMAL';
    case Falta = 'FALTA';
    /** Quantidade ímpar de batidas: o último período ficou aberto. */
    case Incompleto = 'INCOMPLETO';
    case Abonado = 'ABONADO';
    case Feriado = 'FERIADO';
    case FimDeSemana = 'FIM_DE_SEMANA';
    /** Dia de hoje: ainda não entra no saldo. */
    case EmAndamento = 'EM_ANDAMENTO';
    case Futuro = 'FUTURO';

    public function label(): string
    {
        return match ($this) {
            self::Normal => 'Normal',
            self::Falta => 'Falta',
            self::Incompleto => 'Incompleto',
            self::Abonado => 'Abonado',
            self::Feriado => 'Feriado',
            self::FimDeSemana => 'Fim de semana',
            self::EmAndamento => 'Em andamento',
            self::Futuro => '—',
        };
    }
}
