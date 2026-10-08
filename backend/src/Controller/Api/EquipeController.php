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

        // Tudo em lote: o número de consultas não depende do tamanho da equipe
        $membros = $this->funcionarios->equipeDe($this->usuario());
        $espelhosPorId = $espelhos->gerarParaVarios($membros, $competencia);
        $pendencias = $justificativas->contarPendentesDeVarios($membros);

        foreach ($membros as $membro) {
            $espelho = $espelhosPorId[(int) $membro->getId()];
            $equipe[] = [
                'funcionario' => $repr->funcionarioResumo($membro) + ['cargo' => $membro->getCargo()?->getNome()],
                'saldoMinutos' => $espelho->saldoMinutos,
                'faltas' => $espelho->faltas,
                'diasTrabalhados' => $espelho->diasTrabalhados,
                'pendencias' => $pendencias[(int) $membro->getId()] ?? 0,
            ];
        }

        return $this->json($equipe);
    }
}
