export type Papel = 'ROLE_FUNCIONARIO' | 'ROLE_CHEFIA' | 'ROLE_RH'

export interface SetorResumo {
  id: number
  nome: string
  sigla: string
}

export interface Usuario {
  id: number
  matricula: string
  nome: string
  email: string
  cargo: string
  setor: SetorResumo
  jornadaDiariaMinutos: number
  roles: Papel[]
  ehChefia: boolean
}

export interface FuncionarioResumo {
  id: number
  nome: string
  matricula: string
}

export type TipoBatida = 'ENTRADA' | 'SAIDA_ALMOCO' | 'RETORNO_ALMOCO' | 'SAIDA'

export interface PontoHoje {
  data: string
  batidas: string[]
  proximaBatida: TipoBatida | null
  competenciaFechada: boolean
}

export type SituacaoDia =
  | 'NORMAL'
  | 'FALTA'
  | 'INCOMPLETO'
  | 'ABONADO'
  | 'FERIADO'
  | 'FIM_DE_SEMANA'
  | 'EM_ANDAMENTO'
  | 'FUTURO'
  | 'ANTES_ADMISSAO'

export interface DiaEspelho {
  data: string
  diaSemana: string
  batidas: string[]
  trabalhadoMinutos: number
  esperadoMinutos: number
  saldoMinutos: number
  situacao: SituacaoDia
  observacao: string | null
}

export interface Espelho {
  competencia: string
  fechado: boolean
  funcionario: FuncionarioResumo & { setor: string; cargo: string }
  jornadaDiariaMinutos: number
  dias: DiaEspelho[]
  totais: {
    trabalhadoMinutos: number
    esperadoMinutos: number
    saldoMinutos: number
    faltas: number
    diasTrabalhados: number
    diasAbonados: number
    diasUteis: number
  }
}

export type StatusAvaliacao = 'PENDENTE' | 'APROVADA' | 'RECUSADA' | 'SUBSTITUIDA'

export type TipoJustificativa =
  | 'ATESTADO_MEDICO'
  | 'FALTA_JUSTIFICADA'
  | 'SERVICO_EXTERNO'
  | 'ESQUECIMENTO_BATIDA'

export interface Justificativa {
  id: number
  data: string
  tipo: TipoJustificativa
  tipoLabel: string
  motivo: string
  status: StatusAvaliacao
  funcionario: FuncionarioResumo
  avaliadoPor: string | null
  avaliadoEm: string | null
  observacaoAvaliacao: string | null
  criadoEm: string
}

export interface LinhaOnibus {
  id: number
  codigo: string
  nome: string
  tarifa: number
}

export type Sentido = 'IDA' | 'VOLTA'

export interface Trajeto {
  linha: LinhaOnibus
  sentido: Sentido
}

export interface SolicitacaoAuxilio {
  id: number
  status: StatusAvaliacao
  criadoEm: string
  trajetos: Trajeto[]
  valorDiario: number
  funcionario: FuncionarioResumo
  avaliadoPor: string | null
  avaliadoEm: string | null
  observacaoAvaliacao: string | null
}

export interface MeuAuxilio {
  vigente: SolicitacaoAuxilio | null
  pendente: SolicitacaoAuxilio | null
}

export interface DemonstrativoAuxilio {
  competencia: string
  valorDiario: number
  diasUteis: number
  diasTrabalhados: number
  valorBruto: number
  salarioBase: number
  percentualDesconto: number
  valorDesconto: number
  valorLiquido: number
}

export interface MembroEquipe {
  funcionario: FuncionarioResumo & { cargo: string }
  saldoMinutos: number
  faltas: number
  diasTrabalhados: number
  pendencias: number
}

export interface ErroApi {
  erro: string
  detalhes?: Record<string, string>
}
