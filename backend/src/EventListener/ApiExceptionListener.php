<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Exception\CompetenciaInvalidaException;
use App\Exception\RegraNegocioException;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Symfony\Component\Validator\Exception\ValidationFailedException;

/**
 * Padroniza erros de /api/* como { "erro": "...", "detalhes": { campo: mensagem } }.
 * Roda depois do listener do Security (prioridade 1), que já converte
 * AccessDenied em 403.
 */
#[AsEventListener(event: KernelEvents::EXCEPTION, priority: -10)]
final class ApiExceptionListener
{
    private const JSON_FLAGS = JsonResponse::DEFAULT_ENCODING_OPTIONS | \JSON_UNESCAPED_UNICODE;

    public function __invoke(ExceptionEvent $event): void
    {
        $e = $event->getThrowable();

        if (!str_starts_with($event->getRequest()->getPathInfo(), '/api')) {
            // No painel Twig, ?competencia= malformada vira 400 (e não erro 500)
            if ($e instanceof CompetenciaInvalidaException) {
                $event->setThrowable(new BadRequestHttpException($e->getMessage(), $e));
            }

            return;
        }

        // Só exceções de negócio/entrada viram 422 com a mensagem; qualquer outra
        // (bug, falha de infraestrutura) segue como 500 sem expor detalhes internos.
        if ($e instanceof RegraNegocioException || $e instanceof CompetenciaInvalidaException) {
            $event->setResponse(self::resposta(['erro' => $e->getMessage()], 422));

            return;
        }

        if ($e instanceof HttpExceptionInterface) {
            $anterior = $e->getPrevious();
            if ($anterior instanceof ValidationFailedException) {
                $event->setResponse(self::resposta([
                    'erro' => 'Dados inválidos.',
                    'detalhes' => self::violacoes($anterior->getViolations()),
                ], 422));

                return;
            }

            $mensagem = match ($e->getStatusCode()) {
                401 => 'Autenticação necessária.',
                403 => 'Você não tem permissão para esta ação.',
                404 => 'Registro não encontrado.',
                default => $e->getStatusCode() < 500 ? $e->getMessage() : 'Erro interno.',
            };
            $event->setResponse(self::resposta(['erro' => $mensagem], $e->getStatusCode(), $e->getHeaders()));
        }
    }

    /**
     * @param array<string, mixed>  $dados
     * @param array<string, string> $headers
     */
    private static function resposta(array $dados, int $status, array $headers = []): JsonResponse
    {
        $resposta = new JsonResponse(null, $status, $headers);
        $resposta->setEncodingOptions(self::JSON_FLAGS);

        return $resposta->setData($dados);
    }

    /** @return array<string, string> */
    private static function violacoes(ConstraintViolationListInterface $lista): array
    {
        $detalhes = [];
        foreach ($lista as $violacao) {
            $detalhes[$violacao->getPropertyPath() ?: 'geral'] = (string) $violacao->getMessage();
        }

        return $detalhes;
    }
}
