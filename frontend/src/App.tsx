import type { ReactNode } from 'react'
import { Navigate, Route, Routes } from 'react-router-dom'
import type { Papel } from './api/types'
import { useAuth } from './auth/useAuth'
import { Layout } from './components/Layout'
import { Carregando } from './components/ui'
import { Aprovacoes } from './pages/Aprovacoes'
import { AuxilioTransporte } from './pages/AuxilioTransporte'
import { Equipe } from './pages/Equipe'
import { EspelhoPonto } from './pages/EspelhoPonto'
import { Inicio } from './pages/Inicio'
import { Justificativas } from './pages/Justificativas'
import { Login } from './pages/Login'

/** Rota que exige login (e opcionalmente um dos papéis informados). */
function Protegida({ children, papeis }: { children: ReactNode; papeis?: Papel[] }) {
  const { usuario, carregando, temPapel } = useAuth()
  if (carregando) return <Carregando texto="Validando sessão…" />
  if (!usuario) return <Navigate to="/login" replace />
  if (papeis && !papeis.some(temPapel)) return <Navigate to="/" replace />
  return children
}

export default function App() {
  return (
    <Routes>
      <Route path="/login" element={<Login />} />
      <Route
        element={
          <Protegida>
            <Layout />
          </Protegida>
        }
      >
        <Route index element={<Inicio />} />
        <Route path="espelho" element={<EspelhoPonto />} />
        <Route path="justificativas" element={<Justificativas />} />
        <Route path="auxilio" element={<AuxilioTransporte />} />
        <Route path="aprovacoes" element={<Protegida papeis={['ROLE_CHEFIA', 'ROLE_RH']}><Aprovacoes /></Protegida>} />
        <Route path="equipe" element={<Protegida papeis={['ROLE_CHEFIA']}><Equipe /></Protegida>} />
      </Route>
      <Route path="*" element={<Navigate to="/" replace />} />
    </Routes>
  )
}
