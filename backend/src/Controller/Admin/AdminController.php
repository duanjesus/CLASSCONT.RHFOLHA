<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Funcionario;
use Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\Service\Attribute\Required;

/** Base dos controllers do painel: CRUD com formulário Symfony + Twig. */
abstract class AdminController extends AbstractController
{
    protected EntityManagerInterface $em;

    #[Required]
    public function injetarEntityManager(EntityManagerInterface $em): void
    {
        $this->em = $em;
    }

    protected function usuario(): Funcionario
    {
        $usuario = $this->getUser();
        \assert($usuario instanceof Funcionario);

        return $usuario;
    }

    /**
     * Fluxo padrão de formulário: valida, persiste e redireciona (PRG).
     * Em caso de erro, re-renderiza com status 422 (necessário para o Turbo e boa prática HTTP).
     *
     * @template T of object
     *
     * @param FormInterface<T>                           $form
     * @param (callable(T, FormInterface<T>): void)|null $antesDeGravar
     */
    protected function processarFormulario(
        Request $request,
        FormInterface $form,
        string $titulo,
        string $rotaRetorno,
        string $mensagemSucesso,
        ?callable $antesDeGravar = null,
    ): Response {
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entidade = $form->getData();
            if ($antesDeGravar) {
                $antesDeGravar($entidade, $form);
            }
            $this->em->persist($entidade);
            $this->em->flush();
            $this->addFlash('sucesso', $mensagemSucesso);

            return $this->redirectToRoute($rotaRetorno);
        }

        return $this->render('admin/crud/form.html.twig', [
            'titulo' => $titulo,
            'form' => $form,
            'voltar' => $rotaRetorno,
        ], new Response(null, $form->isSubmitted() ? 422 : 200));
    }

    /** Exclusão via POST com token CSRF; se houver vínculos, informa em vez de quebrar. */
    protected function excluirEntidade(Request $request, object $entidade, string $tokenId, string $rotaRetorno, string $descricao): Response
    {
        if (!$this->isCsrfTokenValid($tokenId, $request->getPayload()->getString('_token'))) {
            $this->addFlash('erro', 'Token de segurança inválido. Tente novamente.');

            return $this->redirectToRoute($rotaRetorno);
        }

        try {
            $this->em->remove($entidade);
            $this->em->flush();
            $this->addFlash('sucesso', \sprintf('%s excluído(a).', $descricao));
        } catch (ForeignKeyConstraintViolationException) {
            $this->addFlash('erro', \sprintf('%s está em uso e não pode ser excluído(a).', $descricao));
        }

        return $this->redirectToRoute($rotaRetorno);
    }
}
