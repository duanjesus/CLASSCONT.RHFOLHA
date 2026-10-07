<?php

declare(strict_types=1);

namespace App\Auditoria;

use App\Entity\Funcionario;
use App\Enum\AcaoAuditoria;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\PostPersistEventArgs;
use Doctrine\ORM\Event\PostRemoveEventArgs;
use Doctrine\ORM\Event\PostUpdateEventArgs;
use Doctrine\ORM\Event\PreRemoveEventArgs;
use Doctrine\ORM\Events;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Grava a trilha de auditoria de toda entidade Auditavel.
 *
 * Os eventos post* do Doctrine disparam DENTRO da transação do flush, depois de
 * cada SQL e antes do commit. Gravar o log ali, pelo DBAL, garante atomicidade:
 * ou a alteração e o log entram juntos, ou nenhum dos dois entra.
 * (Um novo persist()/flush() aqui dentro não é permitido pelo Doctrine.)
 *
 * Só registra ações de um usuário autenticado: cargas pelo console (fixtures,
 * migrations) não geram log.
 */
#[AsDoctrineListener(event: Events::postPersist)]
#[AsDoctrineListener(event: Events::postUpdate)]
#[AsDoctrineListener(event: Events::preRemove)]
#[AsDoctrineListener(event: Events::postRemove)]
final class AuditoriaListener
{
    /**
     * Identificadores capturados no preRemove (em algumas versões do ORM o id
     * já foi limpo quando o postRemove dispara).
     *
     * @var \SplObjectStorage<object, string>
     */
    private \SplObjectStorage $idsRemovidos;

    public function __construct(
        private readonly Security $security,
        private readonly RequestStack $requestStack,
        private readonly ClockInterface $clock,
        private readonly NormalizadorDeValores $normalizador,
    ) {
        $this->idsRemovidos = new \SplObjectStorage();
    }

    public function postPersist(PostPersistEventArgs $args): void
    {
        $entidade = $args->getObject();
        if ($entidade instanceof Auditavel) {
            $em = $args->getObjectManager();
            $this->registrar($em, AcaoAuditoria::Criacao, $entidade, $this->id($em, $entidade), $em->getUnitOfWork()->getEntityChangeSet($entidade));
        }
    }

    public function postUpdate(PostUpdateEventArgs $args): void
    {
        $entidade = $args->getObject();
        if ($entidade instanceof Auditavel) {
            $em = $args->getObjectManager();
            $this->registrar($em, AcaoAuditoria::Alteracao, $entidade, $this->id($em, $entidade), $em->getUnitOfWork()->getEntityChangeSet($entidade));
        }
    }

    public function preRemove(PreRemoveEventArgs $args): void
    {
        $entidade = $args->getObject();
        if ($entidade instanceof Auditavel) {
            $this->idsRemovidos[$entidade] = $this->id($args->getObjectManager(), $entidade);
        }
    }

    public function postRemove(PostRemoveEventArgs $args): void
    {
        $entidade = $args->getObject();
        if ($entidade instanceof Auditavel) {
            $id = $this->idsRemovidos[$entidade] ?? '';
            unset($this->idsRemovidos[$entidade]);
            $this->registrar($args->getObjectManager(), AcaoAuditoria::Exclusao, $entidade, $id, []);
        }
    }

    /** @param array<string, array{0: mixed, 1: mixed}> $changeSet */
    private function registrar(EntityManagerInterface $em, AcaoAuditoria $acao, Auditavel $entidade, string $id, array $changeSet): void
    {
        $autor = $this->security->getUser();
        if (!$autor instanceof Funcionario) {
            return;
        }

        $alteracoes = $this->normalizador->alteracoes($this->comEnums($em, $entidade, $changeSet));
        if (AcaoAuditoria::Alteracao === $acao && [] === $alteracoes) {
            return; // nada mudou de fato (ex.: mesma data em outro objeto DateTime)
        }

        $em->getConnection()->insert('registro_auditoria', [
            'ocorrido_em' => $this->clock->now(),
            'autor_id' => $autor->getId(),
            'autor_nome' => $autor->getNome(),
            'acao' => $acao->value,
            'entidade' => $entidade::tipoAuditoria(),
            'entidade_id' => $id,
            'rotulo' => mb_substr($entidade->rotuloAuditoria(), 0, 200),
            'alteracoes' => $alteracoes,
            'ip' => $this->requestStack->getMainRequest()?->getClientIp(),
        ], [
            'ocorrido_em' => Types::DATETIME_IMMUTABLE,
            'alteracoes' => Types::JSON,
        ]);
    }

    /**
     * No changeset do Doctrine, campos mapeados com enumType chegam como o valor
     * cru do banco ("PENDENTE"). Converte de volta para o enum, para o log mostrar
     * o rótulo legível ("Pendente").
     *
     * @param array<string, array{0: mixed, 1: mixed}> $changeSet
     *
     * @return array<string, array{0: mixed, 1: mixed}>
     */
    private function comEnums(EntityManagerInterface $em, object $entidade, array $changeSet): array
    {
        $metadados = $em->getClassMetadata($entidade::class);

        foreach ($changeSet as $campo => $par) {
            $enum = $metadados->hasField($campo) ? $metadados->getFieldMapping($campo)->enumType : null;
            if (null === $enum) {
                continue;
            }
            foreach ($par as $i => $valor) {
                if (\is_string($valor) || \is_int($valor)) {
                    $changeSet[$campo][$i] = $enum::tryFrom($valor) ?? $valor;
                }
            }
        }

        return $changeSet;
    }

    private function id(EntityManagerInterface $em, object $entidade): string
    {
        return implode('-', array_map(strval(...), $em->getUnitOfWork()->getEntityIdentifier($entidade)));
    }
}
