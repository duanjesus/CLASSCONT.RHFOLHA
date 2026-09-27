<?php

declare(strict_types=1);

namespace App\Enum;

enum TipoJustificativa: string
{
    case AtestadoMedico = 'ATESTADO_MEDICO';
    case FaltaJustificada = 'FALTA_JUSTIFICADA';
    case ServicoExterno = 'SERVICO_EXTERNO';
    case EsquecimentoBatida = 'ESQUECIMENTO_BATIDA';

    /** @return list<string> */
    public static function valores(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function label(): string
    {
        return match ($this) {
            self::AtestadoMedico => 'Atestado médico',
            self::FaltaJustificada => 'Falta justificada',
            self::ServicoExterno => 'Serviço externo',
            self::EsquecimentoBatida => 'Esquecimento de batida',
        };
    }
}
