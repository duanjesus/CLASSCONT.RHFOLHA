<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Enum\AcaoAuditoria;
use App\Repository\RegistroAuditoriaRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class AuditoriaController extends AdminController
{
    /** Trilha de auditoria: quem alterou o quê, quando e de onde. Somente consulta. */
    #[Route('/admin/auditoria', name: 'admin_auditoria', methods: ['GET'])]
    public function __invoke(Request $request, RegistroAuditoriaRepository $registros): Response
    {
        $q = $request->query;
        $filtros = [
            'entidade' => trim($q->getString('entidade')),
            'id' => trim($q->getString('id')),
            'acao' => AcaoAuditoria::tryFrom($q->getString('acao')),
            'autor' => trim($q->getString('autor')),
            'de' => self::data($q->getString('de')),
            'ate' => self::data($q->getString('ate')),
        ];
        $pagina = max(1, $q->getInt('pagina', 1));
        $resultado = $registros->buscar($filtros, $pagina);
        $total = \count($resultado);

        return $this->render('admin/auditoria/index.html.twig', [
            'registros' => $resultado,
            'total' => $total,
            'pagina' => $pagina,
            'paginas' => max(1, (int) ceil($total / RegistroAuditoriaRepository::POR_PAGINA)),
            'filtros' => $filtros,
            'entidades' => $registros->entidadesRegistradas(),
            'acoes' => AcaoAuditoria::cases(),
        ]);
    }

    /** Data do filtro (AAAA-MM-DD); valor inválido é ignorado em vez de quebrar a tela. */
    private static function data(string $valor): ?\DateTimeImmutable
    {
        $data = \DateTimeImmutable::createFromFormat('!Y-m-d', $valor);

        return false !== $data && $data->format('Y-m-d') === $valor ? $data : null;
    }
}
