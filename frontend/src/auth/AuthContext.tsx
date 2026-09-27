import { useQuery, useQueryClient } from '@tanstack/react-query'
import { useCallback, useEffect, useMemo, useState, type ReactNode } from 'react'
import { api, lerToken, salvarToken } from '../api/client'
import type { Usuario } from '../api/types'
import { AuthContext, type AuthState } from './contexto'

export function AuthProvider({ children }: { children: ReactNode }) {
  const queryClient = useQueryClient()
  const [token, setToken] = useState<string | null>(lerToken)

  const { data: usuario, isLoading } = useQuery({
    queryKey: ['me', token],
    queryFn: async () => (await api.get<Usuario>('/me')).data,
    enabled: !!token,
    retry: false,
  })

  const sair = useCallback(() => {
    salvarToken(null)
    setToken(null)
    queryClient.clear()
  }, [queryClient])

  useEffect(() => {
    window.addEventListener('rhfolha:logout', sair)
    return () => window.removeEventListener('rhfolha:logout', sair)
  }, [sair])

  const entrar = useCallback(async (email: string, senha: string) => {
    const { data } = await api.post<{ token: string }>('/login', { email, password: senha })
    salvarToken(data.token)
    setToken(data.token)
  }, [])

  const valor = useMemo<AuthState>(
    () => ({
      usuario: token ? (usuario ?? null) : null,
      carregando: !!token && isLoading,
      entrar,
      sair,
      temPapel: (papel) => !!usuario?.roles.includes(papel),
    }),
    [token, usuario, isLoading, entrar, sair],
  )

  return <AuthContext.Provider value={valor}>{children}</AuthContext.Provider>
}
