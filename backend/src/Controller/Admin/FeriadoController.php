<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Feriado;
use App\Form\FeriadoType;
use App\Repository\FeriadoRepository;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/feriados')]
final class FeriadoController extends AdminController
{
    #[Route('', name: 'admin_feriados', methods: ['GET'])]
    public function index(Request $request, FeriadoRepository $feriados, ClockInterface $clock): Response
    {
        $ano = $request->query->getInt('ano') ?: (int) $clock->now()->format('Y');

        return $this->render('admin/feriado/index.html.twig', [
            'ano' => $ano,
            'feriados' => $feriados->doAno($ano),
        ]);
    }

    #[Route('/novo', name: 'admin_feriados_novo', methods: ['GET', 'POST'])]
    public function novo(Request $request): Response
    {
        return $this->processarFormulario($request, $this->createForm(FeriadoType::class, new Feriado()), 'Novo feriado', 'admin_feriados', 'Feriado cadastrado.');
    }

    #[Route('/{id}/editar', name: 'admin_feriados_editar', methods: ['GET', 'POST'])]
    public function editar(Request $request, Feriado $feriado): Response
    {
        return $this->processarFormulario($request, $this->createForm(FeriadoType::class, $feriado), 'Editar feriado', 'admin_feriados', 'Feriado atualizado.');
    }

    #[Route('/{id}/excluir', name: 'admin_feriados_excluir', methods: ['POST'])]
    public function excluir(Request $request, Feriado $feriado): Response
    {
        return $this->excluirEntidade($request, $feriado, 'excluir-feriado-'.$feriado->getId(), 'admin_feriados', 'Feriado');
    }
}
