<?php

declare(strict_types=1);

namespace App\Service;

use Dompdf\Dompdf;
use Dompdf\Options;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;

/** Renderiza um template Twig em HTML e converte para PDF com Dompdf. */
final class GeradorPdf
{
    public function __construct(private readonly Environment $twig)
    {
    }

    /** @param array<string, mixed> $contexto */
    public function gerar(string $template, array $contexto): string
    {
        $options = new Options();
        $options->setDefaultFont('DejaVu Sans'); // suporta acentuação
        $options->setIsRemoteEnabled(false);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($this->twig->render($template, $contexto));
        $dompdf->setPaper('A4');
        $dompdf->render();

        // Numeração "Página X de Y": o total só é conhecido após renderizar tudo,
        // por isso é desenhada no canvas (CSS counter(pages) não funciona no Dompdf).
        $fonte = $dompdf->getFontMetrics()->getFont('DejaVu Sans');
        $dompdf->getCanvas()->page_text(524, 807, 'Página {PAGE_NUM} de {PAGE_COUNT}', $fonte, 6, [0.39, 0.45, 0.55]);

        return (string) $dompdf->output();
    }

    /** @param array<string, mixed> $contexto */
    public function resposta(string $template, array $contexto, string $nomeArquivo): Response
    {
        return new Response($this->gerar($template, $contexto), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => HeaderUtils::makeDisposition(HeaderUtils::DISPOSITION_INLINE, $nomeArquivo),
        ]);
    }
}
