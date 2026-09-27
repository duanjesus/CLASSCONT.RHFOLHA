<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Domain\Competencia;
use App\Repository\SolicitacaoAuxilioRepository;
use App\Service\AuxilioService;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class RelatorioController extends AdminController
{
    /** Folha do auxílio-transporte: um demonstrativo por servidor com auxílio vigente. */
    #[Route('/admin/relatorios/auxilio-transporte', name: 'admin_relatorio_auxilio', methods: ['GET'])]
    public function auxilio(
        Request $request,
        SolicitacaoAuxilioRepository $solicitacoes,
        AuxilioService $auxilios,
        ClockInterface $clock,
    ): Response {
        $valor = $request->query->getString('competencia');
        $competencia = '' === $valor ? Competencia::daData($clock->now())->anterior() : Competencia::fromString($valor);

        $linhas = [];
        foreach ($solicitacoes->vigentes() as $solicitacao) {
            $funcionario = $solicitacao->getFuncionario();
            $linhas[] = [
                'funcionario' => $funcionario,
                'solicitacao' => $solicitacao,
                'demonstrativo' => $auxilios->demonstrativo($funcionario, $competencia),
            ];
        }

        return $this->render('admin/relatorio/auxilio.html.twig', [
            'competencia' => $competencia,
            'linhas' => $linhas,
        ]);
    }
}
