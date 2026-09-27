<?php

declare(strict_types=1);

namespace App\Api\Dto;

use App\Enum\Sentido;
use Symfony\Component\Validator\Constraints as Assert;

final class TrajetoInput
{
    public function __construct(
        #[Assert\Positive(message: 'Selecione a linha.')]
        public int $linhaId = 0,

        #[Assert\Choice(callback: [Sentido::class, 'valores'], message: 'Sentido deve ser IDA ou VOLTA.')]
        public string $sentido = '',
    ) {
    }

    public function sentido(): Sentido
    {
        return Sentido::from($this->sentido);
    }
}
