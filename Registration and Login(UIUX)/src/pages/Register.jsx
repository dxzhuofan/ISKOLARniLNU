import { useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import AuthCard from '../components/AuthCard';
import InputField from '../components/InputField';
import PasswordInput from '../components/PasswordInput';
import PasswordStrengthIndicator from '../components/PasswordStrengthIndicator';
import LoadingButton from '../components/LoadingButton';
import AlertMessage from '../components/AlertMessage';
import SuccessMark from '../components/SuccessMark';
import useForm from '../hooks/useForm';
import { registerStudent } from '../services/authService';
import { clean, required, validateConfirm, validateContact, validateEmail, validateNewPassword, validateStudentId } from '../utils/validators';

export default function Register() {
  const navigate = useNavigate();
  const [status, setStatus] = useState('idle'); // idle | loading | success
  const [formError, setFormError] = useState('');
  const [shake, setShake] = useState(false);

  const form = useForm(
    { first_name: '', middle_name: '', last_name: '', student_id: '', email: '', contact_number: '', password: '', confirm: '', accept_privacy: false },
    (v) => clean({
      first_name: required(v.first_name),
      last_name: required(v.last_name),
      student_id: validateStudentId(v.student_id),
      email: validateEmail(v.email),
      contact_number: validateContact(v.contact_number),
      password: validateNewPassword(v.password),
      confirm: validateConfirm(v.password, v.confirm),
      accept_privacy: v.accept_privacy ? '' : 'You must accept the Data Privacy Notice and Terms of Use.',
    }),
  );

  const onSubmit = form.handleSubmit(async (v) => {
    if (status === 'loading') return;
    setStatus('loading');
    setFormError('');
    const { confirm, ...rest } = v; // never send the confirmation field
    try {
      await registerStudent({
        ...rest,
        first_name: rest.first_name.trim(),
        middle_name: rest.middle_name.trim(),
        last_name: rest.last_name.trim(),
        student_id: rest.student_id.trim(),
        email: rest.email.trim(),
        contact_number: rest.contact_number.replace(/[\s-]/g, ''),
      });
      setStatus('success');
    } catch (err) {
      if (err.fields) form.setServerErrors(err.fields);
      setFormError(err.message);
      setStatus('idle');
      setShake(true);
    }
  });

  return (
    <AuthCard
      title="Create Student Account"
      subtitle="Register to apply for LNU scholarships."
      shake={shake}
      onShakeEnd={() => setShake(false)}
      footer={<p>Already have an account? <Link to="/login" className="link">Sign in</Link></p>}
    >
      {status === 'success' ? (
        <SuccessMark title="Account created">
          <p>Your student account is ready. You can now sign in.</p>
          <button onClick={() => navigate('/login')} className="btn-primary mt-6">Go to Sign In</button>
        </SuccessMark>
      ) : (
        <form onSubmit={onSubmit} noValidate className="space-y-5">
          {formError && <AlertMessage variant="error">{formError}</AlertMessage>}
          <div className="grid gap-5 sm:grid-cols-3">
            <InputField label="First Name" autoComplete="given-name" {...form.field('first_name')} />
            <InputField label="Middle Name" hint="Optional" autoComplete="additional-name" {...form.field('middle_name')} />
            <InputField label="Last Name" autoComplete="family-name" {...form.field('last_name')} />
          </div>
          <div className="grid gap-5 sm:grid-cols-2">
            <InputField label="Student ID" placeholder="2026-00001" inputMode="numeric" {...form.field('student_id')} />
            <InputField label="Contact Number" type="tel" placeholder="09XXXXXXXXX" autoComplete="tel" inputMode="tel" {...form.field('contact_number')} />
          </div>
          <InputField label="LNU Email Address" type="email" autoComplete="email" placeholder="yourname@lnu.edu.ph" {...form.field('email')} />
          <div>
            <PasswordInput label="Password" autoComplete="new-password" {...form.field('password')} />
            <PasswordStrengthIndicator password={form.values.password} />
          </div>
          <PasswordInput label="Confirm Password" autoComplete="new-password" {...form.field('confirm')} />
          <div>
            <label className="flex cursor-pointer items-start gap-3 text-slate-700">
              <input type="checkbox" name="accept_privacy" checked={form.values.accept_privacy} onChange={form.field('accept_privacy').onChange} onBlur={form.field('accept_privacy').onBlur}
                aria-invalid={form.field('accept_privacy').error ? 'true' : 'false'} aria-describedby="privacy-error" className="mt-1 size-5 shrink-0 cursor-pointer rounded border-slate-300 accent-navy-800" />
              <span>I have read and agree to the <a href="/privacy" target="_blank" rel="noreferrer" className="link">Data Privacy Notice</a> and the <a href="/terms" target="_blank" rel="noreferrer" className="link">Terms of Use</a>.</span>
            </label>
            {form.field('accept_privacy').error && <p id="privacy-error" role="alert" className="field-error mt-1.5 text-sm font-medium text-danger">{form.field('accept_privacy').error}</p>}
          </div>
          <LoadingButton loading={status === 'loading'} loadingText="Creating Account...">Create Account</LoadingButton>
        </form>
      )}
    </AuthCard>
  );
}
