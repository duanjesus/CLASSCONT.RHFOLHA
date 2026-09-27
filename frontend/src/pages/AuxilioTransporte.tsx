import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import axios from 'axios'
import { useState } from 'react'
import { abrirPdf, api, mensagemErro } from '../api/client'
import type { DemonstrativoAuxilio, LinhaOnibus, MeuAuxilio, Sentido, SolicitacaoAuxilio } from '../api/types'
import { Alerta, BadgeStatus, Botao, CabecalhoPagina, Cartao, Carregando, SeletorCompetencia } from '../components/ui'
import { classeCampo, cx } from '../components/estilos'
import { competenciaAtual, deslocarCompetencia, formatarDataHora, formatarMoeda } from '../utils/format'

interface LinhaForm {
  chave: number
  linhaId: number | ''
  sentido: Sentido
}

let proximaChave = 1
const novaLinha = (sentido: Sentido): LinhaForm => ({ chave: proximaChave++, linhaId: '', sentido })

function Itinerario({ solicitacao }: { solicitacao: SolicitacaoAuxilio }) {
  return (
    <ul className="space-y-1.5 text-sm">
      {solicitacao.trajetos.map((t, i) => (
        <li key={i} className="flex items-center justify-between gap-3">
          <span>
            <span className={cx('mr-2 inline-block w-12 rounded px-1.5 py-0.5 text-center text-xs font-medium', t.sentido === 'IDA' ? 'bg-marca-50 text-marca-700' : 'bg-teal-50 text-teal-700')}>
              {t.sentido === 'IDA' ? 'Ida' : 'Volta'}
            </span>
            <strong>{t.linha.codigo}</strong> <span className="text-slate-600">{t.linha.nome}</span>
          </span>
          <span className="tabular-nums text-slate-600">{formatarMoeda(t.linha.tarifa)}</span>
        </li>
      ))}
      <li className="flex justify-between border-t border-slate-100 pt-2 font-semibold">
        <span>Valor diário</span>
        <span className="tabular-nums">{formatarMoeda(solicitacao.valorDiario)}</span>
      </li>
    </ul>
  )
}

export function AuxilioTransporte() {
  const queryClient = useQueryClient()
  const [competencia, setCompetencia] = useState(() => deslocarCompetencia(competenciaAtual(), -1))
  const [editando, setEditando] = useState(false)
  const [itens, setItens] = useState<LinhaForm[]>(() => [novaLinha('IDA'), novaLinha('VOLTA')])

  const meu = useQuery({ queryKey: ['auxilio'], queryFn: async () => (await api.get<MeuAuxilio>('/auxilio')).data })
  const linhas = useQuery({ queryKey: ['linhas'], queryFn: async () => (await api.get<LinhaOnibus[]>('/linhas')).data })
  const demonstrativo = useQuery({
    queryKey: ['demonstrativo', competencia],
    enabled: !!meu.data?.vigente,
    retry: false,
    queryFn: async () => {
      try {
        return (await api.get<DemonstrativoAuxilio>('/auxilio/demonstrativo', { params: { competencia } })).data
      } catch (e) {
        if (axios.isAxiosError(e) && e.response?.status === 404) return null
        throw e
      }
    },
  })

  const solicitar = useMutation({
    mutationFn: async () =>
      (await api.post<SolicitacaoAuxilio>('/auxilio', {
        trajetos: itens.map(({ linhaId, sentido }) => ({ linhaId: Number(linhaId), sentido })),
      })).data,
    onSuccess: () => {
      setEditando(false)
      setItens([novaLinha('IDA'), novaLinha('VOLTA')])
      queryClient.invalidateQueries({ queryKey: ['auxilio'] })
    },
  })

  const tarifaDe = (id: number | '') => linhas.data?.find((l) => l.id === id)?.tarifa ?? 0
  const totalDiario = itens.reduce((s, i) => s + tarifaDe(i.linhaId), 0)
  const atualizar = (chave: number, dados: Partial<LinhaForm>) => setItens((lista) => lista.map((i) => (i.chave === chave ? { ...i, ...dados } : i)))

  if (meu.isLoading) return <Carregando />
  const { vigente, pendente } = meu.data ?? { vigente: null, pendente: null }
  const d = demonstrativo.data

  return (
    <>
      <CabecalhoPagina
        titulo="Auxílio-transporte"
        subtitulo="O valor mensal é calculado pelos dias efetivamente trabalhados no ponto, descontada a sua cota de 6% do salário-base."
        acoes={!editando && <Botao onClick={() => setEditando(true)}>{vigente || pendente ? 'Alterar itinerário' : 'Solicitar auxílio'}</Botao>}
      />

      {editando && (
        <Cartao className="mb-6 p-6">
          <h2 className="mb-1 font-semibold text-slate-900">Novo itinerário diário</h2>
          <p className="mb-4 text-sm text-slate-500">Informe todas as conduções de ida e volta. O pedido vai para aprovação da chefia; o auxílio atual continua valendo até lá.</p>
          {solicitar.isError && <div className="mb-4"><Alerta>{mensagemErro(solicitar.error)}</Alerta></div>}

          <div className="space-y-3">
            {itens.map((item) => (
              <div key={item.chave} className="flex flex-wrap items-center gap-3">
                <select aria-label="Sentido" className={cx(classeCampo, 'w-28')} value={item.sentido} onChange={(e) => atualizar(item.chave, { sentido: e.target.value as Sentido })}>
                  <option value="IDA">Ida</option>
                  <option value="VOLTA">Volta</option>
                </select>
                <select aria-label="Linha" className={cx(classeCampo, 'min-w-64 flex-1')} value={item.linhaId} onChange={(e) => atualizar(item.chave, { linhaId: e.target.value ? Number(e.target.value) : '' })}>
                  <option value="">Selecione a linha…</option>
                  {linhas.data?.map((l) => (
                    <option key={l.id} value={l.id}>
                      {l.codigo} — {l.nome} ({formatarMoeda(l.tarifa)})
                    </option>
                  ))}
                </select>
                <Botao variante="fantasma" type="button" onClick={() => setItens((l) => l.filter((i) => i.chave !== item.chave))} disabled={itens.length <= 1} aria-label="Remover condução">
                  ✕
                </Botao>
              </div>
            ))}
          </div>

          <div className="mt-4 flex flex-wrap items-center justify-between gap-4 border-t border-slate-100 pt-4">
            <div className="flex gap-2">
              <Botao variante="secundario" type="button" onClick={() => setItens((l) => [...l, novaLinha('IDA')])} disabled={itens.length >= 6}>+ Condução</Botao>
            </div>
            <div className="flex items-center gap-4">
              <span className="text-sm text-slate-600">Valor diário: <strong className="tabular-nums text-slate-900">{formatarMoeda(totalDiario)}</strong></span>
              <Botao variante="secundario" type="button" onClick={() => setEditando(false)}>Cancelar</Botao>
              <Botao onClick={() => solicitar.mutate()} carregando={solicitar.isPending} disabled={itens.some((i) => i.linhaId === '')}>Enviar pedido</Botao>
            </div>
          </div>
        </Cartao>
      )}

      <div className="grid gap-6 lg:grid-cols-2">
        <div className="space-y-6">
          {vigente ? (
            <Cartao className="p-6">
              <div className="mb-4 flex items-center justify-between">
                <h2 className="font-semibold text-slate-900">Auxílio vigente</h2>
                <BadgeStatus status={vigente.status} />
              </div>
              <Itinerario solicitacao={vigente} />
              <p className="mt-3 text-xs text-slate-500">Aprovado por {vigente.avaliadoPor} em {formatarDataHora(vigente.avaliadoEm!)}</p>
            </Cartao>
          ) : (
            !pendente && <Alerta tipo="aviso">Você não possui auxílio-transporte. Clique em “Solicitar auxílio” para cadastrar seu itinerário.</Alerta>
          )}

          {pendente && (
            <Cartao className="border-amber-200 p-6">
              <div className="mb-4 flex items-center justify-between">
                <h2 className="font-semibold text-slate-900">Aguardando aprovação</h2>
                <BadgeStatus status={pendente.status} />
              </div>
              <Itinerario solicitacao={pendente} />
              <p className="mt-3 text-xs text-slate-500">Enviado em {formatarDataHora(pendente.criadoEm)}</p>
            </Cartao>
          )}
        </div>

        {vigente && (
          <Cartao className="p-6">
            <div className="mb-4 flex flex-wrap items-center justify-between gap-3">
              <h2 className="font-semibold text-slate-900">Demonstrativo</h2>
              <SeletorCompetencia valor={competencia} onChange={setCompetencia} maximo={competenciaAtual()} />
            </div>
            {demonstrativo.isLoading && <Carregando />}
            {d && (
              <>
                <dl className="space-y-2 text-sm">
                  <div className="flex justify-between"><dt className="text-slate-600">Valor diário</dt><dd className="tabular-nums">{formatarMoeda(d.valorDiario)}</dd></div>
                  <div className="flex justify-between"><dt className="text-slate-600">Dias trabalhados (ponto)</dt><dd className="tabular-nums">{d.diasTrabalhados} de {d.diasUteis}</dd></div>
                  <div className="flex justify-between border-t border-slate-100 pt-2"><dt className="font-medium">Valor bruto</dt><dd className="font-medium tabular-nums">{formatarMoeda(d.valorBruto)}</dd></div>
                  <div className="flex justify-between text-red-600">
                    <dt>Cota do servidor ({d.percentualDesconto}% de {formatarMoeda(d.salarioBase)}, proporcional)</dt>
                    <dd className="tabular-nums">−{formatarMoeda(d.valorDesconto)}</dd>
                  </div>
                </dl>
                <div className="mt-4 flex items-center justify-between rounded-lg bg-marca-900 px-4 py-3 text-white">
                  <span className="text-sm font-medium">Valor a receber</span>
                  <span className="text-xl font-semibold tabular-nums">{formatarMoeda(d.valorLiquido)}</span>
                </div>
                <Botao variante="secundario" className="mt-4 w-full" onClick={() => abrirPdf('/auxilio/demonstrativo/pdf', { competencia })}>
                  Baixar demonstrativo (PDF)
                </Botao>
              </>
            )}
          </Cartao>
        )}
      </div>
    </>
  )
}
