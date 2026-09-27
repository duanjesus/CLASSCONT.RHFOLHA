<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Ponto;

use App\Domain\Competencia;
use App\Domain\Ponto\CalculadoraEspelho;
use App\Domain\Ponto\DiaEspelho;
use App\Domain\Ponto\EspelhoMensal;
use App\Enum\SituacaoDia;
use PHPUnit\Framework\TestCase;

/**
 * Setembro/2026: 01 é terça, 05/06 fim de semana, 07 feriado (Independência).
 * "Hoje" = 30/09 para que todo o mês (exceto o dia 30) já tenha passado.
 */
final class CalculadoraEspelhoTest extends TestCase
{
    private const JORNADA = 480;

    private CalculadoraEspelho $calculadora;
    private Competencia $setembro;

    protected function setUp(): void
    {
        $this->calculadora = new CalculadoraEspelho();
        $this->setembro = Competencia::fromString('2026-09');
    }

    public function testDiaUtilComJornadaCompletaTemSaldoZero(): void
    {
        $dia = $this->dia('2026-09-01', batidas: ['08:00', '12:00', '13:00', '17:00']);

        self::assertSame(SituacaoDia::Normal, $dia->situacao);
        self::assertSame(480, $dia->trabalhadoMinutos);
        self::assertSame(0, $dia->saldoMinutos);
    }

    public function testDiferencaDentroDaToleranciaEDesconsiderada(): void
    {
        // 8h08 trabalhadas: 8 min de diferença <= 10 min de tolerância
        $dia = $this->dia('2026-09-01', batidas: ['08:00', '12:00', '13:00', '17:08']);

        self::assertSame(488, $dia->trabalhadoMinutos);
        self::assertSame(0, $dia->saldoMinutos);
    }

    public function testDiferencaAcimaDaToleranciaGeraSaldoIntegral(): void
    {
        $horaExtra = $this->dia('2026-09-01', batidas: ['08:00', '12:00', '13:00', '17:45']);
        $atraso = $this->dia('2026-09-02', batidas: ['08:30', '12:00', '13:00', '17:00']);

        self::assertSame(45, $horaExtra->saldoMinutos);
        self::assertSame(-30, $atraso->saldoMinutos);
    }

    public function testDiaUtilSemBatidaEFalta(): void
    {
        $dia = $this->dia('2026-09-02');

        self::assertSame(SituacaoDia::Falta, $dia->situacao);
        self::assertSame(-480, $dia->saldoMinutos);
    }

    public function testQuantidadeImparDeBatidasIgnoraPeriodoAberto(): void
    {
        $dia = $this->dia('2026-09-03', batidas: ['08:00', '12:00', '13:00']);

        self::assertSame(SituacaoDia::Incompleto, $dia->situacao);
        self::assertSame(240, $dia->trabalhadoMinutos);
        self::assertSame(-240, $dia->saldoMinutos);
    }

    public function testDiaAbonadoNaoGeraSaldoNegativo(): void
    {
        $dia = $this->dia('2026-09-04', abonos: ['2026-09-04' => 'Atestado médico']);

        self::assertSame(SituacaoDia::Abonado, $dia->situacao);
        self::assertSame(0, $dia->saldoMinutos);
        self::assertSame('Atestado médico', $dia->observacao);
    }

    public function testFeriadoEFimDeSemanaNaoTemJornadaEsperada(): void
    {
        $feriado = $this->dia('2026-09-07', feriados: ['2026-09-07' => 'Independência do Brasil']);
        $sabado = $this->dia('2026-09-05');

        self::assertSame(SituacaoDia::Feriado, $feriado->situacao);
        self::assertSame(0, $feriado->esperadoMinutos);
        self::assertSame(SituacaoDia::FimDeSemana, $sabado->situacao);
        self::assertSame(0, $sabado->saldoMinutos);
    }

    public function testTrabalhoNoFimDeSemanaViraCredito(): void
    {
        $sabado = $this->dia('2026-09-05', batidas: ['09:00', '13:00']);

        self::assertSame(240, $sabado->saldoMinutos);
    }

    public function testHojeEFuturoNaoEntramNoSaldo(): void
    {
        $espelho = $this->calcular(hoje: '2026-09-15', batidas: ['2026-09-15' => ['08:00', '12:00']]);

        self::assertSame(SituacaoDia::EmAndamento, $this->doDia($espelho, '2026-09-15')->situacao);
        self::assertSame(SituacaoDia::Futuro, $this->doDia($espelho, '2026-09-16')->situacao);
        self::assertSame(0, $this->doDia($espelho, '2026-09-16')->esperadoMinutos);
    }

    public function testTotaisDoMes(): void
    {
        $batidas = [];
        // Trabalha todos os dias úteis até 10/09, exceto dia 09 (falta)
        foreach (['01', '02', '03', '04', '08', '10'] as $d) {
            $batidas["2026-09-$d"] = ['08:00', '12:00', '13:00', '17:00'];
        }
        $espelho = $this->calcular(
            hoje: '2026-09-11',
            batidas: $batidas,
            feriados: ['2026-09-07' => 'Independência do Brasil'],
        );

        // Setembro/2026 tem 22 dias de semana; menos o feriado = 21 dias úteis
        self::assertSame(21, $espelho->diasUteis);
        self::assertSame(6, $espelho->diasTrabalhados);
        self::assertSame(1, $espelho->faltas);
        self::assertSame(-480, $espelho->saldoMinutos);
        self::assertSame(6 * 480, $espelho->trabalhadoMinutos);
    }

    public function testDiasAntesDaAdmissaoNaoSaoFalta(): void
    {
        // Admitido em 15/09: os dias úteis anteriores não geram jornada nem falta
        $espelho = $this->calculadora->calcular(
            $this->setembro, self::JORNADA, [], [], [], new \DateTimeImmutable('2026-09-17'), new \DateTimeImmutable('2026-09-15'),
        );

        self::assertSame(SituacaoDia::AntesDaAdmissao, $this->doDia($espelho, '2026-09-14')->situacao);
        self::assertSame(0, $this->doDia($espelho, '2026-09-14')->esperadoMinutos);
        self::assertSame(SituacaoDia::Falta, $this->doDia($espelho, '2026-09-15')->situacao);
        self::assertSame(2, $espelho->faltas); // só 15 e 16/09
    }

    public function testMinutosTrabalhadosSomaApenasPares(): void
    {
        $t = static fn (string $h) => new \DateTimeImmutable("2026-09-01 $h");

        self::assertSame(0, CalculadoraEspelho::minutosTrabalhados([]));
        self::assertSame(0, CalculadoraEspelho::minutosTrabalhados([$t('08:00')]));
        self::assertSame(90, CalculadoraEspelho::minutosTrabalhados([$t('08:00'), $t('09:30')]));
    }

    // ---- helpers ----------------------------------------------------------

    /**
     * @param list<string>          $batidas  horários do próprio dia
     * @param array<string, string> $feriados
     * @param array<string, string> $abonos
     */
    private function dia(string $data, array $batidas = [], array $feriados = [], array $abonos = []): DiaEspelho
    {
        $espelho = $this->calcular('2026-09-30', [$data => $batidas], $feriados, $abonos);

        return $this->doDia($espelho, $data);
    }

    /**
     * @param array<string, list<string>> $batidas
     * @param array<string, string>       $feriados
     * @param array<string, string>       $abonos
     */
    private function calcular(string $hoje, array $batidas = [], array $feriados = [], array $abonos = []): EspelhoMensal
    {
        $porDia = [];
        foreach ($batidas as $data => $horarios) {
            $porDia[$data] = array_map(static fn (string $h) => new \DateTimeImmutable("$data $h"), $horarios);
        }

        return $this->calculadora->calcular(
            $this->setembro, self::JORNADA, $porDia, $feriados, $abonos, new \DateTimeImmutable($hoje),
        );
    }

    private function doDia(EspelhoMensal $espelho, string $data): DiaEspelho
    {
        foreach ($espelho->dias as $dia) {
            if ($dia->data->format('Y-m-d') === $data) {
                return $dia;
            }
        }
        self::fail("Dia $data não encontrado no espelho.");
    }
}
