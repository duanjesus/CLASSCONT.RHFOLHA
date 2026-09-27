import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useState } from 'react'
import { Link } from 'react-router-dom'
import { api, mensagemErro } from '../api/client'
import type { Justificativa, SolicitacaoAuxilio } from '../api/types'
import { Alerta, Botao, CabecalhoPagina, Cartao, Carregando, Vazio } from '../components/ui'
import { classeCampo, cx } from '../components/estilos'
import { formatarData, formatarDataHora, formatarMoeda } from '../utils/format'

type Aba = 'justificativas' | 'auxilios'
type Decisao = 'APROVAR' | 'RECUSAR'

/** Botões aprovar/recusar com campo de observação (obrigatório para recusar). */
function Avaliacao({ url, onConcluido }: { url: string; onConcluido: () => void }) {
  const [observacao, setObservacao] = useState('')
  const avaliar = useMutation({
    mutationFn: async (decisao: Decisao) => api.post(url, { decisao, observacao: observacao || null }),
    onSuccess: onConcluido,
  })

  return (
    <div className="mt-3 space-y-2">
      {avaliar.isError && <Alerta>{mensagemErro(avaliar.error)}</Alerta>}
      <div className="flex flex-wrap items-center gap-2">
        <input
          className={cx(classeCampo, 'min-w-60 flex-1')}
          placeholder="Observação (obrigatória para recusar)"
          value={observacao}
          onChange={(e) => setObservacao(e.target.value)}
          aria-label="Observação"
        />
        <Botao variante="perigo" onClick={() => avaliar.mutate('RECUSAR')} carregando={avaliar.isPending && avaliar.variables === 'RECUSAR'} disabled={avaliar.isPending}>
          Recusar
        </Botao>
        <Botao onClick={() => avaliar.mutate('APROVAR')} carregando={avaliar.isPending && avaliar.variables === 'APROVAR'} disabled={avaliar.isPending}>
          Aprovar
        </Botao>
      </div>
    </div>
  )
}

export function Aprovacoes() {
  const [aba, setAba] = useState<Aba>('justificativas')
  const queryClient = useQueryClient()

  const justificativas = useQuery({
    queryKey: ['pendentes', 'justificativas'],
    queryFn: async () => (await api.get<Justificativa[]>('/justificativas/pendentes')).data,
  })
  const auxilios = useQuery({
    queryKey: ['pendentes', 'auxilios'],
    queryFn: async () => (await api.get<SolicitacaoAuxilio[]>('/auxilio/pendentes')).data,
  })

  const atualizar = () => {
    queryClient.invalidateQueries({ queryKey: ['pendentes'] })
    queryClient.invalidateQueries({ queryKey: ['pendencias-total'] })
    queryClient.invalidateQueries({ queryKey: ['equipe'] })
  }

  const abas: { id: Aba; rotulo: string; total: number }[] = [
    { id: 'justificativas', rotulo: 'Justificativas', total: justificativas.data?.length ?? 0 },
    { id: 'auxilios', rotulo: 'Auxílio-transporte', total: auxilios.data?.length ?? 0 },
  ]

  return (
    <>
      <CabecalhoPagina titulo="Aprovações" subtitulo="Pedidos dos servidores sob sua chefia aguardando decisão. O servidor é avisado por e-mail." />

      <div className="mb-4 flex gap-1 rounded-lg bg-slate-100 p-1 text-sm sm:w-fit" role="tablist">
        {abas.map((a) => (
          <button
            key={a.id}
            role="tab"
            aria-selected={aba === a.id}
            onClick={() => setAba(a.id)}
            className={cx('flex-1 whitespace-nowrap rounded-md px-4 py-2 font-medium transition', aba === a.id ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-500 hover:text-slate-700')}
          >
            {a.rotulo}
            {a.total > 0 && <span className="ml-2 rounded-full bg-amber-100 px-2 py-0.5 text-xs text-amber-800">{a.total}</span>}
          </button>
        ))}
      </div>

      {aba === 'justificativas' && (
        <Cartao>
          {justificativas.isLoading && <Carregando />}
          {justificativas.data?.length === 0 && <Vazio>Nenhuma justificativa pendente.</Vazio>}
          <ul className="divide-y divide-slate-100">
            {justificativas.data?.map((j) => (
              <li key={j.id} className="p-5">
                <div className="flex flex-wrap items-baseline justify-between gap-2">
                  <p className="font-medium text-slate-900">
                    {j.funcionario.nome} <span className="text-sm font-normal text-slate-500">· mat. {j.funcionario.matricula}</span>
                  </p>
                  <Link to={`/espelho?funcionario=${j.funcionario.id}&competencia=${j.data.slice(0, 7)}`} className="text-sm font-medium text-marca-600 hover:underline">
                    Ver espelho
                  </Link>
                </div>
                <p className="mt-1 text-sm">
                  <strong>{formatarData(j.data)}</strong> · {j.tipoLabel}
                </p>
                <p className="mt-1 text-sm text-slate-600">“{j.motivo}”</p>
                <p className="mt-1 text-xs text-slate-400">Enviada em {formatarDataHora(j.criadoEm)}</p>
                <Avaliacao url={`/justificativas/${j.id}/avaliar`} onConcluido={atualizar} />
              </li>
            ))}
          </ul>
        </Cartao>
      )}

      {aba === 'auxilios' && (
        <Cartao>
          {auxilios.isLoading && <Carregando />}
          {auxilios.data?.length === 0 && <Vazio>Nenhum pedido de auxílio pendente.</Vazio>}
          <ul className="divide-y divide-slate-100">
            {auxilios.data?.map((s) => (
              <li key={s.id} className="p-5">
                <div className="flex flex-wrap items-baseline justify-between gap-2">
                  <p className="font-medium text-slate-900">
                    {s.funcionario.nome} <span className="text-sm font-normal text-slate-500">· mat. {s.funcionario.matricula}</span>
                  </p>
                  <span className="text-sm text-slate-600">
                    Valor diário <strong className="tabular-nums text-slate-900">{formatarMoeda(s.valorDiario)}</strong>
                  </span>
                </div>
                <div className="mt-2 flex flex-wrap gap-2">
                  {s.trajetos.map((t, i) => (
                    <span key={i} className="rounded bg-slate-100 px-2 py-1 text-xs text-slate-700">
                      {t.sentido === 'IDA' ? 'Ida' : 'Volta'} · {t.linha.codigo} {t.linha.nome} · {formatarMoeda(t.linha.tarifa)}
                    </span>
                  ))}
                </div>
                <p className="mt-1 text-xs text-slate-400">Enviado em {formatarDataHora(s.criadoEm)}</p>
                <Avaliacao url={`/auxilio/${s.id}/avaliar`} onConcluido={atualizar} />
              </li>
            ))}
          </ul>
        </Cartao>
      )}
    </>
  )
}
