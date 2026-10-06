import { Link, useNavigate } from 'react-router-dom';
import { homeFor, useAuth } from '../context/AuthContext';

export default function Unauthorized() {
  const { user, logout } = useAuth();
  const navigate = useNavigate();
  const roleLabel = user?.role === 'administrator' ? 'Administrator' : 'Student';
  return (
    <div className="grid min-h-dvh place-items-center bg-navy-800 p-6">
      <section role="alert" className="w-full max-w-lg rounded-3xl bg-white p-8 text-center shadow-2xl sm:p-10">
        <p className="font-serif text-6xl font-bold text-gold-500">403</p>
        <h1 className="mt-3 font-serif text-2xl font-bold text-navy-950">Access denied</h1>
        <p className="mt-3 text-slate-600">
          Your {roleLabel} account doesn’t have permission to open this page. If you think this is a mistake, contact{' '}
          <a href="mailto:scholarship@lnu.edu.ph" className="link">scholarship@lnu.edu.ph</a>.
        </p>
        <div className="mt-7 grid gap-3 sm:grid-cols-2">
          {user && <Link to={homeFor(user.role)} className="btn-primary">Go to my dashboard</Link>}
          <button onClick={async () => { await logout(); navigate('/login', { replace: true }); }} className="inline-flex min-h-12 items-center justify-center rounded-lg border border-slate-300 px-5 font-semibold text-navy-800 transition hover:bg-slate-50">
            Sign out
          </button>
        </div>
      </section>
    </div>
  );
}
