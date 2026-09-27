<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Api\Dto\AvaliacaoInput;
use App\Api\Dto\JustificativaInput;
use App\Api\Representacao;
use App\Entity\Justificativa;
use App\Repository\JustificativaRepository;
use App\Security\Voter\AvaliacaoVoter;
use App\Service\JustificativaService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/justificativas')]
final class JustificativaController extends ApiController
{
    public function __construct(
        private readonly JustificativaService $service,
        private readonly JustificativaRepository $justificativas,
        private readonly Representacao $repr,
    ) {
    }

    #[Route('', name: 'api_justificativas_minhas', methods: ['GET'])]
    public function minhas(): JsonResponse
    {
        return $this->json(array_map($this->repr->justificativa(...), $this->justificativas->doFuncionario($this->usuario())));
    }

    #[Route('', name: 'api_justificativas_criar', methods: ['POST'])]
    public function criar(#[MapRequestPayload] JustificativaInput $input): JsonResponse
    {
        $justificativa = $this->service->registrar(
            $this->usuario(),
            new \DateTimeImmutable($input->data),
            $input->tipo(),
            $input->motivo,
        );

        return $this->json($this->repr->justificativa($justificativa), Response::HTTP_CREATED);
    }

    /** Fila de aprovação: chefia vê o seu setor, RH vê todas. */
    #[Route('/pendentes', name: 'api_justificativas_pendentes', methods: ['GET'])]
    public function pendentes(): JsonResponse
    {
        return $this->json(array_map($this->repr->justificativa(...), $this->justificativas->pendentesPara($this->usuario())));
    }

    #[Route('/{id}/avaliar', name: 'api_justificativas_avaliar', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function avaliar(Justificativa $justificativa, #[MapRequestPayload] AvaliacaoInput $input): JsonResponse
    {
        $this->denyAccessUnlessGranted(AvaliacaoVoter::AVALIAR, $justificativa);
        $this->service->avaliar($justificativa, $this->usuario(), $input->aprovar(), $input->observacao);

        return $this->json($this->repr->justificativa($justificativa));
    }
}
