import { Navigate, useLocation } from 'react-router-dom';
import { useAuth } from '../context/AuthContext';
import Unauthorized from '../pages/Unauthorized';

/** UI-level guard only. The backend independently enforces roles on every API route. */
export default function ProtectedRoute({ role, children }) {
  const { user, ready } = useAuth();
  const location = useLocation();
  if (!ready) return <div className="grid min-h-dvh place-items-center text-slate-500">Loading…</div>;
  if (!user) return <Navigate to="/login" replace state={{ from: location.pathname }} />;
  if (user.role !== role) return <Unauthorized />;
  return children;
}
