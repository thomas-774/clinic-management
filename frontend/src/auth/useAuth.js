import { createContext, useContext } from 'react'

export const AuthContext = createContext(null)

/** Where each role lands after login (§7.2). */
export function homePathFor(role) {
  return role === 'doctor' ? '/doctor' : '/patient'
}

/** `{ user, role, loading, login, register, logout }` from <AuthProvider>. */
export function useAuth() {
  const context = useContext(AuthContext)
  if (!context) throw new Error('useAuth must be used inside <AuthProvider>')
  return context
}
