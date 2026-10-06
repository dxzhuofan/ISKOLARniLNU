// Creates one test student and one test administrator. Development only.
import bcrypt from 'bcryptjs';
import { config } from '../src/config.js';
import { pool } from '../src/db.js';
import { applicantsRequireExtraFields } from '../src/schemaCheck.js';

if (config.isProd) { console.error('Refusing to seed test accounts in production.'); process.exit(1); }

const ACCOUNTS = [
  { email: 'student@lnu.edu.ph', password: 'Student123', role: 'applicant' },
  { email: 'admin@lnu.edu.ph', password: 'Admin1234', role: 'administrator' },
];

const placeholders = await applicantsRequireExtraFields(pool);
for (const a of ACCOUNTS) {
  const [[exists]] = await pool.execute('SELECT user_id FROM users WHERE email = ?', [a.email]);
  if (exists) { console.log(`- ${a.email} already exists, skipped`); continue; }
  const [[role]] = await pool.execute('SELECT role_id FROM roles WHERE role_name = ?', [a.role]);
  const hash = await bcrypt.hash(a.password, config.bcryptRounds);
  const [u] = await pool.execute("INSERT INTO users (role_id, email, password_hash, account_status, email_verified_at) VALUES (?, ?, ?, 'active', NOW())", [role.role_id, a.email, hash]);
  if (a.role === 'applicant') {
    await pool.execute(
      'INSERT INTO applicants (user_id, student_number, first_name, last_name, mobile_number, birth_date, sex) VALUES (?, ?, ?, ?, ?, ?, ?)',
      [u.insertId, '2026-00001', 'Juan', 'Dela Cruz', '09171234567', placeholders ? '2000-01-01' : null, placeholders ? 'Male' : null],
    );
  } else {
    await pool.execute('INSERT INTO staff_profiles (user_id, employee_no, first_name, last_name, position_title) VALUES (?, ?, ?, ?, ?)', [u.insertId, 'EMP-0001', 'Maria', 'Santos', 'Scholarship Officer']);
  }
  console.log(`+ created ${a.role}: ${a.email} / ${a.password}`);
}
if (placeholders) console.log('\nNote: used placeholder birth date/sex because those columns are still NOT NULL.');
await pool.end();
