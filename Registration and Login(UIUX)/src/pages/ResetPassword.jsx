import { useState } from 'react';
import { Link, useNavigate, useSearchParams } from 'react-router-dom';
import AuthCard from '../components/AuthCard';
import PasswordInput from '../components/PasswordInput';
import PasswordStrengthIndicator from '../components/PasswordStrengthIndicator';
import LoadingButton from '../components/LoadingButton';
import AlertMessage from '../components/AlertMessage';
import SuccessMark from '../components/SuccessMark';
import useForm from '../hooks/useForm';
import { isDemoMode, resetPassword } from '../services/authService';
import { clean, validateConfirm, validateNewPassword } from '../utils/validators';

export default function ResetPassword() {
  const navigate = useNavigate();
  const token = useSearchParams()[0].get('token');
  const [status, setStatus] = useState('idle');
  const [formError, setFormError] = useState('');
  const [shake, setShake] = useState(false);

  const form = useForm({ password: '', confirm: '' }, (v) =>
    clean({ password: validateNewPassword(v.password), confirm: validateConfirm(v.password, v.confirm) }),
  );

  const onSubmit = form.handleSubmit(async ({ password }) => {
    if (status === 'loading') return;
    setStatus('loading');
    setFormError('');
    try {
      await resetPassword({ token, password });
      setStatus('success');
    } catch (err) {
      setFormError(err.message);
      setStatus('idle');
      setShake(true);
    }
  });

  const linkMissing = !token && !isDemoMode;

  return (
    <AuthCard
      title="Reset Password"
      subtitle="Choose a new password for your account."
      shake={shake}
      onShakeEnd={() => setShake(false)}
      footer={<Link to="/login" className="link">Back to Sign In</Link>}
    >
      {status === 'success' ? (
        <SuccessMark title="Password updated">
          <p>Your password has been reset. You can now sign in.</p>
          <button onClick={() => navigate('/login')} className="btn-primary mt-6">Go to Sign In</button>
        </SuccessMark>
      ) : linkMissing ? (
        <AlertMessage variant="error">
          This reset link is invalid or has expired. <Link to="/forgot-password" className="link">Request a new link</Link>.
        </AlertMessage>
      ) : (
        <form onSubmit={onSubmit} noValidate className="space-y-5">
          {formError && <AlertMessage variant="error">{formError}</AlertMessage>}
          <div>
            <PasswordInput label="New Password" autoComplete="new-password" {...form.field('password')} />
            <PasswordStrengthIndicator password={form.values.password} />
          </div>
          <PasswordInput label="Confirm New Password" autoComplete="new-password" {...form.field('confirm')} />
          <LoadingButton loading={status === 'loading'} loadingText="Resetting...">Reset Password</LoadingButton>
        </form>
      )}
    </AuthCard>
  );
}
