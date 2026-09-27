<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Api\Representacao;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final class MeController extends ApiController
{
    #[Route('/api/me', name: 'api_me', methods: ['GET'])]
    public function __invoke(Representacao $repr): JsonResponse
    {
        return $this->json($repr->usuario($this->usuario()));
    }
}
