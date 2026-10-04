export const PASSWORD_RULES = [
  { id: 'len', label: 'At least 8 characters', test: (p) => p.length >= 8 },
  { id: 'upper', label: 'One uppercase letter', test: (p) => /[A-Z]/.test(p) },
  { id: 'lower', label: 'One lowercase letter', test: (p) => /[a-z]/.test(p) },
  { id: 'num', label: 'One number', test: (p) => /\d/.test(p) },
];

export const required = (v, msg = 'This field is required.') => (!v || !String(v).trim() ? msg : '');

export const validateEmail = (v) =>
  !v?.trim()
    ? 'Email address is required.'
    : /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v.trim())
      ? ''
      : 'Please enter a valid email address.';

export const validateLoginPassword = (v) => (v ? '' : 'Password is required.');

export const validateNewPassword = (v) => {
  if (!v) return 'Password is required.';
  if (v.length < 8) return 'Password must be at least 8 characters.';
  if (!PASSWORD_RULES.every((r) => r.test(v)))
    return 'Password must include an uppercase letter, a lowercase letter, and a number.';
  return '';
};

export const validateConfirm = (password, confirm) =>
  !confirm ? 'Please confirm your password.' : password !== confirm ? 'Passwords do not match.' : '';

export const validateStudentId = (v) =>
  !v?.trim()
    ? 'Student ID is required.'
    : /^\d{4}-\d{5}$/.test(v.trim())
      ? ''
      : 'Use the format YYYY-NNNNN, for example 2026-00001.';

export const validateContact = (v) => {
  if (!v?.trim()) return 'Contact number is required.';
  return /^09\d{9}$/.test(v.replace(/[\s-]/g, '')) ? '' : 'Enter an 11-digit mobile number starting with 09.';
};

/** Drops empty messages so the result only holds real errors. */
export const clean = (errors) => Object.fromEntries(Object.entries(errors).filter(([, m]) => m));
