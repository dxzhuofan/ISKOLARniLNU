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

export default function Login() {
  const { login } = useAuth();
  const navigate = useNavigate();
  const [status, setStatus] = useState('idle'); // idle | loading | success
  const [formError, setFormError] = useState('');
  const [shake, setShake] = useState(false);

  const form = useForm({ email: '', password: '', remember: false }, (v) =>
    clean({ email: validateEmail(v.email), password: validateLoginPassword(v.password) }),
  );

  const onSubmit = form.handleSubmit(async ({ email, password, remember }) => {
    if (status === 'loading') return;
    setStatus('loading');
    setFormError('');
    try {
      const user = await login({ email: email.trim(), password, remember });
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
      title="Student Sign In"
      subtitle="Access your LNU Scholarship Portal account."
      shake={shake}
      onShakeEnd={() => setShake(false)}
      footer={
        <>
          <p>No account? <Link to="/register" className="link">Register here</Link></p>
          <p className="mt-1 text-sm">For assistance: <a href="mailto:scholarship@lnu.edu.ph" className="link">scholarship@lnu.edu.ph</a></p>
        </>
      }
    >
      {status === 'success' ? (
        <SuccessMark title="Signed in">Taking you to your dashboard…</SuccessMark>
      ) : (
        <form onSubmit={onSubmit} noValidate className="space-y-5">
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
          <LoadingButton loading={status === 'loading'} loadingText="Signing in...">Sign In to Portal</LoadingButton>
          {isDemoMode && (
            <AlertMessage variant="info">
              <strong>Demo mode:</strong> Click Sign In with any email to explore the portal. Use an email containing “admin” for the administrator view, or the password “wrongpass” to see the error state.
            </AlertMessage>
          )}
        </form>
      )}
    </AuthCard>
  );
}
