import { useMemo, useState } from 'react';

/** Small form helper: values, touched-aware errors, server errors, submit guard. */
export default function useForm(initial, validate) {
  const [values, setValues] = useState(initial);
  const [touched, setTouched] = useState({});
  const [submitted, setSubmitted] = useState(false);
  const [serverErrors, setServerErrors] = useState({});
  const errors = useMemo(() => validate(values), [values]); // eslint-disable-line

  const onChange = (e) => {
    const { name, value, type, checked } = e.target;
    setValues((v) => ({ ...v, [name]: type === 'checkbox' ? checked : value }));
    setServerErrors((s) => (s[name] ? { ...s, [name]: undefined } : s));
  };
  const onBlur = (e) => setTouched((t) => ({ ...t, [e.target.name]: true }));

  const fieldError = (name) =>
    serverErrors[name] || (touched[name] || submitted ? errors[name] : undefined);

  const field = (name) => ({ name, value: values[name], onChange, onBlur, error: fieldError(name) });

  const handleSubmit = (fn) => async (e) => {
    e.preventDefault();
    setSubmitted(true);
    if (Object.keys(errors).length) {
      requestAnimationFrame(() => document.querySelector('[aria-invalid="true"]')?.focus());
      return;
    }
    await fn(values);
  };

  return { values, field, handleSubmit, setServerErrors };
}
