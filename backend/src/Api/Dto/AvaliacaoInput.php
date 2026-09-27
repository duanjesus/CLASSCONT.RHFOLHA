<?php

declare(strict_types=1);

namespace App\Api\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final class AvaliacaoInput
{
    public const APROVAR = 'APROVAR';
    public const RECUSAR = 'RECUSAR';

    public function __construct(
        #[Assert\Choice(choices: [self::APROVAR, self::RECUSAR], message: 'Decisão deve ser APROVAR ou RECUSAR.')]
        public string $decisao = '',

        #[Assert\Length(max: 1000)]
        public ?string $observacao = null,
    ) {
    }

    public function aprovar(): bool
    {
        return self::APROVAR === $this->decisao;
    }
}
