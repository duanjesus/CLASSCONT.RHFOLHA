<?php

declare(strict_types=1);

namespace App\Api\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final class SolicitacaoAuxilioInput
{
    /**
     * @param list<TrajetoInput> $trajetos
     */
    public function __construct(
        #[Assert\Count(min: 1, minMessage: 'Informe ao menos uma condução.')]
        #[Assert\Valid]
        public array $trajetos = [],
    ) {
    }
}
