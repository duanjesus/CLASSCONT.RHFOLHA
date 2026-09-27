<?php

declare(strict_types=1);

namespace App\Api\Dto;

use App\Enum\TipoJustificativa;
use Symfony\Component\Validator\Constraints as Assert;

final class JustificativaInput
{
    public function __construct(
        #[Assert\NotBlank(message: 'Informe a data.')]
        #[Assert\Date(message: 'Data inválida.')]
        public string $data = '',

        #[Assert\NotBlank(message: 'Informe o tipo.')]
        #[Assert\Choice(callback: [TipoJustificativa::class, 'valores'], message: 'Tipo de justificativa inválido.')]
        public string $tipo = '',

        #[Assert\NotBlank(message: 'Descreva o motivo.')]
        #[Assert\Length(min: 10, max: 1000, minMessage: 'Descreva o motivo com ao menos {{ limit }} caracteres.')]
        public string $motivo = '',
    ) {
    }

    public function tipo(): TipoJustificativa
    {
        return TipoJustificativa::from($this->tipo);
    }
}
