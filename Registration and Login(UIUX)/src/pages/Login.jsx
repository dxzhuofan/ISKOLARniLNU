import { useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import AuthCard from '../components/AuthCard';
import InputField from '../components/InputField';
import PasswordInput from '../components/PasswordInput';
import LoadingButton from '../components/LoadingButton';
import AlertMessage from '../components/AlertMessage';
import SuccessMark from '../components/SuccessMark';
import useForm from '../hooks/useForm';
import { useAuth, homeFor } from '../context/AuthContext';
import { isDemoMode } from '../services/authService';
import { clean, validateEmail, validateLoginPassword } from '../utils/validators';

const ROLES = [
  { id: 'student', label: 'Student', title: 'Student Sign In', subtitle: 'Access your LNU Scholarship Portal account.' },
  { id: 'administrator', label: 'Administrator', title: 'Administrator Sign In', subtitle: 'Manage scholarships, applications and grantees.' },
];

/** Student | Administrator switch with a sliding indicator and arrow-key support. */
function RoleTabs({ role, onChange, disabled }) {
  const index = ROLES.findIndex((r) => r.id === role);
  const onKeyDown = (e) => {
    if (e.key !== 'ArrowLeft' && e.key !== 'ArrowRight') return;
    e.preventDefault();
    const next = ROLES[(index + (e.key === 'ArrowRight' ? 1 : -1) + ROLES.length) % ROLES.length];
    onChange(next.id);
    requestAnimationFrame(() => document.getElementById(`tab-${next.id}`)?.focus());
  };
  return (
    <div role="tablist" aria-label="Sign in as" onKeyDown={onKeyDown} className="relative grid grid-cols-2 rounded-xl bg-slate-100 p-1">
      <span aria-hidden="true" className="absolute inset-y-1 left-1 w-[calc(50%-0.25rem)] rounded-lg bg-white shadow transition-transform duration-300 ease-out" style={{ transform: `translateX(${index * 100}%)` }} />
      {ROLES.map((r) => (
        <button
          key={r.id}
          id={`tab-${r.id}`}
          type="button"
          role="tab"
          aria-selected={role === r.id}
          aria-controls="login-panel"
          tabIndex={role === r.id ? 0 : -1}
          disabled={disabled}
          onClick={() => onChange(r.id)}
          className={`relative z-10 min-h-11 rounded-lg px-3 text-sm font-semibold transition-colors ${role === r.id ? 'text-navy-900' : 'text-slate-500 hover:text-navy-800'}`}
        >
          {r.label}
        </button>
      ))}
    </div>
  );
}

export default function Login() {
  const { login, notice } = useAuth();
  const navigate = useNavigate();
  const [role, setRole] = useState('student');
  const [status, setStatus] = useState('idle'); // idle | loading | success
  const [formError, setFormError] = useState('');
  const [shake, setShake] = useState(false);
  const current = ROLES.find((r) => r.id === role);
  const isAdmin = role === 'administrator';

  const form = useForm({ email: '', password: '', remember: false }, (v) =>
    clean({ email: validateEmail(v.email), password: validateLoginPassword(v.password) }),
  );

  const onSubmit = form.handleSubmit(async ({ email, password, remember }) => {
    if (status === 'loading') return;
    setStatus('loading');
    setFormError('');
    try {
      const user = await login({ email: email.trim(), password, remember, role });
      setStatus('success');
      setTimeout(() => navigate(homeFor(user.role), { replace: true }), 1100);
    } catch (err) {
      setFormError(err.message);
      setStatus('idle');
      setShake(true);
    }
  });

  return (
    <AuthCard
      top={status === 'success' ? null : <RoleTabs role={role} onChange={(r) => { setRole(r); setFormError(''); }} disabled={status === 'loading'} />}
      title={current.title}
      subtitle={current.subtitle}
      shake={shake}
      onShakeEnd={() => setShake(false)}
      footer={
        <>
          {isAdmin ? (
            <p>Administrator accounts are created by the system administrator.</p>
          ) : (
            <p>No account? <Link to="/register" className="link">Register here</Link></p>
          )}
          <p className="mt-1 text-sm">For assistance: <a href="mailto:scholarship@lnu.edu.ph" className="link">scholarship@lnu.edu.ph</a></p>
        </>
      }
    >
      {status === 'success' ? (
        <SuccessMark title="Signed in">Taking you to your dashboard…</SuccessMark>
      ) : (
        <form id="login-panel" role="tabpanel" aria-labelledby={`tab-${role}`} onSubmit={onSubmit} noValidate className="space-y-5">
          {notice && !formError && (
            <AlertMessage variant="info">
              {notice === 'idle' ? 'You were signed out because of inactivity. Please sign in again.' : 'Your session has expired. Please sign in again.'}
            </AlertMessage>
          )}
          {formError && <AlertMessage variant="error">{formError}</AlertMessage>}
          <InputField label="LNU Email Address" type="email" autoComplete="username" placeholder="yourname@lnu.edu.ph" {...form.field('email')} />
          <PasswordInput
            placeholder="••••••••"
            labelRight={<Link to="/forgot-password" className="link text-sm">Forgot password?</Link>}
            {...form.field('password')}
          />
          <label className="flex min-h-11 w-fit cursor-pointer items-center gap-3 text-slate-700">
            <input type="checkbox" name="remember" checked={form.values.remember} onChange={form.field('remember').onChange} className="size-5 cursor-pointer rounded border-slate-300 accent-navy-800" />
            Keep me signed in
          </label>
          <LoadingButton loading={status === 'loading'} loadingText="Signing in...">{isAdmin ? 'Sign In as Administrator' : 'Sign In to Portal'}</LoadingButton>
          {isDemoMode && (
            <AlertMessage variant="info">
              <strong>Demo mode:</strong> Choose a tab and click Sign In. Student tab: any email works. Administrator tab: use an email containing “admin”. Password “wrongpass” shows the error state.
            </AlertMessage>
          )}
        </form>
      )}
    </AuthCard>
  );
}
