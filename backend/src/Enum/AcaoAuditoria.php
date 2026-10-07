<?php

declare(strict_types=1);

namespace App\Enum;

enum AcaoAuditoria: string
{
    case Criacao = 'CRIACAO';
    case Alteracao = 'ALTERACAO';
    case Exclusao = 'EXCLUSAO';

    public function label(): string
    {
        return match ($this) {
            self::Criacao => 'Criação',
            self::Alteracao => 'Alteração',
            self::Exclusao => 'Exclusão',
        };
    }
}
