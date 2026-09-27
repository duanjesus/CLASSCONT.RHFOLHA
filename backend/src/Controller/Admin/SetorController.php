<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Setor;
use App\Form\SetorType;
use App\Repository\SetorRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/setores')]
final class SetorController extends AdminController
{
    #[Route('', name: 'admin_setores', methods: ['GET'])]
    public function index(SetorRepository $setores): Response
    {
        return $this->render('admin/setor/index.html.twig', [
            'setores' => $setores->findBy([], ['sigla' => 'ASC']),
        ]);
    }

    #[Route('/novo', name: 'admin_setores_novo', methods: ['GET', 'POST'])]
    public function novo(Request $request): Response
    {
        return $this->processarFormulario($request, $this->createForm(SetorType::class, new Setor()), 'Novo setor', 'admin_setores', 'Setor cadastrado.');
    }

    #[Route('/{id}/editar', name: 'admin_setores_editar', methods: ['GET', 'POST'])]
    public function editar(Request $request, Setor $setor): Response
    {
        return $this->processarFormulario($request, $this->createForm(SetorType::class, $setor), 'Editar setor '.$setor->getSigla(), 'admin_setores', 'Setor atualizado.');
    }

    #[Route('/{id}/excluir', name: 'admin_setores_excluir', methods: ['POST'])]
    public function excluir(Request $request, Setor $setor): Response
    {
        return $this->excluirEntidade($request, $setor, 'excluir-setor-'.$setor->getId(), 'admin_setores', 'Setor '.$setor->getSigla());
    }
}
