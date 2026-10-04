/**
 * Authentication service — the ONLY place that talks to the backend.
 * UI components never call fetch() directly.
 *
 * Set VITE_API_URL to connect your real API. Expected endpoints (JSON):
 *   POST /auth/login           { email, password, remember }  -> { user: { id, email, first_name, role } }
 *   POST /auth/register        { first_name, middle_name, last_name, student_id, email, contact_number, password }
 *   POST /auth/forgot-password { email }
 *   POST /auth/reset-password  { token, password }
 *   POST /auth/logout
 *   GET  /auth/session         -> { user } or 401
 * Errors: non-2xx with { message, fields?: { email: "..." } }
 *
 * The backend must hash passwords, validate all input again, and keep the
 * session in an httpOnly, Secure cookie. Never store tokens or passwords in
 * localStorage.
 */
const API = import.meta.env.VITE_API_URL;

/** Demo mode exists only in `npm run dev` when no API is configured. Never in production builds. */
export const isDemoMode = import.meta.env.DEV && !API;

export class AuthError extends Error {
  constructor(message, status, fields) {
    super(message);
    this.status = status;
    this.fields = fields;
  }
}

const wait = (ms) => new Promise((r) => setTimeout(r, ms));

async function request(path, { method = 'POST', body } = {}) {
  if (!API) throw new AuthError('The sign-in service is not connected yet. Please contact the administrator.');
  const res = await fetch(`${API}${path}`, {
    method,
    credentials: 'include',
    headers: { 'Content-Type': 'application/json' },
    body: body ? JSON.stringify(body) : undefined,
  });
  const data = await res.json().catch(() => ({}));
  if (!res.ok) throw new AuthError(data.message || 'Something went wrong. Please try again.', res.status, data.fields);
  return data;
}

export async function loginUser({ email, password, remember }) {
  if (isDemoMode) {
    await wait(1100);
    if (password === 'wrongpass') throw new AuthError('Invalid email/username or password.', 401);
    const role = /admin/i.test(email) ? 'administrator' : 'student';
    return { user: { id: 'demo', email, first_name: 'Juan', role } };
  }
  return request('/auth/login', { body: { email, password, remember } });
}

export async function registerStudent(payload) {
  if (isDemoMode) {
    await wait(1200);
    if (payload.email.toLowerCase() === 'taken@lnu.edu.ph')
      throw new AuthError('Please review the highlighted field.', 409, { email: 'An account with this email already exists.' });
    return { ok: true };
  }
  return request('/auth/register', { body: payload });
}

export async function requestPasswordReset({ email }) {
  if (isDemoMode) return wait(1000);
  return request('/auth/forgot-password', { body: { email } });
}

export async function resetPassword({ token, password }) {
  if (isDemoMode) return wait(1100);
  return request('/auth/reset-password', { body: { token, password } });
}

export async function logoutUser() {
  if (isDemoMode) return;
  return request('/auth/logout');
}

export async function getSession() {
  if (!API) return null;
  try {
    return (await request('/auth/session', { method: 'GET' })).user ?? null;
  } catch {
    return null;
  }
}
