import axios, { type AxiosError } from 'axios'
import type { ErroApi } from './types'

export const TOKEN_KEY = 'rhfolha.token'

export function lerToken(): string | null {
  try {
    return localStorage.getItem(TOKEN_KEY)
  } catch {
    return null
  }
}

export function salvarToken(token: string | null): void {
  try {
    if (token) localStorage.setItem(TOKEN_KEY, token)
    else localStorage.removeItem(TOKEN_KEY)
  } catch {
    /* storage indisponível: sessão fica só em memória */
  }
}

export const api = axios.create({ baseURL: '/api' })

api.interceptors.request.use((config) => {
  const token = lerToken()
  if (token) config.headers.Authorization = `Bearer ${token}`
  return config
})

api.interceptors.response.use(
  (r) => r,
  (error: AxiosError) => {
    if (error.response?.status === 401 && !error.config?.url?.endsWith('/login')) {
      salvarToken(null)
      window.dispatchEvent(new Event('rhfolha:logout'))
    }
    return Promise.reject(error)
  },
)

/** Extrai a mensagem de erro padronizada pela API ({ erro, detalhes }). */
export function mensagemErro(error: unknown): string {
  if (axios.isAxiosError<ErroApi>(error)) {
    const dados = error.response?.data
    if (dados?.detalhes) return Object.values(dados.detalhes).join(' ')
    if (dados?.erro) return dados.erro
    if (error.response?.status === 403) return 'Você não tem permissão para esta ação.'
  }
  return 'Não foi possível completar a operação. Tente novamente.'
}

/**
 * Baixa um PDF autenticado (gerado pelo Twig no backend) e abre em nova aba.
 * A aba é aberta ANTES do await: navegadores só liberam pop-ups disparados
 * diretamente pelo clique; depois do download apenas trocamos o endereço dela.
 */
export async function abrirPdf(url: string, params: Record<string, string>): Promise<void> {
  const aba = window.open('', '_blank')
  try {
    const resp = await api.get<Blob>(url, { params, responseType: 'blob' })
    const href = URL.createObjectURL(resp.data)
    if (aba) aba.location.href = href
    else window.location.href = href
    setTimeout(() => URL.revokeObjectURL(href), 60_000)
  } catch (erro) {
    aba?.close()
    throw erro
  }
}

/** Endereço do painel Twig do RH (configurável por VITE_ADMIN_URL). */
export const URL_PAINEL_RH = import.meta.env.VITE_ADMIN_URL ?? 'http://localhost:8081/admin'
