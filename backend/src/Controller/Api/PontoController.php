<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Api\Representacao;
use App\Repository\FechamentoCompetenciaRepository;
use App\Service\EspelhoService;
use App\Service\GeradorPdf;
use App\Service\PontoService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/ponto')]
final class PontoController extends ApiController
{
    public function __construct(
        private readonly PontoService $ponto,
        private readonly EspelhoService $espelhos,
        private readonly FechamentoCompetenciaRepository $fechamentos,
        private readonly Representacao $repr,
    ) {
    }

    #[Route('/hoje', name: 'api_ponto_hoje', methods: ['GET'])]
    public function hoje(): JsonResponse
    {
        return $this->json($this->ponto->situacaoDeHoje($this->usuario()));
    }

    #[Route('/bater', name: 'api_ponto_bater', methods: ['POST'])]
    public function bater(): JsonResponse
    {
        $this->ponto->bater($this->usuario());

        return $this->json($this->ponto->situacaoDeHoje($this->usuario()), Response::HTTP_CREATED);
    }

    #[Route('/espelho', name: 'api_ponto_espelho', methods: ['GET'])]
    public function espelho(Request $request): JsonResponse
    {
        $funcionario = $this->funcionarioAlvo($request);
        $competencia = $this->competencia($request);
        $espelho = $this->espelhos->gerar($funcionario, $competencia);

        return $this->json($this->repr->espelho($espelho, $funcionario, $this->fechamentos->estaFechada($competencia)));
    }

    /** O mesmo espelho, renderizado pelo Twig (templates/pdf/espelho.html.twig) e convertido em PDF. */
    #[Route('/espelho/pdf', name: 'api_ponto_espelho_pdf', methods: ['GET'])]
    public function espelhoPdf(Request $request, GeradorPdf $pdf): Response
    {
        $funcionario = $this->funcionarioAlvo($request);
        $competencia = $this->competencia($request);

        return $pdf->resposta('pdf/espelho.html.twig', [
            'funcionario' => $funcionario,
            'espelho' => $this->espelhos->gerar($funcionario, $competencia),
            'fechado' => $this->fechamentos->estaFechada($competencia),
            'emitido_em' => $this->clock->now(),
        ], \sprintf('espelho-%s-%s.pdf', $funcionario->getMatricula(), $competencia));
    }
}
