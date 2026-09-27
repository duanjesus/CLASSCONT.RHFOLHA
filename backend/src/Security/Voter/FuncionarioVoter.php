<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\Funcionario;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Acesso ao ponto/auxílio de um funcionário: ele mesmo, sua chefia imediata ou o RH.
 *
 * @extends Voter<string, Funcionario>
 */
final class FuncionarioVoter extends Voter
{
    public const VER_PONTO = 'VER_PONTO';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return self::VER_PONTO === $attribute && $subject instanceof Funcionario;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $usuario = $token->getUser();
        if (!$usuario instanceof Funcionario) {
            return false;
        }

        return $usuario === $subject || $usuario->isRh() || $usuario->chefia($subject);
    }
}
