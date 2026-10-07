<?php

declare(strict_types=1);

namespace App\Tests\Unit\Auditoria;

use App\Auditoria\NormalizadorDeValores;
use App\Entity\Cargo;
use App\Enum\StatusAvaliacao;
use PHPUnit\Framework\TestCase;

final class NormalizadorDeValoresTest extends TestCase
{
    private NormalizadorDeValores $normalizador;

    protected function setUp(): void
    {
        $this->normalizador = new NormalizadorDeValores();
    }

    public function testConverteTiposParaTextoLegivel(): void
    {
        self::assertSame('15/09/2026', $this->normalizador->normalizar(new \DateTimeImmutable('2026-09-15')));
        self::assertSame('15/09/2026 08:30', $this->normalizador->normalizar(new \DateTimeImmutable('2026-09-15 08:30')));
        self::assertSame('Aprovada', $this->normalizador->normalizar(StatusAvaliacao::Aprovada));
        self::assertSame('Analista', $this->normalizador->normalizar((new Cargo())->setNome('Analista')));
        self::assertSame('ROLE_RH', $this->normalizador->normalizar(['ROLE_RH']));
        self::assertNull($this->normalizador->normalizar([]));
        self::assertTrue($this->normalizador->normalizar(true));
    }

    public function testSoRegistraOQueMudouDeFato(): void
    {
        $alteracoes = $this->normalizador->alteracoes([
            'nome' => ['Ana', 'Ana Souza'],
            // Mesma data em objetos diferentes: o Doctrine acusa mudança, o log não
            'dataAdmissao' => [new \DateTimeImmutable('2024-03-01'), new \DateTimeImmutable('2024-03-01')],
        ]);

        self::assertSame(['nome' => ['Ana', 'Ana Souza']], $alteracoes);
    }

    public function testSenhaNuncaVaiParaOLog(): void
    {
        $alteracoes = $this->normalizador->alteracoes([
            'password' => ['$2y$13$hashAntigo', '$2y$13$hashNovo'],
        ]);

        self::assertSame(['password' => [NormalizadorDeValores::MASCARA, NormalizadorDeValores::MASCARA]], $alteracoes);
        self::assertStringNotContainsString('hash', (string) json_encode($alteracoes));
    }

    public function testSenhaInicialNaoApareceNaCriacao(): void
    {
        self::assertSame([], $this->normalizador->alteracoes(['password' => [null, '$2y$13$hash']]));
    }
}
