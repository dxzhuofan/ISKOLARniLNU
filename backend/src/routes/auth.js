import { Router } from 'express';
import bcrypt from 'bcryptjs';
import crypto from 'node:crypto';
import rateLimit from 'express-rate-limit';
import { config } from '../config.js';
import { pool } from '../db.js';
import { audit, recordAttempt } from '../services/audit.js';
import { mail } from '../services/mailer.js';
import { requireAuth } from '../middleware/auth.js';
import { API_ROLES, toApiRole } from '../utils/roles.js';
import { EMAIL_RE, passwordProblem, validateRegistration } from '../utils/validate.js';

const router = Router();
const GENERIC_LOGIN_ERROR = 'Invalid email or password.';
const DUMMY_HASH = bcrypt.hashSync('timing-equalizer-password', config.bcryptRounds);
const sha256 = (s) => crypto.createHash('sha256').update(s).digest('hex');
const ctx = (req) => ({ ip: req.ip, userAgent: req.get('user-agent') });

const limiter = (windowMs, limit, message) =>
  rateLimit({ windowMs, limit, standardHeaders: true, legacyHeaders: false, skip: () => !config.rateLimitEnabled, message: { message } });
const loginLimiter = limiter(15 * 60 * 1000, 30, 'Too many sign-in attempts. Please try again later.');
const registerLimiter = limiter(60 * 60 * 1000, 15, 'Too many registration attempts. Please try again later.');
const recoveryLimiter = limiter(60 * 60 * 1000, 10, 'Too many requests. Please try again later.');

const regenerate = (req) => new Promise((res, rej) => req.session.regenerate((e) => (e ? rej(e) : res())));
const save = (req) => new Promise((res, rej) => req.session.save((e) => (e ? rej(e) : res())));
const lockedMessage = (minutes) =>
  `Too many failed attempts. Please try again in ${minutes} minute${minutes === 1 ? '' : 's'}.`;

/** Logs a user out everywhere (used after a password reset). Sessions store `uid` as a string. */
async function destroyUserSessions(userId) {
  try {
    await pool.execute('DELETE FROM sessions WHERE data LIKE ?', [`%"uid":"${Number(userId)}"%`]);
  } catch (e) {
    console.error('[sessions] cleanup failed:', e.message);
  }
}

// ---------------------------------------------------------------- LOGIN
router.post('/login', loginLimiter, async (req, res, next) => {
  try {
    const email = String(req.body?.email ?? '').trim().toLowerCase();
    const password = typeof req.body?.password === 'string' ? req.body.password : '';
    const remember = req.body?.remember === true;
    const wantedRole = req.body?.role;
    if (!email || !password) return res.status(400).json({ message: 'Email and password are required.' });
    if (wantedRole !== undefined && !API_ROLES.includes(wantedRole)) return res.status(400).json({ message: 'Invalid role.' });
    const c = ctx(req);

    const [rows] = await pool.execute(
      `SELECT u.user_id, u.email, u.password_hash, u.account_status, r.role_name,
              (u.locked_until IS NOT NULL AND u.locked_until > NOW()) AS is_locked,
              CEIL(TIMESTAMPDIFF(SECOND, NOW(), u.locked_until) / 60) AS lock_minutes,
              COALESCE(a.first_name, s.first_name) AS first_name, COALESCE(a.last_name, s.last_name) AS last_name
       FROM users u JOIN roles r ON r.role_id = u.role_id
       LEFT JOIN applicants a ON a.user_id = u.user_id
       LEFT JOIN staff_profiles s ON s.user_id = u.user_id
       WHERE u.email = ? LIMIT 1`,
      [email],
    );
    const user = rows[0];

    // Unknown email: behave like a real account (same timing, same lockout behaviour).
    if (!user) {
      await bcrypt.compare(password, DUMMY_HASH);
      const [[{ n }]] = await pool.execute(
        `SELECT COUNT(*) AS n FROM login_attempts
         WHERE email_attempted = ? AND was_successful = 0 AND attempted_at > DATE_SUB(NOW(), INTERVAL ? MINUTE)`,
        [email, config.lockMinutes],
      );
      await recordAttempt(pool, { email, ...c, ok: false });
      await audit(pool, { action: 'login_failed', details: { email, reason: 'unknown_email' }, ip: c.ip });
      if (n >= config.maxFailedLogins) return res.status(423).json({ message: lockedMessage(config.lockMinutes), code: 'LOCKED' });
      return res.status(401).json({ message: GENERIC_LOGIN_ERROR });
    }

    if (user.is_locked) {
      await recordAttempt(pool, { userId: user.user_id, email, ...c, ok: false });
      await audit(pool, { userId: user.user_id, action: 'login_blocked_locked', ip: c.ip });
      return res.status(423).json({ message: lockedMessage(Number(user.lock_minutes) || 1), code: 'LOCKED' });
    }

    const passwordOk = await bcrypt.compare(password, user.password_hash);
    if (!passwordOk) {
      // locked_until is assigned BEFORE failed_login_count so it sees the old count (MySQL evaluates left to right).
      await pool.execute(
        `UPDATE users SET
           locked_until = IF(failed_login_count + 1 >= ?, DATE_ADD(NOW(), INTERVAL ? MINUTE), locked_until),
           failed_login_count = IF(failed_login_count + 1 >= ?, 0, failed_login_count + 1)
         WHERE user_id = ?`,
        [config.maxFailedLogins, config.lockMinutes, config.maxFailedLogins, user.user_id],
      );
      await recordAttempt(pool, { userId: user.user_id, email, ...c, ok: false });
      await audit(pool, { userId: user.user_id, action: 'login_failed', details: { reason: 'wrong_password' }, ip: c.ip });
      return res.status(401).json({ message: GENERIC_LOGIN_ERROR });
    }

    // From here the credentials are correct.
    if (user.account_status !== 'active') {
      await recordAttempt(pool, { userId: user.user_id, email, ...c, ok: false });
      await audit(pool, { userId: user.user_id, action: 'login_blocked_inactive', details: { status: user.account_status }, ip: c.ip });
      const message = user.account_status === 'pending_verification'
        ? 'Please verify your email address before signing in.'
        : 'Your account is not active. Please contact the scholarship office.';
      return res.status(403).json({ message, code: 'ACCOUNT_INACTIVE' });
    }

    const role = toApiRole(user.role_name);
    if (wantedRole && wantedRole !== role) {
      await recordAttempt(pool, { userId: user.user_id, email, ...c, ok: false });
      await audit(pool, { userId: user.user_id, action: 'login_role_mismatch', details: { wantedRole, role }, ip: c.ip });
      const label = wantedRole === 'administrator' ? 'an administrator' : 'a student';
      return res.status(403).json({ message: `This account is not ${label} account. Use the other tab to sign in.`, code: 'ROLE_MISMATCH' });
    }

    await pool.execute('UPDATE users SET failed_login_count = 0, locked_until = NULL, last_login_at = NOW() WHERE user_id = ?', [user.user_id]);

    await regenerate(req); // new session id on login prevents session fixation
    req.session.user = { id: user.user_id };
    req.session.uid = String(user.user_id);
    req.session.cookie.maxAge = (remember ? config.rememberDays * 24 * 60 : config.idleMinutes) * 60 * 1000;
    await save(req);

    await recordAttempt(pool, { userId: user.user_id, email, ...c, ok: true });
    await audit(pool, { userId: user.user_id, action: 'login_success', details: { role, remember }, ip: c.ip });
    res.json({
      user: { id: user.user_id, email: user.email, role, first_name: user.first_name, last_name: user.last_name },
      idleTimeoutSeconds: Math.round(req.session.cookie.originalMaxAge / 1000),
    });
  } catch (e) {
    next(e);
  }
});

// ------------------------------------------------------------- REGISTER
router.post('/register', registerLimiter, async (req, res, next) => {
  const { fields, values } = validateRegistration(req.body);
  if (Object.keys(fields).length) return res.status(400).json({ message: 'Please review the highlighted fields.', fields });

  const conn = await pool.getConnection();
  try {
    const hash = await bcrypt.hash(values.password, config.bcryptRounds);
    await conn.beginTransaction();
    const [[role]] = await conn.execute("SELECT role_id FROM roles WHERE role_name = 'applicant'");
    // Public registration can ONLY create students. Administrators are never created here.
    const [u] = await conn.execute(
      "INSERT INTO users (role_id, email, password_hash, account_status) VALUES (?, ?, ?, 'active')",
      [role.role_id, values.email, hash],
    );
    await conn.execute(
      'INSERT INTO applicants (user_id, student_number, first_name, middle_name, last_name, mobile_number) VALUES (?, ?, ?, ?, ?, ?)',
      [u.insertId, values.student_id, values.first_name, values.middle_name, values.last_name, values.contact_number],
    );
    for (const type of ['data_privacy_notice', 'terms_of_use']) {
      await conn.execute('INSERT INTO privacy_consents (user_id, consent_type, policy_version, ip_address) VALUES (?, ?, ?, ?)', [u.insertId, type, '1.0', req.ip]);
    }
    await conn.commit();
    await audit(pool, { userId: u.insertId, action: 'register', ip: req.ip });
    res.status(201).json({ ok: true });
  } catch (e) {
    await conn.rollback().catch(() => {});
    if (e.code === 'ER_DUP_ENTRY') {
      const msg = e.sqlMessage || '';
      if (msg.includes('uq_users_email')) return res.status(409).json({ message: 'Please review the highlighted field.', fields: { email: 'An account with this email already exists.' } });
      if (msg.includes('uq_applicants_student_no')) return res.status(409).json({ message: 'Please review the highlighted field.', fields: { student_id: 'An account with this Student ID already exists.' } });
    }
    if (e.code === 'ER_NO_DEFAULT_FOR_FIELD' || e.code === 'ER_BAD_NULL_ERROR') {
      console.error('[register] applicants.birth_date / sex are still NOT NULL. Run sql/01_applicants_optional_fields.sql.');
      return res.status(500).json({ message: 'Registration is temporarily unavailable. Please contact the administrator.' });
    }
    next(e);
  } finally {
    conn.release();
  }
});

// --------------------------------------------------------------- LOGOUT
router.post('/logout', (req, res) => {
  const userId = req.session?.user?.id ?? null;
  req.session.destroy(async () => {
    res.clearCookie('iskolar.sid');
    if (userId) await audit(pool, { userId, action: 'logout', ip: req.ip });
    res.json({ ok: true });
  });
});

// -------------------------------------------------------------- SESSION
router.get('/session', requireAuth, (req, res) => {
  res.json({ user: req.user, idleTimeoutSeconds: Math.round(req.session.cookie.originalMaxAge / 1000) });
});

// ------------------------------------------------------ FORGOT PASSWORD
router.post('/forgot-password', recoveryLimiter, async (req, res, next) => {
  try {
    const email = String(req.body?.email ?? '').trim().toLowerCase();
    if (!EMAIL_RE.test(email)) return res.status(400).json({ message: 'Please enter a valid email address.' });

    const [rows] = await pool.execute("SELECT user_id FROM users WHERE email = ? AND account_status = 'active' LIMIT 1", [email]);
    if (rows[0]) {
      const userId = rows[0].user_id;
      const token = crypto.randomBytes(32).toString('hex');
      await pool.execute('UPDATE password_resets SET used_at = NOW() WHERE user_id = ? AND used_at IS NULL', [userId]);
      await pool.execute(
        'INSERT INTO password_resets (user_id, token_hash, expires_at, ip_address) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 30 MINUTE), ?)',
        [userId, sha256(token), req.ip],
      );
      await audit(pool, { userId, action: 'password_reset_requested', ip: req.ip });
      await mail.sendPasswordReset({ to: email, link: `${config.clientOrigin}/reset-password?token=${token}` });
    }
    res.json({ ok: true }); // identical response whether or not the account exists
  } catch (e) {
    next(e);
  }
});

// ------------------------------------------------------- RESET PASSWORD
router.post('/reset-password', recoveryLimiter, async (req, res, next) => {
  const token = typeof req.body?.token === 'string' ? req.body.token : '';
  const problem = passwordProblem(req.body?.password);
  if (problem) return res.status(400).json({ message: problem, fields: { password: problem } });
  const invalid = () => res.status(400).json({ message: 'This reset link is invalid or has expired.', code: 'INVALID_TOKEN' });
  if (!/^[a-f0-9]{64}$/.test(token)) return invalid();

  const conn = await pool.getConnection();
  try {
    await conn.beginTransaction();
    const [rows] = await conn.execute(
      'SELECT reset_id, user_id FROM password_resets WHERE token_hash = ? AND used_at IS NULL AND expires_at > NOW() LIMIT 1 FOR UPDATE',
      [sha256(token)],
    );
    if (!rows[0]) { await conn.rollback(); return invalid(); }
    const hash = await bcrypt.hash(req.body.password, config.bcryptRounds);
    await conn.execute('UPDATE users SET password_hash = ?, failed_login_count = 0, locked_until = NULL WHERE user_id = ?', [hash, rows[0].user_id]);
    await conn.execute('UPDATE password_resets SET used_at = NOW() WHERE reset_id = ?', [rows[0].reset_id]);
    await conn.commit();
    await destroyUserSessions(rows[0].user_id);
    await audit(pool, { userId: rows[0].user_id, action: 'password_reset_completed', ip: req.ip });
    res.json({ ok: true });
  } catch (e) {
    await conn.rollback().catch(() => {});
    next(e);
  } finally {
    conn.release();
  }
});

export default router;
