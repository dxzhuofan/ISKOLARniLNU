// Server-side mirror of the frontend rules. Never trust the browser.
export const EMAIL_RE = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

export function passwordProblem(p) {
  if (typeof p !== 'string' || !p) return 'Password is required.';
  if (p.length < 8) return 'Password must be at least 8 characters.';
  if (Buffer.byteLength(p) > 72) return 'Password must be at most 72 characters.';
  if (!/[A-Z]/.test(p) || !/[a-z]/.test(p) || !/\d/.test(p))
    return 'Password must include an uppercase letter, a lowercase letter, and a number.';
  return '';
}

export function validateRegistration(b = {}) {
  const f = {};
  const str = (v) => (typeof v === 'string' ? v.trim() : '');
  const first = str(b.first_name), last = str(b.last_name), middle = str(b.middle_name);
  if (!first) f.first_name = 'First name is required.';
  else if (first.length > 80) f.first_name = 'First name is too long.';
  if (!last) f.last_name = 'Last name is required.';
  else if (last.length > 80) f.last_name = 'Last name is too long.';
  if (middle.length > 80) f.middle_name = 'Middle name is too long.';

  const sid = str(b.student_id);
  if (!/^\d{4}-\d{5}$/.test(sid)) f.student_id = 'Use the format YYYY-NNNNN, for example 2026-00001.';

  const email = str(b.email).toLowerCase();
  if (!EMAIL_RE.test(email) || email.length > 150) f.email = 'Please enter a valid email address.';

  const contact = str(b.contact_number).replace(/[\s-]/g, '');
  if (!/^09\d{9}$/.test(contact)) f.contact_number = 'Enter an 11-digit mobile number starting with 09.';

  const pw = passwordProblem(b.password);
  if (pw) f.password = pw;
  if (b.accept_privacy !== true) f.accept_privacy = 'You must accept the Data Privacy Notice and Terms of Use.';

  return {
    fields: f,
    values: { first_name: first, middle_name: middle || null, last_name: last, student_id: sid, email, contact_number: contact, password: b.password },
  };
}
