<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Domain\Competencia;
use App\Exception\RegraNegocioException;
use App\Repository\FechamentoCompetenciaRepository;
use App\Service\FechamentoService;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/fechamentos')]
final class FechamentoController extends AdminController
{
    public function __construct(
        private readonly FechamentoService $service,
        private readonly ClockInterface $clock,
    ) {
    }

    #[Route('', name: 'admin_fechamentos', methods: ['GET'])]
    public function index(FechamentoCompetenciaRepository $fechamentos): Response
    {
        $atual = Competencia::daData($this->clock->now());
        $linhas = [];
        for ($c = $atual, $i = 0; $i < 12; $c = $c->anterior(), ++$i) {
            $linhas[] = [
                'competencia' => $c,
                'fechamento' => $fechamentos->daCompetencia($c),
                'pode_fechar' => $c->antesDe($atual),
            ];
        }

        return $this->render('admin/fechamento/index.html.twig', ['linhas' => $linhas]);
    }

    #[Route('/{competencia}/fechar', name: 'admin_fechamentos_fechar', methods: ['POST'], requirements: ['competencia' => '\d{4}-\d{2}'])]
    public function fechar(Request $request, string $competencia): Response
    {
        return $this->executar($request, 'fechar-'.$competencia, function () use ($competencia): string {
            $this->service->fechar(Competencia::fromString($competencia), $this->usuario());

            return 'Competência fechada. Ponto, justificativas e avaliações desse mês estão bloqueados.';
        });
    }

    #[Route('/{competencia}/reabrir', name: 'admin_fechamentos_reabrir', methods: ['POST'], requirements: ['competencia' => '\d{4}-\d{2}'])]
    public function reabrir(Request $request, string $competencia): Response
    {
        return $this->executar($request, 'reabrir-'.$competencia, function () use ($competencia): string {
            $this->service->reabrir(Competencia::fromString($competencia));

            return 'Competência reaberta.';
        });
    }

    /** @param callable(): string $acao */
    private function executar(Request $request, string $tokenId, callable $acao): Response
    {
        if (!$this->isCsrfTokenValid($tokenId, $request->getPayload()->getString('_token'))) {
            $this->addFlash('erro', 'Token de segurança inválido.');
        } else {
            try {
                $this->addFlash('sucesso', $acao());
            } catch (RegraNegocioException $e) {
                $this->addFlash('erro', $e->getMessage());
            }
        }

        return $this->redirectToRoute('admin_fechamentos');
    }
}
