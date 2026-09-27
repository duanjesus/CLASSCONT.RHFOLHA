<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\LinhaOnibus;
use App\Form\LinhaOnibusType;
use App\Repository\LinhaOnibusRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/linhas')]
final class LinhaOnibusController extends AdminController
{
    #[Route('', name: 'admin_linhas', methods: ['GET'])]
    public function index(LinhaOnibusRepository $linhas): Response
    {
        return $this->render('admin/linha/index.html.twig', [
            'linhas' => $linhas->findBy([], ['codigo' => 'ASC']),
        ]);
    }

    #[Route('/nova', name: 'admin_linhas_nova', methods: ['GET', 'POST'])]
    public function nova(Request $request): Response
    {
        return $this->processarFormulario($request, $this->createForm(LinhaOnibusType::class, new LinhaOnibus()), 'Nova linha de ônibus', 'admin_linhas', 'Linha cadastrada.');
    }

    #[Route('/{id}/editar', name: 'admin_linhas_editar', methods: ['GET', 'POST'])]
    public function editar(Request $request, LinhaOnibus $linha): Response
    {
        return $this->processarFormulario($request, $this->createForm(LinhaOnibusType::class, $linha), 'Editar linha '.$linha->getCodigo(), 'admin_linhas', 'Linha atualizada.');
    }

    #[Route('/{id}/excluir', name: 'admin_linhas_excluir', methods: ['POST'])]
    public function excluir(Request $request, LinhaOnibus $linha): Response
    {
        return $this->excluirEntidade($request, $linha, 'excluir-linha-'.$linha->getId(), 'admin_linhas', 'Linha '.$linha->getCodigo());
    }
}
