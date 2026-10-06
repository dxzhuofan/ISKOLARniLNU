// Writes to the team's existing audit_logs and login_attempts tables. Never throws.
export async function audit(pool, { userId = null, action, entityId = null, details = null, ip = null }) {
  try {
    await pool.execute(
      'INSERT INTO audit_logs (user_id, action, entity_type, entity_id, details, ip_address) VALUES (?, ?, ?, ?, ?, ?)',
      [userId, action, 'user', entityId ?? userId, details ? JSON.stringify(details) : null, ip],
    );
  } catch (e) {
    console.error('[audit] failed:', e.message);
  }
}

export async function recordAttempt(pool, { userId = null, email, ip = null, userAgent = null, ok }) {
  try {
    await pool.execute(
      'INSERT INTO login_attempts (user_id, email_attempted, ip_address, user_agent, was_successful) VALUES (?, ?, ?, ?, ?)',
      [userId, String(email).slice(0, 150), ip, userAgent ? userAgent.slice(0, 255) : null, ok ? 1 : 0],
    );
  } catch (e) {
    console.error('[login_attempts] failed:', e.message);
  }
}
