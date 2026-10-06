import { createContext, useCallback, useContext, useEffect, useState } from 'react';
import { AuthError, DEFAULT_IDLE_SECONDS, getSession, isDemoMode, loginUser, logoutUser } from '../services/authService';

const AuthContext = createContext(null);
export const useAuth = () => useContext(AuthContext);
export const homeFor = (role) => (role === 'administrator' ? '/admin' : '/student');

export function AuthProvider({ children }) {
  const [user, setUser] = useState(null);
  const [idleSeconds, setIdleSeconds] = useState(DEFAULT_IDLE_SECONDS);
  const [ready, setReady] = useState(false);
  const [notice, setNotice] = useState(null); // 'idle' | 'expired' | null, shown on the login page

  const apply = (s) => {
    setUser(s.user);
    setIdleSeconds(s.idleTimeoutSeconds || DEFAULT_IDLE_SECONDS);
  };

  useEffect(() => {
    let live = true;
    getSession()
      .then((s) => live && s && apply(s))
      .catch(() => {})
      .finally(() => live && setReady(true));
    return () => { live = false; };
  }, []);

  const login = async (credentials) => {
    const s = await loginUser(credentials);
    // Safety net only: the server already enforces this.
    if (credentials.role && s.user.role !== credentials.role) {
      await logoutUser().catch(() => {});
      throw new AuthError(`This account is not ${credentials.role === 'administrator' ? 'an administrator' : 'a student'} account. Use the other tab to sign in.`, 403);
    }
    apply(s);
    setNotice(null);
    return s.user;
  };

  const logout = async (reason = null) => {
    try { await logoutUser(); } catch { /* the local session is cleared either way */ }
    setUser(null);
    setNotice(reason);
  };

  /** Pings the server (renews the idle timeout). Returns false when the session is gone. */
  const refreshSession = useCallback(async () => {
    if (isDemoMode) return true;
    try {
      const s = await getSession();
      if (s) { apply(s); return true; }
      setUser(null);
      setNotice('expired');
      return false;
    } catch {
      return true; // network hiccup: keep the user signed in
    }
  }, []);

  return (
    <AuthContext.Provider value={{ user, ready, idleSeconds, notice, login, logout, refreshSession }}>
      {children}
    </AuthContext.Provider>
  );
}
