import { createContext, useContext, useEffect, useState, useCallback } from 'react';
import { api } from './api';

const Ctx = createContext(null);
export const useAuth = () => useContext(Ctx);

export function AuthProvider({ children }) {
  const [token, setToken] = useState(() => localStorage.getItem('token'));
  const [user, setUser] = useState(null);
  const [ready, setReady] = useState(!localStorage.getItem('token'));

  useEffect(() => {
    if (!token) { setUser(null); setReady(true); return; }
    api('/me', { token })
      .then(setUser)
      .catch(() => { localStorage.removeItem('token'); setToken(null); })
      .finally(() => setReady(true));
  }, [token]);

  const login = useCallback((t, u) => { localStorage.setItem('token', t); setUser(u); setToken(t); }, []);
  const logout = useCallback(async () => {
    try { await api('/auth/logout', { method: 'POST', token }); } catch { /* ignore */ }
    localStorage.removeItem('token'); setToken(null); setUser(null);
  }, [token]);

  return <Ctx.Provider value={{ token, user, ready, login, logout }}>{children}</Ctx.Provider>;
}
