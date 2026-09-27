<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Api\Representacao;
use App\Repository\JustificativaRepository;
use App\Service\EspelhoService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/** Visão da chefia: resumo do ponto de cada servidor do(s) setor(es) que chefia. */
final class EquipeController extends ApiController
{
    #[Route('/api/equipe', name: 'api_equipe', methods: ['GET'])]
    #[IsGranted('ROLE_CHEFIA')]
    public function __invoke(
        Request $request,
        EspelhoService $espelhos,
        JustificativaRepository $justificativas,
        Representacao $repr,
    ): JsonResponse {
        $competencia = $this->competencia($request);
        $equipe = [];

        foreach ($this->funcionarios->equipeDe($this->usuario()) as $membro) {
            $espelho = $espelhos->gerar($membro, $competencia);
            $equipe[] = [
                'funcionario' => $repr->funcionarioResumo($membro) + ['cargo' => $membro->getCargo()?->getNome()],
                'saldoMinutos' => $espelho->saldoMinutos,
                'faltas' => $espelho->faltas,
                'diasTrabalhados' => $espelho->diasTrabalhados,
                'pendencias' => $justificativas->contarPendentes($membro),
            ];
        }

        return $this->json($equipe);
    }
}
