import { useNavigate } from 'react-router-dom';
import { useAuth } from '../context/AuthContext';

function Shell({ title, children }) {
  const { user, logout } = useAuth();
  const navigate = useNavigate();
  const signOut = async () => { await logout(); navigate('/login', { replace: true }); };
  return (
    <div className="min-h-dvh bg-slate-50">
      <header className="flex items-center justify-between bg-navy-800 px-6 py-4 text-white">
        <span className="font-serif text-xl font-bold">LNU Scholarship Portal</span>
        <button onClick={signOut} className="btn-outline">Sign Out</button>
      </header>
      <main className="mx-auto max-w-4xl p-6">
        <h1 className="font-serif text-3xl font-bold text-navy-950">{title}</h1>
        <p className="mt-2 text-slate-600">Signed in as {user.email}. This is a placeholder; replace it with the real dashboard module.</p>
        {children}
      </main>
    </div>
  );
}

export const StudentDashboard = () => <Shell title="Student Dashboard" />;
export const AdminDashboard = () => <Shell title="Administrator Dashboard" />;
