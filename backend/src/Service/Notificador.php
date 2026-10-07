<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Funcionario;
use App\Enum\StatusAvaliacao;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Exception\ExceptionInterface as MessengerException;
use Symfony\Component\Mime\Address;

/**
 * E-mails transacionais renderizados com Twig (templates/emails).
 *
 * O envio é assíncrono: mailer->send() só coloca a mensagem na fila do Messenger
 * (config/packages/messenger.yaml) e quem fala com o SMTP é o worker, com novas
 * tentativas automáticas. A requisição do usuário não espera nem falha por causa do e-mail.
 */
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
            // Só valores simples no contexto: a mensagem é serializada para a fila,
            // e entidades do Doctrine não devem viajar dentro dela.
            ->context([
                'primeiro_nome' => explode(' ', $destinatario->getNome())[0],
                'descricao_pedido' => $descricaoPedido,
                'aprovado' => StatusAvaliacao::Aprovada === $status,
                'status' => $status->label(),
                'avaliador' => $avaliador->getNome(),
                'observacao' => $observacao,
            ]);

        // A avaliação já foi gravada: não conseguir enfileirar o aviso não pode desfazê-la.
        try {
            $this->mailer->send($email);
        } catch (TransportExceptionInterface|MessengerException $e) {
            $this->logger->error('Não foi possível enfileirar o e-mail de avaliação', [
                'destinatario' => $destinatario->getEmail(),
                'erro' => $e->getMessage(),
            ]);
        }
    }
}
