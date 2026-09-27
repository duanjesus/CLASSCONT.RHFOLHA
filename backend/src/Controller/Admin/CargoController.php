<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Cargo;
use App\Form\CargoType;
use App\Repository\CargoRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/cargos')]
final class CargoController extends AdminController
{
    #[Route('', name: 'admin_cargos', methods: ['GET'])]
    public function index(CargoRepository $cargos): Response
    {
        return $this->render('admin/cargo/index.html.twig', [
            'cargos' => $cargos->findBy([], ['nome' => 'ASC']),
        ]);
    }

    #[Route('/novo', name: 'admin_cargos_novo', methods: ['GET', 'POST'])]
    public function novo(Request $request): Response
    {
        return $this->processarFormulario($request, $this->createForm(CargoType::class, new Cargo()), 'Novo cargo', 'admin_cargos', 'Cargo cadastrado.');
    }

    #[Route('/{id}/editar', name: 'admin_cargos_editar', methods: ['GET', 'POST'])]
    public function editar(Request $request, Cargo $cargo): Response
    {
        return $this->processarFormulario($request, $this->createForm(CargoType::class, $cargo), 'Editar cargo', 'admin_cargos', 'Cargo atualizado.');
    }

    #[Route('/{id}/excluir', name: 'admin_cargos_excluir', methods: ['POST'])]
    public function excluir(Request $request, Cargo $cargo): Response
    {
        return $this->excluirEntidade($request, $cargo, 'excluir-cargo-'.$cargo->getId(), 'admin_cargos', 'Cargo '.$cargo->getNome());
    }
}
