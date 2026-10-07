<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Domain\Competencia;
use App\Repository\FechamentoCompetenciaRepository;
use App\Repository\FuncionarioRepository;
use App\Repository\JustificativaRepository;
use App\Repository\SolicitacaoAuxilioRepository;
use App\Service\FilaDeEmails;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class DashboardController extends AdminController
{
    #[Route('/admin', name: 'admin_dashboard', methods: ['GET'])]
    public function __invoke(
        FuncionarioRepository $funcionarios,
        JustificativaRepository $justificativas,
        SolicitacaoAuxilioRepository $auxilios,
        FechamentoCompetenciaRepository $fechamentos,
        ClockInterface $clock,
        FilaDeEmails $fila,
    ): Response {
        $anterior = Competencia::daData($clock->now())->anterior();

        return $this->render('admin/dashboard.html.twig', [
            'indicadores' => [
                ['rotulo' => 'Servidores ativos', 'valor' => $funcionarios->contarAtivos(), 'rota' => 'admin_funcionarios'],
                ['rotulo' => 'Justificativas pendentes', 'valor' => $justificativas->contarPendentes(), 'rota' => null],
                ['rotulo' => 'Auxílios aguardando', 'valor' => $auxilios->contarPendentes(), 'rota' => null],
                ['rotulo' => 'Auxílios vigentes', 'valor' => \count($auxilios->vigentes()), 'rota' => 'admin_relatorio_auxilio'],
            ],
            'pendentes' => $justificativas->ultimasPendentes(5),
            'competencia_anterior' => $anterior,
            'anterior_fechada' => $fechamentos->estaFechada($anterior),
            'emails_com_falha' => $fila->falhas(),
        ]);
    }
}
