import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useState, type FormEvent } from 'react'
import { api, mensagemErro } from '../api/client'
import type { Justificativa, TipoJustificativa } from '../api/types'
import { Alerta, BadgeStatus, Botao, CabecalhoPagina, Cartao, Carregando, Vazio } from '../components/ui'
import { classeCampo, classeRotulo } from '../components/estilos'
import { formatarData, formatarDataHora, hojeIso } from '../utils/format'

const TIPOS: { valor: TipoJustificativa; rotulo: string }[] = [
  { valor: 'ATESTADO_MEDICO', rotulo: 'Atestado médico' },
  { valor: 'FALTA_JUSTIFICADA', rotulo: 'Falta justificada' },
  { valor: 'SERVICO_EXTERNO', rotulo: 'Serviço externo' },
  { valor: 'ESQUECIMENTO_BATIDA', rotulo: 'Esquecimento de batida' },
]

export function Justificativas() {
  const queryClient = useQueryClient()
  const [data, setData] = useState('')
  const [tipo, setTipo] = useState<TipoJustificativa>('FALTA_JUSTIFICADA')
  const [motivo, setMotivo] = useState('')

  const [pagina, setPagina] = useState(1)

  // A API devolve a lista no corpo e os totais nos cabeçalhos X-Total / X-Paginas
  const lista = useQuery({
    queryKey: ['justificativas', pagina],
    placeholderData: keepPreviousData,
    queryFn: async () => {
      const resp = await api.get<Justificativa[]>('/justificativas', { params: { pagina } })
      return {
        itens: resp.data,
        total: Number(resp.headers['x-total'] ?? resp.data.length),
        paginas: Number(resp.headers['x-paginas'] ?? 1),
      }
    },
  })

  const criar = useMutation({
    mutationFn: async () => (await api.post<Justificativa>('/justificativas', { data, tipo, motivo })).data,
    onSuccess: () => {
      setData('')
      setMotivo('')
      setPagina(1) // o pedido novo aparece no topo da primeira página
      queryClient.invalidateQueries({ queryKey: ['justificativas'] })
    },
  })

  function enviar(e: FormEvent) {
    e.preventDefault()
    criar.mutate()
  }

  return (
    <>
      <CabecalhoPagina titulo="Justificativas" subtitulo="Solicite o abono de faltas ou batidas não registradas. Sua chefia imediata avalia o pedido." />

      <div className="grid gap-6 lg:grid-cols-3">
        <Cartao className="h-fit p-6">
          <h2 className="mb-4 font-semibold text-slate-900">Nova justificativa</h2>
          <form onSubmit={enviar} className="space-y-4">
            {criar.isError && <Alerta>{mensagemErro(criar.error)}</Alerta>}
            {criar.isSuccess && <Alerta tipo="sucesso">Justificativa enviada para avaliação.</Alerta>}
            <div>
              <label htmlFor="data" className={classeRotulo}>Dia</label>
              <input id="data" type="date" required max={hojeIso()} className={classeCampo} value={data} onChange={(e) => setData(e.target.value)} />
            </div>
            <div>
              <label htmlFor="tipo" className={classeRotulo}>Tipo</label>
              <select id="tipo" className={classeCampo} value={tipo} onChange={(e) => setTipo(e.target.value as TipoJustificativa)}>
                {TIPOS.map((t) => (
                  <option key={t.valor} value={t.valor}>{t.rotulo}</option>
                ))}
              </select>
            </div>
            <div>
              <label htmlFor="motivo" className={classeRotulo}>Motivo</label>
              <textarea id="motivo" required minLength={10} rows={4} className={classeCampo} value={motivo} onChange={(e) => setMotivo(e.target.value)} placeholder="Descreva o que aconteceu" />
            </div>
            <Botao type="submit" carregando={criar.isPending} className="w-full">Enviar</Botao>
            <p className="text-xs text-slate-500">Prazo: até 30 dias após a data. Meses fechados pelo RH não aceitam novos pedidos.</p>
          </form>
        </Cartao>

        <Cartao className="overflow-hidden lg:col-span-2">
          <h2 className="border-b border-slate-100 px-5 py-4 font-semibold text-slate-900">Meus pedidos</h2>
          {lista.isLoading && <Carregando />}
          {lista.data?.total === 0 && <Vazio>Você ainda não enviou justificativas.</Vazio>}
          <ul className="divide-y divide-slate-100">
            {lista.data?.itens.map((j) => (
              <li key={j.id} className="px-5 py-4">
                <div className="flex flex-wrap items-center justify-between gap-2">
                  <p className="font-medium text-slate-900">
                    {formatarData(j.data)} · <span className="font-normal text-slate-600">{j.tipoLabel}</span>
                  </p>
                  <BadgeStatus status={j.status} />
                </div>
                <p className="mt-1 text-sm text-slate-600">{j.motivo}</p>
                {j.avaliadoPor && (
                  <p className="mt-2 text-xs text-slate-500">
                    Avaliada por {j.avaliadoPor} em {formatarDataHora(j.avaliadoEm!)}
                    {j.observacaoAvaliacao && <> — “{j.observacaoAvaliacao}”</>}
                  </p>
                )}
              </li>
            ))}
          </ul>
          {lista.data && lista.data.paginas > 1 && (
            <nav className="flex items-center justify-between border-t border-slate-100 px-5 py-3 text-sm" aria-label="Paginação">
              <Botao variante="secundario" onClick={() => setPagina((p) => p - 1)} disabled={pagina <= 1}>← Mais recentes</Botao>
              <span className="tabular-nums text-slate-500">
                Página {pagina} de {lista.data.paginas} · {lista.data.total} pedidos
              </span>
              <Botao variante="secundario" onClick={() => setPagina((p) => p + 1)} disabled={pagina >= lista.data.paginas}>Mais antigos →</Botao>
            </nav>
          )}
        </Cartao>
      </div>
    </>
  )
}
