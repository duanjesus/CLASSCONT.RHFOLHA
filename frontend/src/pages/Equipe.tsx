import { useQuery } from '@tanstack/react-query'
import { useState } from 'react'
import { Link } from 'react-router-dom'
import { api } from '../api/client'
import type { MembroEquipe } from '../api/types'
import { CabecalhoPagina, Cartao, Carregando, SeletorCompetencia, Vazio } from '../components/ui'
import { cx } from '../components/estilos'
import { competenciaAtual, formatarMinutos } from '../utils/format'

export function Equipe() {
  const [competencia, setCompetencia] = useState(competenciaAtual)
  const { data, isLoading } = useQuery({
    queryKey: ['equipe', competencia],
    queryFn: async () => (await api.get<MembroEquipe[]>('/equipe', { params: { competencia } })).data,
  })

  return (
    <>
      <CabecalhoPagina
        titulo="Minha equipe"
        subtitulo="Situação do ponto dos servidores do seu setor."
        acoes={<SeletorCompetencia valor={competencia} onChange={setCompetencia} maximo={competenciaAtual()} />}
      />
      <Cartao className="overflow-x-auto">
        {isLoading && <Carregando />}
        {data?.length === 0 && <Vazio>Nenhum servidor na sua equipe.</Vazio>}
        {!!data?.length && (
          <table className="min-w-full divide-y divide-slate-200 text-sm">
            <thead className="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
              <tr>
                <th className="px-4 py-3">Servidor</th>
                <th className="px-4 py-3 text-right">Banco de horas</th>
                <th className="px-4 py-3 text-right">Faltas</th>
                <th className="px-4 py-3 text-right">Dias trabalhados</th>
                <th className="px-4 py-3 text-right">Pendências</th>
                <th className="px-4 py-3" />
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-100">
              {data.map((m) => (
                <tr key={m.funcionario.id} className="hover:bg-slate-50">
                  <td className="px-4 py-3">
                    <p className="font-medium text-slate-900">{m.funcionario.nome}</p>
                    <p className="text-xs text-slate-500">{m.funcionario.cargo} · mat. {m.funcionario.matricula}</p>
                  </td>
                  <td className={cx('px-4 py-3 text-right font-medium tabular-nums', m.saldoMinutos > 0 && 'text-emerald-700', m.saldoMinutos < 0 && 'text-red-600')}>
                    {formatarMinutos(m.saldoMinutos, true)}
                  </td>
                  <td className={cx('px-4 py-3 text-right tabular-nums', m.faltas > 0 && 'font-medium text-red-600')}>{m.faltas}</td>
                  <td className="px-4 py-3 text-right tabular-nums">{m.diasTrabalhados}</td>
                  <td className="px-4 py-3 text-right">
                    {m.pendencias > 0 ? (
                      <Link to="/aprovacoes" className="rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-800 hover:bg-amber-200">
                        {m.pendencias} para avaliar
                      </Link>
                    ) : (
                      <span className="text-slate-400">—</span>
                    )}
                  </td>
                  <td className="px-4 py-3 text-right">
                    <Link to={`/espelho?funcionario=${m.funcionario.id}&competencia=${competencia}`} className="font-medium text-marca-600 hover:underline">
                      Espelho
                    </Link>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        )}
      </Cartao>
    </>
  )
}
