import { useQuery } from '@tanstack/react-query'
import { useState } from 'react'
import { useSearchParams } from 'react-router-dom'
import { abrirPdf, api, mensagemErro } from '../api/client'
import type { Espelho } from '../api/types'
import { Alerta, BadgeSituacao, Botao, CabecalhoPagina, Cartao, Carregando, Indicador, SeletorCompetencia } from '../components/ui'
import { cx } from '../components/estilos'
import { competenciaAtual, formatarData, formatarMinutos } from '../utils/format'

export function EspelhoPonto() {
  const [params, setParams] = useSearchParams()
  const funcionario = params.get('funcionario') // chefia consultando alguém da equipe
  const competencia = params.get('competencia') ?? competenciaAtual()
  const [gerandoPdf, setGerandoPdf] = useState(false)

  const { data: espelho, isLoading, error } = useQuery({
    queryKey: ['espelho', competencia, funcionario],
    queryFn: async () =>
      (await api.get<Espelho>('/ponto/espelho', { params: { competencia, ...(funcionario && { funcionario }) } })).data,
  })

  function mudarCompetencia(c: string) {
    const novo = new URLSearchParams(params)
    novo.set('competencia', c)
    setParams(novo)
  }

  async function pdf() {
    setGerandoPdf(true)
    try {
      await abrirPdf('/ponto/espelho/pdf', { competencia, ...(funcionario && { funcionario }) })
    } finally {
      setGerandoPdf(false)
    }
  }

  const t = espelho?.totais

  return (
    <>
      <CabecalhoPagina
        titulo="Espelho de ponto"
        subtitulo={espelho && (funcionario ? `${espelho.funcionario.nome} · mat. ${espelho.funcionario.matricula} · ${espelho.funcionario.setor}` : `Jornada diária de ${formatarMinutos(espelho.jornadaDiariaMinutos)}`)}
        acoes={
          <>
            <SeletorCompetencia valor={competencia} onChange={mudarCompetencia} maximo={competenciaAtual()} />
            <Botao variante="secundario" onClick={pdf} carregando={gerandoPdf}>Baixar PDF</Botao>
          </>
        }
      />

      {error && <Alerta>{mensagemErro(error)}</Alerta>}
      {isLoading && <Carregando />}

      {espelho && t && (
        <>
          {espelho.fechado && (
            <div className="mb-4"><Alerta tipo="aviso">🔒 Competência fechada pelo RH — os dados já foram enviados à folha.</Alerta></div>
          )}

          <div className="mb-6 grid grid-cols-2 gap-4 md:grid-cols-4">
            <Indicador rotulo="Saldo do mês" valor={formatarMinutos(t.saldoMinutos, true)} destaque={t.saldoMinutos > 0 ? 'positivo' : t.saldoMinutos < 0 ? 'negativo' : undefined} />
            <Indicador rotulo="Faltas" valor={t.faltas} />
            <Indicador rotulo="Dias abonados" valor={t.diasAbonados} />
            <Indicador rotulo="Dias trabalhados" valor={`${t.diasTrabalhados} de ${t.diasUteis}`} />
          </div>

          <Cartao className="overflow-x-auto">
            <table className="min-w-full divide-y divide-slate-200 text-sm">
              <thead className="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                <tr>
                  <th className="px-4 py-3">Dia</th>
                  <th className="px-4 py-3">Batidas</th>
                  <th className="px-4 py-3 text-right">Trabalhado</th>
                  <th className="px-4 py-3 text-right">Previsto</th>
                  <th className="px-4 py-3 text-right">Saldo</th>
                  <th className="px-4 py-3">Situação</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-100">
                {espelho.dias.map((dia) => {
                  const inativo = dia.situacao === 'FIM_DE_SEMANA' || dia.situacao === 'FUTURO'
                  return (
                    <tr key={dia.data} className={cx(inativo ? 'bg-slate-50/60 text-slate-400' : 'text-slate-700', 'hover:bg-slate-50')}>
                      <td className="whitespace-nowrap px-4 py-2.5">
                        {formatarData(dia.data).slice(0, 5)} <span className="text-xs text-slate-400">{dia.diaSemana}</span>
                      </td>
                      <td className="px-4 py-2.5 tabular-nums">
                        {dia.batidas.length ? dia.batidas.join('  ·  ') : '—'}
                        {dia.observacao && <div className="text-xs text-amber-700">{dia.observacao}</div>}
                      </td>
                      <td className="px-4 py-2.5 text-right tabular-nums">{dia.trabalhadoMinutos ? formatarMinutos(dia.trabalhadoMinutos) : '—'}</td>
                      <td className="px-4 py-2.5 text-right tabular-nums">{dia.esperadoMinutos ? formatarMinutos(dia.esperadoMinutos) : '—'}</td>
                      <td
                        className={cx(
                          'px-4 py-2.5 text-right font-medium tabular-nums',
                          dia.saldoMinutos > 0 && 'text-emerald-700',
                          dia.saldoMinutos < 0 && 'text-red-600',
                        )}
                      >
                        {dia.saldoMinutos ? formatarMinutos(dia.saldoMinutos, true) : '—'}
                      </td>
                      <td className="px-4 py-2.5"><BadgeSituacao situacao={dia.situacao} /></td>
                    </tr>
                  )
                })}
              </tbody>
              <tfoot className="bg-slate-50 font-semibold text-slate-900">
                <tr>
                  <td className="px-4 py-3" colSpan={2}>Totais</td>
                  <td className="px-4 py-3 text-right tabular-nums">{formatarMinutos(t.trabalhadoMinutos)}</td>
                  <td className="px-4 py-3 text-right tabular-nums">{formatarMinutos(t.esperadoMinutos)}</td>
                  <td className={cx('px-4 py-3 text-right tabular-nums', t.saldoMinutos < 0 ? 'text-red-600' : 'text-emerald-700')}>
                    {formatarMinutos(t.saldoMinutos, true)}
                  </td>
                  <td className="px-4 py-3">{t.faltas} falta(s)</td>
                </tr>
              </tfoot>
            </table>
          </Cartao>
          <p className="mt-3 text-xs text-slate-500">Diferenças de até 10 minutos por dia são desconsideradas (tolerância da CLT, art. 58 §1º).</p>
        </>
      )}
    </>
  )
}
