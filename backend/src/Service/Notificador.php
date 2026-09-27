<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Funcionario;
use App\Enum\StatusAvaliacao;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;

/** E-mails transacionais renderizados com Twig (templates/emails). */
final class Notificador
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly LoggerInterface $logger,
        private readonly string $emailRemetente,
    ) {
    }

    public function resultadoAvaliacao(
        Funcionario $destinatario,
        string $assunto,
        string $descricaoPedido,
        StatusAvaliacao $status,
        Funcionario $avaliador,
        ?string $observacao,
    ): void {
        $email = (new TemplatedEmail())
            ->from(new Address($this->emailRemetente, 'CLASSCONT.RHFOLHA'))
            ->to(new Address($destinatario->getEmail(), $destinatario->getNome()))
            ->subject($assunto)
            ->htmlTemplate('emails/resultado_avaliacao.html.twig')
            ->context([
                'destinatario' => $destinatario,
                'descricao_pedido' => $descricaoPedido,
                'status' => $status,
                'avaliador' => $avaliador,
                'observacao' => $observacao,
            ]);

        // Falha no envio não pode desfazer a avaliação já gravada.
        try {
            $this->mailer->send($email);
        } catch (TransportExceptionInterface $e) {
            $this->logger->warning('Falha ao enviar e-mail de avaliação', ['erro' => $e->getMessage()]);
        }
    }
}
