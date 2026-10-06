import { pool } from '../db.js';
import { audit } from '../services/audit.js';
import { toApiRole } from '../utils/roles.js';

/**
 * Requires a valid session AND re-checks the database on every request, so a suspended
 * account or a changed role takes effect immediately (not only at next login).
 */
export async function requireAuth(req, res, next) {
  try {
    const uid = req.session?.user?.id;
    if (!uid) return res.status(401).json({ message: 'Please sign in to continue.', code: 'UNAUTHENTICATED' });

    const [rows] = await pool.execute(
      `SELECT u.user_id, u.email, u.account_status, r.role_name,
              COALESCE(a.first_name, s.first_name) AS first_name, COALESCE(a.last_name, s.last_name) AS last_name
       FROM users u JOIN roles r ON r.role_id = u.role_id
       LEFT JOIN applicants a ON a.user_id = u.user_id
       LEFT JOIN staff_profiles s ON s.user_id = u.user_id
       WHERE u.user_id = ? LIMIT 1`,
      [uid],
    );
    const u = rows[0];
    if (!u || u.account_status !== 'active') {
      await new Promise((r) => req.session.destroy(r));
      res.clearCookie('iskolar.sid');
      return res.status(401).json({ message: 'Your session is no longer valid. Please sign in again.', code: 'SESSION_INVALID' });
    }
    req.user = { id: u.user_id, email: u.email, role: toApiRole(u.role_name), first_name: u.first_name, last_name: u.last_name };
    next();
  } catch (e) {
    next(e);
  }
}

export const requireRole = (role) => [
  requireAuth,
  async (req, res, next) => {
    if (req.user.role !== role) {
      await audit(pool, { userId: req.user.id, action: 'access_denied', details: { path: req.originalUrl, required: role, actual: req.user.role }, ip: req.ip });
      return res.status(403).json({ message: 'You do not have permission to access this resource.', code: 'FORBIDDEN' });
    }
    next();
  },
];
