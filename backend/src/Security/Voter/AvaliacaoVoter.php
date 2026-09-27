<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\Funcionario;
use App\Entity\Justificativa;
use App\Entity\SolicitacaoAuxilio;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Quem pode aprovar/recusar um pedido:
 *  - a chefia imediata do setor do solicitante, ou
 *  - qualquer servidor do RH;
 *  - nunca o próprio solicitante.
 *
 * @extends Voter<string, Justificativa|SolicitacaoAuxilio>
 */
final class AvaliacaoVoter extends Voter
{
    public const AVALIAR = 'AVALIAR';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return self::AVALIAR === $attribute
            && ($subject instanceof Justificativa || $subject instanceof SolicitacaoAuxilio);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $avaliador = $token->getUser();
        if (!$avaliador instanceof Funcionario) {
            return false;
        }

        $solicitante = $subject->getFuncionario();
        if ($solicitante === $avaliador) {
            $vote?->addReason('Ninguém avalia o próprio pedido.');

            return false;
        }

        return $avaliador->isRh() || $avaliador->chefia($solicitante);
    }
}
