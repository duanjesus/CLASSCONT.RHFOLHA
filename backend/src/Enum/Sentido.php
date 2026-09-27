<?php

declare(strict_types=1);

namespace App\Enum;

enum Sentido: string
{
    case Ida = 'IDA';
    case Volta = 'VOLTA';

    /** @return list<string> */
    public static function valores(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function label(): string
    {
        return self::Ida === $this ? 'Ida' : 'Volta';
    }
}
