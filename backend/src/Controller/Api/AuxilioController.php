<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Api\Dto\AvaliacaoInput;
use App\Api\Dto\SolicitacaoAuxilioInput;
use App\Api\Dto\TrajetoInput;
use App\Api\Representacao;
use App\Entity\SolicitacaoAuxilio;
use App\Repository\LinhaOnibusRepository;
use App\Repository\SolicitacaoAuxilioRepository;
use App\Security\Voter\AvaliacaoVoter;
use App\Service\AuxilioService;
use App\Service\GeradorPdf;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

final class AuxilioController extends ApiController
{
    public function __construct(
        private readonly AuxilioService $service,
        private readonly SolicitacaoAuxilioRepository $solicitacoes,
        private readonly Representacao $repr,
    ) {
    }

    #[Route('/api/linhas', name: 'api_linhas', methods: ['GET'])]
    public function linhas(LinhaOnibusRepository $linhas): JsonResponse
    {
        return $this->json(array_map($this->repr->linha(...), $linhas->ativas()));
    }

    /** Auxílio vigente (aprovado) e eventual pedido aguardando avaliação. */
    #[Route('/api/auxilio', name: 'api_auxilio', methods: ['GET'])]
    public function meu(): JsonResponse
    {
        $vigente = $this->solicitacoes->vigente($this->usuario());
        $pendente = $this->solicitacoes->pendente($this->usuario());

        return $this->json([
            'vigente' => $vigente ? $this->repr->solicitacao($vigente) : null,
            'pendente' => $pendente ? $this->repr->solicitacao($pendente) : null,
        ]);
    }

    #[Route('/api/auxilio', name: 'api_auxilio_solicitar', methods: ['POST'])]
    public function solicitar(#[MapRequestPayload] SolicitacaoAuxilioInput $input): JsonResponse
    {
        $trajetos = array_map(
            static fn (TrajetoInput $t): array => ['linhaId' => $t->linhaId, 'sentido' => $t->sentido()],
            $input->trajetos,
        );

        $solicitacao = $this->service->solicitar($this->usuario(), $trajetos);

        return $this->json($this->repr->solicitacao($solicitacao), Response::HTTP_CREATED);
    }

    #[Route('/api/auxilio/pendentes', name: 'api_auxilio_pendentes', methods: ['GET'])]
    public function pendentes(): JsonResponse
    {
        return $this->json(array_map($this->repr->solicitacao(...), $this->solicitacoes->pendentesPara($this->usuario())));
    }

    #[Route('/api/auxilio/{id}/avaliar', name: 'api_auxilio_avaliar', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function avaliar(SolicitacaoAuxilio $solicitacao, #[MapRequestPayload] AvaliacaoInput $input): JsonResponse
    {
        $this->denyAccessUnlessGranted(AvaliacaoVoter::AVALIAR, $solicitacao);
        $this->service->avaliar($solicitacao, $this->usuario(), $input->aprovar(), $input->observacao);

        return $this->json($this->repr->solicitacao($solicitacao));
    }

    #[Route('/api/auxilio/demonstrativo', name: 'api_auxilio_demonstrativo', methods: ['GET'])]
    public function demonstrativo(Request $request): JsonResponse
    {
        $demonstrativo = $this->service->demonstrativo($this->funcionarioAlvo($request), $this->competencia($request))
            ?? throw new NotFoundHttpException();

        return $this->json($this->repr->demonstrativo($demonstrativo));
    }

    #[Route('/api/auxilio/demonstrativo/pdf', name: 'api_auxilio_demonstrativo_pdf', methods: ['GET'])]
    public function demonstrativoPdf(Request $request, GeradorPdf $pdf): Response
    {
        $funcionario = $this->funcionarioAlvo($request);
        $competencia = $this->competencia($request);
        $demonstrativo = $this->service->demonstrativo($funcionario, $competencia) ?? throw new NotFoundHttpException();

        return $pdf->resposta('pdf/demonstrativo_auxilio.html.twig', [
            'funcionario' => $funcionario,
            'demonstrativo' => $demonstrativo,
            'solicitacao' => $this->solicitacoes->vigente($funcionario),
            'emitido_em' => $this->clock->now(),
        ], \sprintf('auxilio-transporte-%s-%s.pdf', $funcionario->getMatricula(), $competencia));
    }
}
