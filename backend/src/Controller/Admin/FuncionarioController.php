<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Domain\Competencia;
use App\Entity\Funcionario;
use App\Form\FuncionarioType;
use App\Repository\FechamentoCompetenciaRepository;
use App\Repository\FuncionarioRepository;
use App\Service\EspelhoService;
use App\Service\GeradorPdf;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/funcionarios')]
final class FuncionarioController extends AdminController
{
    public function __construct(private readonly UserPasswordHasherInterface $hasher)
    {
    }

    #[Route('', name: 'admin_funcionarios', methods: ['GET'])]
    public function index(Request $request, FuncionarioRepository $funcionarios): Response
    {
        $busca = $request->query->getString('q');

        return $this->render('admin/funcionario/index.html.twig', [
            'funcionarios' => $funcionarios->listagemAdmin($busca),
            'busca' => $busca,
        ]);
    }

    #[Route('/novo', name: 'admin_funcionarios_novo', methods: ['GET', 'POST'])]
    public function novo(Request $request): Response
    {
        $funcionario = (new Funcionario())->setDataAdmissao(new \DateTimeImmutable('today'));
        $form = $this->createForm(FuncionarioType::class, $funcionario, ['exigir_senha' => true]);

        return $this->processarFormulario($request, $form, 'Novo funcionário', 'admin_funcionarios', 'Funcionário cadastrado.', $this->definirSenha(...));
    }

    #[Route('/{id}/editar', name: 'admin_funcionarios_editar', methods: ['GET', 'POST'])]
    public function editar(Request $request, Funcionario $funcionario): Response
    {
        $form = $this->createForm(FuncionarioType::class, $funcionario);

        return $this->processarFormulario($request, $form, 'Editar '.$funcionario->getNome(), 'admin_funcionarios', 'Cadastro atualizado.', $this->definirSenha(...));
    }

    /**
     * Servidores não são excluídos (o histórico de ponto é documento funcional):
     * apenas ativados/desativados.
     */
    #[Route('/{id}/alternar-ativo', name: 'admin_funcionarios_alternar', methods: ['POST'])]
    public function alternarAtivo(Request $request, Funcionario $funcionario): Response
    {
        if ($this->isCsrfTokenValid('alternar-'.$funcionario->getId(), $request->getPayload()->getString('_token'))) {
            $funcionario->setAtivo(!$funcionario->isAtivo());
            $this->em->flush();
            $this->addFlash('sucesso', \sprintf('%s %s.', $funcionario->getNome(), $funcionario->isAtivo() ? 'reativado(a)' : 'desativado(a)'));
        }

        return $this->redirectToRoute('admin_funcionarios');
    }

    #[Route('/{id}/espelho', name: 'admin_funcionarios_espelho', methods: ['GET'])]
    public function espelho(
        Request $request,
        Funcionario $funcionario,
        EspelhoService $espelhos,
        FechamentoCompetenciaRepository $fechamentos,
        ClockInterface $clock,
        GeradorPdf $pdf,
    ): Response {
        $valor = $request->query->getString('competencia');
        $competencia = '' === $valor ? Competencia::daData($clock->now()) : Competencia::fromString($valor);

        $contexto = [
            'funcionario' => $funcionario,
            'espelho' => $espelhos->gerar($funcionario, $competencia),
            'fechado' => $fechamentos->estaFechada($competencia),
            'emitido_em' => $clock->now(),
        ];

        if ('pdf' === $request->query->getString('formato')) {
            return $pdf->resposta('pdf/espelho.html.twig', $contexto, \sprintf('espelho-%s-%s.pdf', $funcionario->getMatricula(), $competencia));
        }

        return $this->render('admin/funcionario/espelho.html.twig', $contexto);
    }

    /** @param FormInterface<Funcionario> $form */
    private function definirSenha(Funcionario $funcionario, FormInterface $form): void
    {
        $senha = $form->get('senha')->getData();
        if (\is_string($senha) && '' !== $senha) {
            $funcionario->setPassword($this->hasher->hashPassword($funcionario, $senha));
        }
    }
}
