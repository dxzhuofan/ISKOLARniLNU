import { Navigate } from 'react-router-dom';
import { homeFor, useAuth } from '../context/AuthContext';

/** UI-level guard only. The backend must also authorize every protected API request. */
export default function ProtectedRoute({ role, children }) {
  const { user, ready } = useAuth();
  if (!ready) return <div className="grid min-h-dvh place-items-center text-slate-500">Loading…</div>;
  if (!user) return <Navigate to="/login" replace />;
  if (user.role !== role) return <Navigate to={homeFor(user.role)} replace />;
  return children;
}
