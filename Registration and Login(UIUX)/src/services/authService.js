/**
 * Authentication service — the ONLY place that talks to the backend.
 * UI components never call fetch() directly.
 *
 * Set VITE_API_URL (e.g. http://localhost:4000) to connect the real API (see /backend).
 * Endpoints (JSON, cookie session):
 *   POST /auth/login           { email, password, remember, role }  -> { user, idleTimeoutSeconds }
 *                              role = "student" | "administrator" (the tab chosen; server answers 403 if the real role differs)
 *   POST /auth/register        { first_name, middle_name, last_name, student_id, email, contact_number, password, accept_privacy }
 *   POST /auth/forgot-password { email }
 *   POST /auth/reset-password  { token, password }
 *   POST /auth/logout
 *   GET  /auth/session         -> { user, idleTimeoutSeconds }  or 401
 * Errors: non-2xx with { message, fields?: { email: "..." } }
 *
 * Sessions live in an httpOnly cookie set by the server. The frontend never sees or stores
 * tokens or passwords.
 */
const API = import.meta.env.VITE_API_URL;

/** Demo mode exists only in `npm run dev` when no API is configured. Never in production builds. */
export const isDemoMode = import.meta.env.DEV && !API;
export const DEFAULT_IDLE_SECONDS = Number(import.meta.env.VITE_IDLE_SECONDS) || 1800;

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
  let res;
  try {
    res = await fetch(`${API}${path}`, {
      method,
      credentials: 'include',
      headers: { 'Content-Type': 'application/json' },
      body: body ? JSON.stringify(body) : undefined,
    });
  } catch {
    throw new AuthError('Unable to reach the server. Check your connection and try again.');
  }
  const data = await res.json().catch(() => ({}));
  if (!res.ok) throw new AuthError(data.message || 'Something went wrong. Please try again.', res.status, data.fields);
  return data;
}

export async function loginUser({ email, password, remember, role }) {
  if (isDemoMode) {
    await wait(1100);
    if (password === 'wrongpass') throw new AuthError('Invalid email or password.', 401);
    const actual = /admin/i.test(email) ? 'administrator' : 'student';
    if (role && role !== actual)
      throw new AuthError(`This account is not ${role === 'administrator' ? 'an administrator' : 'a student'} account. Use the other tab to sign in.`, 403);
    return { user: { id: 'demo', email, first_name: 'Juan', last_name: 'Dela Cruz', role: actual }, idleTimeoutSeconds: DEFAULT_IDLE_SECONDS };
  }
  return request('/auth/login', { body: { email, password, remember, role } });
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

/** Returns { user, idleTimeoutSeconds }, or null when signed out (401). Other failures throw. */
export async function getSession() {
  if (!API) return null;
  try {
    return await request('/auth/session', { method: 'GET' });
  } catch (e) {
    if (e.status === 401) return null;
    throw e;
  }
}
