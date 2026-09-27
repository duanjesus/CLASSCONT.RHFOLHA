<?php

declare(strict_types=1);

namespace App\Exception;

/**
 * Violação de regra de negócio. A API converte em HTTP 422 com { erro };
 * o painel Twig mostra como flash message.
 */
final class RegraNegocioException extends \DomainException
{
}
