import { createContext, useContext, useEffect, useState } from 'react';
import { AuthError, getSession, loginUser, logoutUser } from '../services/authService';

const AuthContext = createContext(null);
export const useAuth = () => useContext(AuthContext);
export const homeFor = (role) => (role === 'administrator' ? '/admin' : '/student');

export function AuthProvider({ children }) {
  const [user, setUser] = useState(null);
  const [ready, setReady] = useState(false);

  useEffect(() => {
    let live = true;
    getSession().then((u) => live && setUser(u)).finally(() => live && setReady(true));
    return () => { live = false; };
  }, []);

  const login = async (credentials) => {
    const { user } = await loginUser(credentials);
    // Defensive check: the backend must enforce this too.
    if (credentials.role && user.role !== credentials.role) {
      await logoutUser().catch(() => {});
      throw new AuthError(`This account is not ${credentials.role === 'administrator' ? 'an administrator' : 'a student'} account. Use the other tab to sign in.`, 403);
    }
    setUser(user);
    return user;
  };
  const logout = async () => {
    try { await logoutUser(); } finally { setUser(null); }
  };

  return <AuthContext.Provider value={{ user, ready, login, logout }}>{children}</AuthContext.Provider>;
}
