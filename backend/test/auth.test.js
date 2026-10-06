import { test, before, after, describe } from 'node:test';
import assert from 'node:assert/strict';

// Always use the throw-away test database, never the real one.
process.env.DB_NAME = process.env.TEST_DB_NAME || 'lnu_scholarship_test';
process.env.BCRYPT_ROUNDS = '4';
process.env.RATE_LIMIT_ENABLED = 'false';
process.env.MAX_FAILED_LOGINS = '3';
process.env.NODE_ENV = 'test';

const { default: request } = await import('supertest');
const { createApp } = await import('../src/app.js');
const { pool } = await import('../src/db.js');
const { config } = await import('../src/config.js');
const { mail } = await import('../src/services/mailer.js');
const bcrypt = (await import('bcryptjs')).default;

assert.ok(config.db.database.endsWith('_test'), 'tests must run on a *_test database');
const { app, store } = createApp();

const STUDENT = { email: 'student@lnu.edu.ph', password: 'Student123' };
const ADMIN = { email: 'admin@lnu.edu.ph', password: 'Admin1234' };
const reg = (o = {}) => ({
  first_name: 'Ana', middle_name: '', last_name: 'Reyes', student_id: '2026-00010', email: 'ana@lnu.edu.ph',
  contact_number: '09171112222', password: 'Passw0rdOK', accept_privacy: true, ...o,
});

async function createUser({ email, password, role, status = 'active', student = '2026-09999' }) {
  const [[r]] = await pool.execute('SELECT role_id FROM roles WHERE role_name = ?', [role]);
  const [u] = await pool.execute('INSERT INTO users (role_id, email, password_hash, account_status) VALUES (?, ?, ?, ?)', [r.role_id, email, await bcrypt.hash(password, 4), status]);
  if (role === 'applicant') await pool.execute('INSERT INTO applicants (user_id, student_number, first_name, last_name, mobile_number) VALUES (?, ?, ?, ?, ?)', [u.insertId, student, 'Juan', 'Dela Cruz', '09170000000']);
  else await pool.execute('INSERT INTO staff_profiles (user_id, first_name, last_name) VALUES (?, ?, ?)', [u.insertId, 'Maria', 'Santos']);
  return u.insertId;
}

let studentId;
before(async () => {
  studentId = await createUser({ ...STUDENT, role: 'applicant', student: '2026-00001' });
  await createUser({ ...ADMIN, role: 'administrator' });
});
after(async () => { await new Promise((r) => store.close(r)).catch(() => {}); await pool.end(); });

const login = (agent, creds, extra = {}) => agent.post('/auth/login').send({ ...creds, ...extra });

describe('registration', () => {
  test('creates a student account (role = applicant) with consents, hashed password', async () => {
    const res = await request(app).post('/auth/register').send(reg());
    assert.equal(res.status, 201);
    const [[u]] = await pool.execute("SELECT u.user_id, u.password_hash, r.role_name FROM users u JOIN roles r USING(role_id) WHERE email='ana@lnu.edu.ph'");
    assert.equal(u.role_name, 'applicant');
    assert.match(u.password_hash, /^\$2[aby]\$/);
    assert.notEqual(u.password_hash, 'Passw0rdOK');
    const [c] = await pool.execute('SELECT consent_type FROM privacy_consents WHERE user_id = ?', [u.user_id]);
    assert.equal(c.length, 2);
    const [[a]] = await pool.execute('SELECT birth_date, sex FROM applicants WHERE user_id = ?', [u.user_id]);
    assert.equal(a.birth_date, null); assert.equal(a.sex, null);
  });
  test('new student can sign in', async () => {
    const res = await login(request.agent(app), { email: 'ana@lnu.edu.ph', password: 'Passw0rdOK' }, { role: 'student' });
    assert.equal(res.status, 200);
  });
  test('rejects duplicate email and duplicate student id with field errors', async () => {
    const a = await request(app).post('/auth/register').send(reg({ student_id: '2026-00011' }));
    assert.equal(a.status, 409); assert.ok(a.body.fields.email);
    const b = await request(app).post('/auth/register').send(reg({ email: 'other@lnu.edu.ph' }));
    assert.equal(b.status, 409); assert.ok(b.body.fields.student_id);
  });
  test('validates input on the server', async () => {
    const res = await request(app).post('/auth/register').send(reg({ password: 'weak', student_id: 'bad', contact_number: '123', accept_privacy: false, email: 'nope' }));
    assert.equal(res.status, 400);
    for (const k of ['password', 'student_id', 'contact_number', 'accept_privacy', 'email']) assert.ok(res.body.fields[k], k);
  });
  test('cannot self-register as administrator (role field is ignored)', async () => {
    await request(app).post('/auth/register').send(reg({ student_id: '2026-00012', email: 'sneaky@lnu.edu.ph', role: 'administrator', role_id: 2 }));
    const [[u]] = await pool.execute("SELECT r.role_name FROM users u JOIN roles r USING(role_id) WHERE email='sneaky@lnu.edu.ph'");
    assert.equal(u.role_name, 'applicant');
  });
});

describe('login & sessions', () => {
  test('student login returns role student and an httpOnly cookie', async () => {
    const res = await login(request.agent(app), STUDENT, { role: 'student' });
    assert.equal(res.status, 200);
    assert.equal(res.body.user.role, 'student');
    assert.equal(res.body.user.first_name, 'Juan');
    assert.ok(!('password_hash' in res.body.user));
    assert.match(res.headers['set-cookie'][0], /iskolar\.sid=.*HttpOnly/i);
  });
  test('admin login returns role administrator', async () => {
    const res = await login(request.agent(app), ADMIN, { role: 'administrator' });
    assert.equal(res.status, 200);
    assert.equal(res.body.user.role, 'administrator');
  });
  test('wrong password and unknown email give the same generic error', async () => {
    const a = await login(request(app), { ...STUDENT, password: 'Nope12345' });
    const b = await login(request(app), { email: 'ghost@lnu.edu.ph', password: 'Nope12345' });
    assert.equal(a.status, 401); assert.equal(b.status, 401);
    assert.equal(a.body.message, b.body.message);
  });
  test('wrong tab: student cannot sign in on the administrator tab (and vice versa)', async () => {
    const agent = request.agent(app);
    const a = await login(agent, STUDENT, { role: 'administrator' });
    assert.equal(a.status, 403); assert.equal(a.body.code, 'ROLE_MISMATCH');
    assert.equal((await agent.get('/auth/session')).status, 401); // no session was created
    const b = await login(request.agent(app), ADMIN, { role: 'student' });
    assert.equal(b.status, 403);
  });
  test('session endpoint works after login and logout really ends the session', async () => {
    const agent = request.agent(app);
    await login(agent, STUDENT);
    assert.equal((await agent.get('/auth/session')).status, 200);
    assert.equal((await agent.post('/auth/logout')).status, 200);
    assert.equal((await agent.get('/auth/session')).status, 401);
    assert.equal((await agent.get('/api/student/me')).status, 401);
  });
  test('"keep me signed in" lengthens the session, default uses the idle timeout', async () => {
    const a = await login(request.agent(app), STUDENT);
    const b = await login(request.agent(app), STUDENT, { remember: true });
    assert.equal(a.body.idleTimeoutSeconds, config.idleMinutes * 60);
    assert.equal(b.body.idleTimeoutSeconds, config.rememberDays * 86400);
  });
  test('rejects requests from a foreign Origin (CSRF defence)', async () => {
    const res = await request(app).post('/auth/login').set('Origin', 'http://evil.example').send(STUDENT);
    assert.equal(res.status, 403);
  });
});

describe('role-based access control', () => {
  test('anonymous users get 401 on protected routes', async () => {
    assert.equal((await request(app).get('/api/student/me')).status, 401);
    assert.equal((await request(app).get('/api/admin/me')).status, 401);
  });
  test('student can reach student routes but gets 403 on admin routes', async () => {
    const agent = request.agent(app);
    await login(agent, STUDENT);
    const ok = await agent.get('/api/student/me');
    assert.equal(ok.status, 200); assert.equal(ok.body.user.role, 'student');
    const denied = await agent.get('/api/admin/me');
    assert.equal(denied.status, 403); assert.equal(denied.body.code, 'FORBIDDEN');
  });
  test('administrator can reach admin routes but gets 403 on student routes', async () => {
    const agent = request.agent(app);
    await login(agent, ADMIN);
    assert.equal((await agent.get('/api/admin/me')).status, 200);
    assert.equal((await agent.get('/api/student/me')).status, 403);
  });
  test('a role change in the database takes effect immediately', async () => {
    const id = await createUser({ email: 'promo@lnu.edu.ph', password: 'Promo12345', role: 'applicant', student: '2026-07777' });
    const agent = request.agent(app);
    await login(agent, { email: 'promo@lnu.edu.ph', password: 'Promo12345' });
    assert.equal((await agent.get('/api/admin/me')).status, 403);
    await pool.execute("UPDATE users SET role_id = 2 WHERE user_id = ?", [id]);
    assert.equal((await agent.get('/api/admin/me')).status, 200);
    assert.equal((await agent.get('/api/student/me')).status, 403);
  });
  test('suspending an account kills its existing session', async () => {
    const id = await createUser({ email: 'susp@lnu.edu.ph', password: 'Susp123456', role: 'applicant', student: '2026-06666' });
    const agent = request.agent(app);
    await login(agent, { email: 'susp@lnu.edu.ph', password: 'Susp123456' });
    assert.equal((await agent.get('/api/student/me')).status, 200);
    await pool.execute("UPDATE users SET account_status = 'suspended' WHERE user_id = ?", [id]);
    assert.equal((await agent.get('/api/student/me')).status, 401);
  });
});

describe('account protection', () => {
  test('inactive accounts cannot sign in even with the right password', async () => {
    await createUser({ email: 'off@lnu.edu.ph', password: 'Off1234567', role: 'applicant', status: 'deactivated', student: '2026-05555' });
    const res = await login(request(app), { email: 'off@lnu.edu.ph', password: 'Off1234567' });
    assert.equal(res.status, 403); assert.equal(res.body.code, 'ACCOUNT_INACTIVE');
  });
  test('locks the account after repeated failures, even for the right password', async () => {
    await createUser({ email: 'lock@lnu.edu.ph', password: 'Lock123456', role: 'applicant', student: '2026-04444' });
    for (let i = 0; i < config.maxFailedLogins; i++) assert.equal((await login(request(app), { email: 'lock@lnu.edu.ph', password: 'Wrong12345' })).status, 401);
    const res = await login(request(app), { email: 'lock@lnu.edu.ph', password: 'Lock123456' });
    assert.equal(res.status, 423); assert.equal(res.body.code, 'LOCKED');
    await pool.execute("UPDATE users SET locked_until = NULL WHERE email = 'lock@lnu.edu.ph'"); // simulate lock expiring
    assert.equal((await login(request(app), { email: 'lock@lnu.edu.ph', password: 'Lock123456' })).status, 200);
  });
  test('unknown emails get the same lockout behaviour (no account enumeration)', async () => {
    for (let i = 0; i < config.maxFailedLogins; i++) await login(request(app), { email: 'nobody@lnu.edu.ph', password: 'Wrong12345' });
    assert.equal((await login(request(app), { email: 'nobody@lnu.edu.ph', password: 'Wrong12345' })).status, 423);
  });
  test('logins, failures and logouts are written to login_attempts and audit_logs', async () => {
    const agent = request.agent(app);
    await login(agent, STUDENT); await agent.post('/auth/logout');
    const [acts] = await pool.execute('SELECT DISTINCT action FROM audit_logs WHERE user_id = ?', [studentId]);
    const names = acts.map((a) => a.action);
    for (const n of ['login_success', 'logout', 'login_role_mismatch']) assert.ok(names.includes(n), n);
    const [[{ n }]] = await pool.execute('SELECT COUNT(*) AS n FROM login_attempts WHERE user_id = ? AND was_successful = 1', [studentId]);
    assert.ok(n > 0);
  });
});

describe('password recovery', () => {
  test('same response for known and unknown emails; token resets password once and revokes sessions', async () => {
    const links = [];
    mail.sendPasswordReset = async ({ link }) => links.push(link);

    const unknown = await request(app).post('/auth/forgot-password').send({ email: 'ghost2@lnu.edu.ph' });
    const known = await request(app).post('/auth/forgot-password').send({ email: ADMIN.email });
    assert.deepEqual(unknown.body, known.body);
    assert.equal(links.length, 1);
    const token = new URL(links[0]).searchParams.get('token');

    const adminAgent = request.agent(app);
    await login(adminAgent, ADMIN);
    assert.equal((await adminAgent.get('/auth/session')).status, 200);

    assert.equal((await request(app).post('/auth/reset-password').send({ token, password: 'weak' })).status, 400);
    assert.equal((await request(app).post('/auth/reset-password').send({ token, password: 'NewAdmin123' })).status, 200);
    assert.equal((await request(app).post('/auth/reset-password').send({ token, password: 'Another123A' })).status, 400); // single use

    assert.equal((await adminAgent.get('/auth/session')).status, 401); // old sessions revoked
    assert.equal((await login(request(app), ADMIN)).status, 401);
    assert.equal((await login(request(app), { email: ADMIN.email, password: 'NewAdmin123' })).status, 200);
  });
  test('rejects malformed and expired tokens', async () => {
    assert.equal((await request(app).post('/auth/reset-password').send({ token: 'abc', password: 'Valid1234' })).status, 400);
    await pool.execute("UPDATE password_resets SET expires_at = DATE_SUB(NOW(), INTERVAL 1 DAY)");
    assert.equal((await request(app).post('/auth/reset-password').send({ token: 'a'.repeat(64), password: 'Valid1234' })).status, 400);
  });
});
