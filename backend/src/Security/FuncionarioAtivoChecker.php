<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\Funcionario;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Bloqueia funcionários desativados. Roda a cada autenticação, inclusive nas
 * requisições com JWT: um token emitido antes da desativação deixa de valer.
 */
final class FuncionarioAtivoChecker implements UserCheckerInterface
{
    public function checkPreAuth(UserInterface $user): void
    {
        if ($user instanceof Funcionario && !$user->isAtivo()) {
            throw new CustomUserMessageAccountStatusException('Usuário desativado. Procure o RH.');
        }
    }

    public function checkPostAuth(UserInterface $user): void
    {
    }
}
