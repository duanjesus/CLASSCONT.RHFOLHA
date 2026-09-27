<?php

declare(strict_types=1);

namespace App\Api;

use App\Domain\Auxilio\DemonstrativoAuxilio;
use App\Domain\Ponto\EspelhoMensal;
use App\Entity\Funcionario;
use App\Entity\Justificativa;
use App\Entity\LinhaOnibus;
use App\Entity\SolicitacaoAuxilio;

/**
 * Converte entidades/objetos de domínio no JSON da API.
 * Mapeamento explícito: o contrato não muda por acidente quando a entidade muda.
 * Valores monetários saem em reais (float com 2 casas) apenas na borda.
 */
final class Representacao
{
    private const DIAS_SEMANA = [1 => 'seg', 'ter', 'qua', 'qui', 'sex', 'sáb', 'dom'];

    /** @return array<string, mixed> */
    public function usuario(Funcionario $f): array
    {
        return [
            'id' => $f->getId(),
            'matricula' => $f->getMatricula(),
            'nome' => $f->getNome(),
            'email' => $f->getEmail(),
            'cargo' => $f->getCargo()?->getNome(),
            'setor' => [
                'id' => $f->getSetor()?->getId(),
                'nome' => $f->getSetor()?->getNome(),
                'sigla' => $f->getSetor()?->getSigla(),
            ],
            'jornadaDiariaMinutos' => $f->getJornadaDiariaMinutos(),
            'roles' => $f->getRoles(),
            'ehChefia' => $f->isChefia(),
        ];
    }

    /** @return array{id: ?int, nome: string, matricula: string} */
    public function funcionarioResumo(Funcionario $f): array
    {
        return ['id' => $f->getId(), 'nome' => $f->getNome(), 'matricula' => $f->getMatricula()];
    }

    /** @return array<string, mixed> */
    public function espelho(EspelhoMensal $e, Funcionario $f, bool $fechado): array
    {
        $dias = [];
        foreach ($e->dias as $dia) {
            $dias[] = [
                'data' => $dia->data->format('Y-m-d'),
                'diaSemana' => self::DIAS_SEMANA[(int) $dia->data->format('N')],
                'batidas' => array_map(static fn (\DateTimeImmutable $b) => $b->format('H:i'), $dia->batidas),
                'trabalhadoMinutos' => $dia->trabalhadoMinutos,
                'esperadoMinutos' => $dia->esperadoMinutos,
                'saldoMinutos' => $dia->saldoMinutos,
                'situacao' => $dia->situacao->value,
                'observacao' => $dia->observacao,
            ];
        }

        return [
            'competencia' => (string) $e->competencia,
            'fechado' => $fechado,
            'funcionario' => $this->funcionarioResumo($f) + [
                'setor' => $f->getSetor()?->getSigla(),
                'cargo' => $f->getCargo()?->getNome(),
            ],
            'jornadaDiariaMinutos' => $e->jornadaDiariaMinutos,
            'dias' => $dias,
            'totais' => [
                'trabalhadoMinutos' => $e->trabalhadoMinutos,
                'esperadoMinutos' => $e->esperadoMinutos,
                'saldoMinutos' => $e->saldoMinutos,
                'faltas' => $e->faltas,
                'diasTrabalhados' => $e->diasTrabalhados,
                'diasAbonados' => $e->diasAbonados,
                'diasUteis' => $e->diasUteis,
            ],
        ];
    }

    /** @return array<string, mixed> */
    public function justificativa(Justificativa $j): array
    {
        return [
            'id' => $j->getId(),
            'data' => $j->getData()->format('Y-m-d'),
            'tipo' => $j->getTipo()->value,
            'tipoLabel' => $j->getTipo()->label(),
            'motivo' => $j->getMotivo(),
            'status' => $j->getStatus()->value,
            'funcionario' => $this->funcionarioResumo($j->getFuncionario()),
            'avaliadoPor' => $j->getAvaliadoPor()?->getNome(),
            'avaliadoEm' => $j->getAvaliadoEm()?->format(\DATE_ATOM),
            'observacaoAvaliacao' => $j->getObservacaoAvaliacao(),
            'criadoEm' => $j->getCriadoEm()->format(\DATE_ATOM),
        ];
    }

    /** @return array{id: ?int, codigo: string, nome: string, tarifa: float} */
    public function linha(LinhaOnibus $l): array
    {
        return [
            'id' => $l->getId(),
            'codigo' => $l->getCodigo(),
            'nome' => $l->getNome(),
            'tarifa' => self::reais($l->getTarifaCentavos()),
        ];
    }

    /** @return array<string, mixed> */
    public function solicitacao(SolicitacaoAuxilio $s): array
    {
        $trajetos = [];
        foreach ($s->getTrajetos() as $t) {
            $trajetos[] = ['linha' => $this->linha($t->getLinha()), 'sentido' => $t->getSentido()->value];
        }

        return [
            'id' => $s->getId(),
            'status' => $s->getStatus()->value,
            'criadoEm' => $s->getCriadoEm()->format(\DATE_ATOM),
            'trajetos' => $trajetos,
            'valorDiario' => self::reais($s->getValorDiarioCentavos()),
            'funcionario' => $this->funcionarioResumo($s->getFuncionario()),
            'avaliadoPor' => $s->getAvaliadoPor()?->getNome(),
            'avaliadoEm' => $s->getAvaliadoEm()?->format(\DATE_ATOM),
            'observacaoAvaliacao' => $s->getObservacaoAvaliacao(),
        ];
    }

    /** @return array<string, mixed> */
    public function demonstrativo(DemonstrativoAuxilio $d): array
    {
        return [
            'competencia' => (string) $d->competencia,
            'valorDiario' => self::reais($d->valorDiarioCentavos),
            'diasUteis' => $d->diasUteis,
            'diasTrabalhados' => $d->diasTrabalhados,
            'valorBruto' => self::reais($d->valorBrutoCentavos),
            'salarioBase' => self::reais($d->salarioBaseCentavos),
            'percentualDesconto' => $d->percentualDesconto,
            'valorDesconto' => self::reais($d->valorDescontoCentavos),
            'valorLiquido' => self::reais($d->valorLiquidoCentavos),
        ];
    }

    public static function reais(int $centavos): float
    {
        return round($centavos / 100, 2);
    }
}
