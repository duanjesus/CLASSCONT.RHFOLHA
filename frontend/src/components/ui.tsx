import type { ButtonHTMLAttributes, ReactNode } from 'react'
import type { SituacaoDia, StatusAvaliacao } from '../api/types'
import { deslocarCompetencia, nomeCompetencia } from '../utils/format'
import { cx } from './estilos'

type Variante = 'primario' | 'secundario' | 'perigo' | 'fantasma'

const variantes: Record<Variante, string> = {
  primario: 'bg-marca-700 text-white hover:bg-marca-600 shadow-sm',
  secundario: 'border border-slate-300 bg-white text-slate-700 hover:bg-slate-50 shadow-sm',
  perigo: 'bg-red-600 text-white hover:bg-red-700 shadow-sm',
  fantasma: 'text-slate-600 hover:bg-slate-100',
}

export function Botao({
  variante = 'primario',
  carregando = false,
  className,
  children,
  disabled,
  ...props
}: ButtonHTMLAttributes<HTMLButtonElement> & { variante?: Variante; carregando?: boolean }) {
  return (
    <button
      {...props}
      disabled={disabled || carregando}
      className={cx(
        'inline-flex items-center justify-center gap-2 rounded-lg px-4 py-2 text-sm font-medium transition',
        'focus:outline-none focus-visible:ring-2 focus-visible:ring-marca-500 focus-visible:ring-offset-2',
        'disabled:cursor-not-allowed disabled:opacity-50',
        variantes[variante],
        className,
      )}
    >
      {carregando && <Spinner className="h-4 w-4" />}
      {children}
    </button>
  )
}

export function Cartao({ children, className }: { children: ReactNode; className?: string }) {
  return <div className={cx('rounded-xl border border-slate-200 bg-white shadow-sm', className)}>{children}</div>
}

export function CabecalhoPagina({ titulo, subtitulo, acoes }: { titulo: string; subtitulo?: ReactNode; acoes?: ReactNode }) {
  return (
    <div className="mb-6 flex flex-wrap items-end justify-between gap-4">
      <div>
        <h1 className="text-2xl font-semibold text-slate-900">{titulo}</h1>
        {subtitulo && <p className="mt-1 text-sm text-slate-500">{subtitulo}</p>}
      </div>
      {acoes && <div className="flex flex-wrap items-center gap-3">{acoes}</div>}
    </div>
  )
}

export function Spinner({ className = 'h-6 w-6' }: { className?: string }) {
  return (
    <svg className={cx('animate-spin text-current', className)} viewBox="0 0 24 24" fill="none" aria-hidden="true">
      <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
      <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 0 1 8-8v4a4 4 0 0 0-4 4H4z" />
    </svg>
  )
}

export function Carregando({ texto = 'Carregando…' }: { texto?: string }) {
  return (
    <div className="flex items-center justify-center gap-3 py-16 text-sm text-slate-500" role="status">
      <Spinner className="h-5 w-5 text-marca-500" /> {texto}
    </div>
  )
}

export function Vazio({ children }: { children: ReactNode }) {
  return <div className="px-6 py-12 text-center text-sm text-slate-500">{children}</div>
}

export function Alerta({ tipo = 'erro', children }: { tipo?: 'erro' | 'sucesso' | 'aviso'; children: ReactNode }) {
  const cores = {
    erro: 'border-red-200 bg-red-50 text-red-800',
    sucesso: 'border-emerald-200 bg-emerald-50 text-emerald-800',
    aviso: 'border-amber-200 bg-amber-50 text-amber-900',
  }
  return (
    <div role={tipo === 'erro' ? 'alert' : 'status'} className={cx('rounded-lg border px-4 py-3 text-sm', cores[tipo])}>
      {children}
    </div>
  )
}

const coresStatus: Record<StatusAvaliacao, string> = {
  PENDENTE: 'bg-amber-50 text-amber-700 ring-amber-600/20',
  APROVADA: 'bg-emerald-50 text-emerald-700 ring-emerald-600/20',
  RECUSADA: 'bg-red-50 text-red-700 ring-red-600/20',
  SUBSTITUIDA: 'bg-slate-100 text-slate-600 ring-slate-500/20',
}
const rotulosStatus: Record<StatusAvaliacao, string> = {
  PENDENTE: 'Pendente',
  APROVADA: 'Aprovada',
  RECUSADA: 'Recusada',
  SUBSTITUIDA: 'Substituída',
}

export function BadgeStatus({ status }: { status: StatusAvaliacao }) {
  return (
    <span className={cx('inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium ring-1 ring-inset', coresStatus[status])}>
      {rotulosStatus[status]}
    </span>
  )
}

const situacoes: Record<SituacaoDia, { rotulo: string; classe: string }> = {
  NORMAL: { rotulo: 'Normal', classe: 'bg-emerald-50 text-emerald-700' },
  FALTA: { rotulo: 'Falta', classe: 'bg-red-50 text-red-700' },
  INCOMPLETO: { rotulo: 'Incompleto', classe: 'bg-amber-50 text-amber-700' },
  ABONADO: { rotulo: 'Abonado', classe: 'bg-sky-50 text-sky-700' },
  FERIADO: { rotulo: 'Feriado', classe: 'bg-violet-50 text-violet-700' },
  FIM_DE_SEMANA: { rotulo: 'Fim de semana', classe: 'bg-slate-100 text-slate-500' },
  EM_ANDAMENTO: { rotulo: 'Hoje', classe: 'bg-marca-50 text-marca-700' },
  FUTURO: { rotulo: '—', classe: 'text-slate-400' },
}

export function BadgeSituacao({ situacao }: { situacao: SituacaoDia }) {
  const s = situacoes[situacao]
  return <span className={cx('inline-flex rounded-full px-2 py-0.5 text-xs font-medium', s.classe)}>{s.rotulo}</span>
}

export function SeletorCompetencia({ valor, onChange, maximo }: { valor: string; onChange: (c: string) => void; maximo?: string }) {
  const proxima = deslocarCompetencia(valor, 1)
  const bloqueiaProxima = maximo !== undefined && proxima > maximo
  return (
    <div className="inline-flex items-center rounded-lg border border-slate-300 bg-white text-sm shadow-sm">
      <button type="button" className="px-3 py-2 text-slate-500 hover:text-marca-700" onClick={() => onChange(deslocarCompetencia(valor, -1))} aria-label="Mês anterior">
        ←
      </button>
      <span className="min-w-40 border-x border-slate-200 px-3 py-2 text-center font-medium first-letter:uppercase">{nomeCompetencia(valor)}</span>
      <button
        type="button"
        className="px-3 py-2 text-slate-500 hover:text-marca-700 disabled:opacity-30"
        onClick={() => onChange(proxima)}
        disabled={bloqueiaProxima}
        aria-label="Próximo mês"
      >
        →
      </button>
    </div>
  )
}

export function Indicador({ rotulo, valor, detalhe, destaque }: { rotulo: string; valor: ReactNode; detalhe?: ReactNode; destaque?: 'positivo' | 'negativo' }) {
  return (
    <Cartao className="p-4">
      <p className="text-xs font-medium uppercase tracking-wide text-slate-500">{rotulo}</p>
      <p
        className={cx(
          'mt-1 text-2xl font-semibold tabular-nums',
          destaque === 'positivo' && 'text-emerald-700',
          destaque === 'negativo' && 'text-red-600',
          !destaque && 'text-slate-900',
        )}
      >
        {valor}
      </p>
      {detalhe && <p className="mt-0.5 text-xs text-slate-500">{detalhe}</p>}
    </Cartao>
  )
}
