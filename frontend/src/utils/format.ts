const moeda = new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' })

export const formatarMoeda = (valor: number) => moeda.format(valor)

/** 125 → "02:05"; -30 → "-00:30"; com sinal: 30 → "+00:30" */
export function formatarMinutos(minutos: number, comSinal = false): string {
  const sinal = minutos < 0 ? '-' : comSinal && minutos > 0 ? '+' : ''
  const abs = Math.abs(minutos)
  const h = String(Math.floor(abs / 60)).padStart(2, '0')
  const m = String(abs % 60).padStart(2, '0')
  return `${sinal}${h}:${m}`
}

/** "2026-09-27" → "27/09/2026" */
export function formatarData(iso: string): string {
  const [a, m, d] = iso.slice(0, 10).split('-')
  return `${d}/${m}/${a}`
}

export function formatarDataHora(iso: string): string {
  return new Date(iso).toLocaleString('pt-BR', { dateStyle: 'short', timeStyle: 'short' })
}

/** Competência atual no formato YYYY-MM. */
export function competenciaAtual(): string {
  const hoje = new Date()
  return `${hoje.getFullYear()}-${String(hoje.getMonth() + 1).padStart(2, '0')}`
}

/** "2026-09" → "setembro de 2026" */
export function nomeCompetencia(competencia: string): string {
  const [a, m] = competencia.split('-').map(Number)
  return new Date(a, m - 1, 1).toLocaleDateString('pt-BR', { month: 'long', year: 'numeric' })
}

export function deslocarCompetencia(competencia: string, meses: number): string {
  const [a, m] = competencia.split('-').map(Number)
  const d = new Date(a, m - 1 + meses, 1)
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`
}

/** Data de hoje no formato YYYY-MM-DD (fuso local). */
export function hojeIso(): string {
  const d = new Date()
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`
}
