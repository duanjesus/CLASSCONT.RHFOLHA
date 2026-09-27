import { createContext } from 'react'
import type { Papel, Usuario } from '../api/types'

export interface AuthState {
  usuario: Usuario | null
  carregando: boolean
  entrar: (email: string, senha: string) => Promise<void>
  sair: () => void
  temPapel: (papel: Papel) => boolean
}

export const AuthContext = createContext<AuthState | null>(null)
