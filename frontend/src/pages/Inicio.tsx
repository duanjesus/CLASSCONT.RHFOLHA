import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import { api, mensagemErro } from '../api/client'
import type { Espelho, PontoHoje, TipoBatida } from '../api/types'
import { useAuth } from '../auth/useAuth'
import { Alerta, Botao, Cartao, Carregando, Indicador } from '../components/ui'
import { cx } from '../components/estilos'
import { competenciaAtual, formatarMinutos, nomeCompetencia } from '../utils/format'

const ROTULOS_BATIDA: Record<TipoBatida, string> = {
  ENTRADA: 'Entrada',
  SAIDA_ALMOCO: 'Saída para intervalo',
  RETORNO_ALMOCO: 'Retorno do intervalo',
  SAIDA: 'Saída',
}
const ORDEM: TipoBatida[] = ['ENTRADA', 'SAIDA_ALMOCO', 'RETORNO_ALMOCO', 'SAIDA']

function useRelogio() {
  const [agora, setAgora] = useState(() => new Date())
  useEffect(() => {
    const id = setInterval(() => setAgora(new Date()), 1000)
    return () => clearInterval(id)
  }, [])
  return agora
}

export function Inicio() {
  const { usuario } = useAuth()
  const agora = useRelogio()
  const queryClient = useQueryClient()
  const competencia = competenciaAtual()

  const hoje = useQuery({ queryKey: ['ponto-hoje'], queryFn: async () => (await api.get<PontoHoje>('/ponto/hoje')).data })
  const espelho = useQuery({
    queryKey: ['espelho', competencia, null],
    queryFn: async () => (await api.get<Espelho>('/ponto/espelho', { params: { competencia } })).data,
  })

  const bater = useMutation({
    mutationFn: async () => (await api.post<PontoHoje>('/ponto/bater')).data,
    onSuccess: (dados) => {
      queryClient.setQueryData(['ponto-hoje'], dados)
      queryClient.invalidateQueries({ queryKey: ['espelho'] })
    },
  })

  const proxima = hoje.data?.proximaBatida ?? null
  const totais = espelho.data?.totais
  const pendenciasDias = espelho.data?.dias.filter((d) => d.situacao === 'FALTA' || d.situacao === 'INCOMPLETO') ?? []

  return (
    <>
      <div className="mb-6">
        <h1 className="text-2xl font-semibold text-slate-900">Olá, {usuario?.nome.split(' ')[0]}!</h1>
        <p className="mt-1 text-sm first-letter:uppercase text-slate-500">
          {agora.toLocaleDateString('pt-BR', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' })}
        </p>
      </div>

      <div className="grid gap-6 lg:grid-cols-5">
        {/* Registro de ponto */}
        <Cartao className="p-6 lg:col-span-3">
          <div className="flex flex-wrap items-center justify-between gap-6">
            <div>
              <p className="text-sm font-medium text-slate-500">Horário oficial</p>
              <p className="mt-1 text-5xl font-semibold tabular-nums tracking-tight text-marca-900" aria-live="off">
                {agora.toLocaleTimeString('pt-BR')}
              </p>
            </div>
            <div className="text-right">
              {proxima ? (
                <Botao className="px-6 py-3 text-base" carregando={bater.isPending} onClick={() => bater.mutate()} disabled={hoje.data?.competenciaFechada}>
                  Registrar {ROTULOS_BATIDA[proxima].toLowerCase()}
                </Botao>
              ) : (
                hoje.data && <p className="rounded-lg bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-700">Jornada de hoje concluída ✓</p>
              )}
            </div>
          </div>

          {bater.isError && <div className="mt-4"><Alerta>{mensagemErro(bater.error)}</Alerta></div>}
          {bater.isSuccess && <div className="mt-4"><Alerta tipo="sucesso">Batida registrada às {bater.data.batidas.at(-1)}.</Alerta></div>}

          <ol className="mt-6 grid grid-cols-2 gap-3 sm:grid-cols-4">
            {ORDEM.map((tipo, i) => {
              const horario = hoje.data?.batidas[i]
              const eProxima = tipo === proxima
              return (
                <li
                  key={tipo}
                  className={cx(
                    'rounded-lg border px-3 py-2',
                    horario ? 'border-emerald-200 bg-emerald-50' : eProxima ? 'border-marca-500 border-dashed bg-marca-50' : 'border-slate-200',
                  )}
                >
                  <p className="text-xs text-slate-500">{ROTULOS_BATIDA[tipo]}</p>
                  <p className={cx('text-lg font-semibold tabular-nums', horario ? 'text-emerald-800' : 'text-slate-300')}>{horario ?? '--:--'}</p>
                </li>
              )
            })}
          </ol>
        </Cartao>

        {/* Resumo do mês */}
        <div className="space-y-4 lg:col-span-2">
          {espelho.isLoading ? (
            <Carregando />
          ) : (
            totais && (
              <>
                <p className="text-sm font-medium first-letter:uppercase text-slate-500">Resumo de {nomeCompetencia(competencia)}</p>
                <div className="grid grid-cols-2 gap-4">
                  <Indicador
                    rotulo="Banco de horas"
                    valor={formatarMinutos(totais.saldoMinutos, true)}
                    destaque={totais.saldoMinutos > 0 ? 'positivo' : totais.saldoMinutos < 0 ? 'negativo' : undefined}
                  />
                  <Indicador rotulo="Faltas" valor={totais.faltas} destaque={totais.faltas > 0 ? 'negativo' : undefined} />
                  <Indicador rotulo="Trabalhado" valor={formatarMinutos(totais.trabalhadoMinutos)} detalhe={`de ${formatarMinutos(totais.esperadoMinutos)} previstas`} />
                  <Indicador rotulo="Dias trabalhados" valor={totais.diasTrabalhados} detalhe={`${totais.diasUteis} dias úteis no mês`} />
                </div>
              </>
            )
          )}

          {pendenciasDias.length > 0 && (
            <Alerta tipo="aviso">
              <p className="font-medium">Você tem {pendenciasDias.length} dia(s) com pendência no ponto.</p>
              <p className="mt-1">
                <Link to="/justificativas" className="font-medium underline">Enviar justificativa</Link> ou{' '}
                <Link to="/espelho" className="font-medium underline">ver espelho</Link>.
              </p>
            </Alerta>
          )}
        </div>
      </div>
    </>
  )
}
