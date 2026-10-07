import { useQueryClient } from '@tanstack/react-query'
import { useCallback, useEffect, useMemo, useRef, useState } from 'react'
import { useLocation } from 'react-router-dom'
import * as authApi from '../api/auth'
import { setUnauthorizedHandler, tokenStorage } from '../api/client'
import { warmStart } from '../pages/warmStart'
import { AuthContext } from './useAuth'

export function AuthProvider({ children }) {
  const queryClient = useQueryClient()
  const [user, setUser] = useState(null)
  // With a stored token we must ask /me before deciding where the user belongs.
  const [loading, setLoading] = useState(() => Boolean(tokenStorage.get()))
  // The page the app was opened on, for warmStart().
  const firstPath = useRef(useLocation().pathname)

  useEffect(() => {
    // An expired or revoked token: forget the user; route guards send them to /login.
    setUnauthorizedHandler(() => {
      setUser(null)
      queryClient.clear()
    })

    if (!tokenStorage.get()) return
    warmStart(firstPath.current, queryClient)
    let cancelled = false
    authApi
      .me()
      .then((me) => !cancelled && setUser(me))
      // A rejected token is already cleared by the client's 401 handling; on a
      // network error we keep it so a reload can try again.
      .catch(() => !cancelled && setUser(null))
      .finally(() => !cancelled && setLoading(false))
    return () => {
      cancelled = true
    }
  }, [queryClient])

  const startSession = useCallback(({ token, user }) => {
    tokenStorage.set(token)
    setUser(user)
    return user
  }, [])

  const login = useCallback((credentials) => authApi.login(credentials).then(startSession), [startSession])

  const register = useCallback((fields) => authApi.register(fields).then(startSession), [startSession])

  const logout = useCallback(async () => {
    try {
      await authApi.logout()
    } catch {
      // The token may already be invalid; log out locally anyway.
    } finally {
      tokenStorage.clear()
      setUser(null)
      // Drop cached data so the next person on this browser never sees it.
      queryClient.clear()
    }
  }, [queryClient])

  const value = useMemo(
    () => ({ user, role: user?.role ?? null, loading, login, register, logout }),
    [user, loading, login, register, logout],
  )

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>
}
