<?php

declare(strict_types=1);

namespace App\Service;

use Doctrine\DBAL\Connection;

/** Situação da fila de envio de e-mails (tabela messenger_messages do Messenger). */
final class FilaDeEmails
{
    public function __construct(private readonly Connection $conexao)
    {
    }

    /** Mensagens que esgotaram as tentativas automáticas e aguardam reenvio manual. */
    public function falhas(): int
    {
        return (int) $this->conexao->fetchOne("SELECT COUNT(*) FROM messenger_messages WHERE queue_name = 'failed'");
    }
}
