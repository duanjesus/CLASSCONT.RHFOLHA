<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Domain\Competencia;
use App\Entity\Funcionario;
use App\Repository\FuncionarioRepository;
use App\Security\Voter\FuncionarioVoter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Contracts\Service\Attribute\Required;

abstract class ApiController extends AbstractController
{
    protected ClockInterface $clock;
    protected FuncionarioRepository $funcionarios;

    #[Required]
    public function injetarDependenciasBase(ClockInterface $clock, FuncionarioRepository $funcionarios): void
    {
        $this->clock = $clock;
        $this->funcionarios = $funcionarios;
    }

    /**
     * Igual ao json() do AbstractController, mas sem escapar acentos (ç)
     * e preservando "5.0" em valores monetários.
     *
     * @param array<string, mixed> $headers
     * @param array<string, mixed> $context
     */
    protected function json(mixed $data, int $status = 200, array $headers = [], array $context = []): JsonResponse
    {
        return parent::json($data, $status, $headers, $context + [
            'json_encode_options' => JsonResponse::DEFAULT_ENCODING_OPTIONS | \JSON_UNESCAPED_UNICODE | \JSON_PRESERVE_ZERO_FRACTION,
        ]);
    }

    protected function usuario(): Funcionario
    {
        $usuario = $this->getUser();
        \assert($usuario instanceof Funcionario);

        return $usuario;
    }

    /** ?competencia=AAAA-MM; padrão: mês corrente. */
    protected function competencia(Request $request): Competencia
    {
        $valor = $request->query->getString('competencia');

        return '' === $valor ? Competencia::daData($this->clock->now()) : Competencia::fromString($valor);
    }

    /**
     * ?funcionario=ID para chefia/RH consultarem outra pessoa; padrão: o próprio usuário.
     * A permissão é checada pelo FuncionarioVoter.
     */
    protected function funcionarioAlvo(Request $request): Funcionario
    {
        $id = $request->query->getInt('funcionario');
        $alvo = 0 === $id ? $this->usuario() : ($this->funcionarios->find($id) ?? throw new NotFoundHttpException());
        $this->denyAccessUnlessGranted(FuncionarioVoter::VER_PONTO, $alvo);

        return $alvo;
    }
}
