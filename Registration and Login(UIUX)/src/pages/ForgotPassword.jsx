import { useState } from 'react';
import { Link } from 'react-router-dom';
import AuthCard from '../components/AuthCard';
import InputField from '../components/InputField';
import LoadingButton from '../components/LoadingButton';
import AlertMessage from '../components/AlertMessage';
import SuccessMark from '../components/SuccessMark';
import useForm from '../hooks/useForm';
import { requestPasswordReset } from '../services/authService';
import { clean, validateEmail } from '../utils/validators';

export default function ForgotPassword() {
  const [status, setStatus] = useState('idle');
  const [formError, setFormError] = useState('');
  const form = useForm({ email: '' }, (v) => clean({ email: validateEmail(v.email) }));

  const onSubmit = form.handleSubmit(async ({ email }) => {
    if (status === 'loading') return;
    setStatus('loading');
    setFormError('');
    try {
      await requestPasswordReset({ email: email.trim() });
      setStatus('success');
    } catch (err) {
      setFormError(err.message);
      setStatus('idle');
    }
  });

  return (
    <AuthCard
      title="Forgot Password"
      subtitle="Enter your email and we'll send you a reset link."
      footer={<Link to="/login" className="link">Back to Sign In</Link>}
    >
      {status === 'success' ? (
        <SuccessMark title="Check your email">
          If an account exists with this email address, a password reset link has been sent.
        </SuccessMark>
      ) : (
        <form onSubmit={onSubmit} noValidate className="space-y-5">
          {formError && <AlertMessage variant="error">{formError}</AlertMessage>}
          <InputField label="Email Address" type="email" autoComplete="email" placeholder="yourname@lnu.edu.ph" {...form.field('email')} />
          <LoadingButton loading={status === 'loading'} loadingText="Sending...">Send Reset Link</LoadingButton>
        </form>
      )}
    </AuthCard>
  );
}
