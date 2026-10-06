# ISKOLAR niLNU: Authentication API (role-based access)

Node.js + Express + MySQL/MariaDB. Works with the team's `lnu_scholarship_db` schema (SCRUM-8). It reuses the existing
`users`, `roles`, `applicants`, `staff_profiles`, `login_attempts`, `audit_logs`, `password_resets` and `privacy_consents`
tables and does **not** modify them (the one requested change is in `sql/01_...`, see step 1).

## 1. Database (once)
1. Start MySQL (XAMPP) and import the team's `lnu_scholarship_db.sql` (phpMyAdmin → Import).
2. Ask the database owner to run **`sql/01_applicants_optional_fields.sql`**. It makes `applicants.birth_date` and `sex` optional,
   because students register without them and complete them later in the profile/application form.
   Until then, registration answers "temporarily unavailable" and the API prints a warning at startup.
3. The `sessions` table is created automatically on first run (`sql/02_sessions.sql` is the same thing if you prefer to create it yourself).

## 2. Run
```bash
cd backend
npm install
copy .env.example .env      # macOS/Linux: cp .env.example .env   then edit DB_USER / DB_PASSWORD
npm run seed                # development only: creates the two test accounts below
npm run dev                 # http://localhost:4000
```
Then in the frontend folder, copy `.env.example` to `.env` (it points `VITE_API_URL` to `http://localhost:4000`) and run `npm run dev`.
Open the frontend at **http://localhost:5173** (use `localhost`, not `127.0.0.1`, so the cookie works).

| Test account | Email | Password | Tab |
|---|---|---|---|
| Student | student@lnu.edu.ph | Student123 | Student |
| Administrator | admin@lnu.edu.ph | Admin1234 | Administrator |

## 3. API
| Method & path | Who | Purpose |
|---|---|---|
| `POST /auth/login` | public | Validates credentials, checks the role against the chosen tab, starts a session |
| `POST /auth/register` | public | Creates a **student** (`applicant`) + `applicants` row + privacy consents, in one transaction |
| `POST /auth/logout` | anyone | Destroys the session and clears the cookie |
| `GET /auth/session` | signed in | Current user + idle timeout (also renews the session) |
| `POST /auth/forgot-password` | public | Same response whether or not the email exists |
| `POST /auth/reset-password` | public | One-time, 30-minute token; also signs the user out everywhere |
| `GET /api/student/me` | students only | Starter route for the Student Dashboard module |
| `GET /api/admin/me` | administrators only | Starter route for the Admin Dashboard module |

**For the dashboard teams:** add new routes inside `src/routes/protected.js`. The role check is applied in `src/app.js`, so every
route there is already protected (403 for the wrong role, 401 when signed out).

## 4. Security measures
- Passwords hashed with bcrypt; never logged, never returned.
- Sessions in an **httpOnly, SameSite=Lax cookie**, stored in MySQL; new session id on login (prevents fixation); idle timeout (default 30 min, 7 days with "Keep me signed in").
- **The database is re-checked on every protected request**, so suspending an account or changing a role takes effect immediately.
- Role is read from the database, never from the browser. Public registration can only create students; administrators are created only by a DBA/seed script.
- Wrong password and unknown email give the same message and timing. Lockout after 5 failures for 15 minutes (`users.failed_login_count/locked_until`; unknown emails behave identically).
- Rate limiting on login, registration and recovery; Helmet headers; CORS limited to `CLIENT_ORIGIN`; requests from other origins are rejected (CSRF defence).
- Every login, failure, lockout, role mismatch, access denial, logout and password reset is written to `audit_logs`; every attempt to `login_attempts`.

## 5. Tests
```bash
npm test
```
Creates a throw-away `lnu_scholarship_test` database (never touches your real data) and runs 23 tests covering registration, both roles' login,
wrong-tab login, 401/403 on every role boundary, immediate effect of role/status changes, logout, lockout, the "keep me signed in" duration,
the CSRF check, audit logging and the password-reset flow. Uses `DB_USER`/`DB_PASSWORD` from `.env`; that user needs permission to create databases.

## 6. Before going live
- Set a long random `SESSION_SECRET` and `NODE_ENV=production` (cookie becomes `Secure`, so serve over HTTPS).
- Serve the frontend and API on the **same site** (e.g. `app.lnu.edu.ph` and `api.lnu.edu.ph`); otherwise change the cookie to `SameSite=None; Secure` and add CSRF tokens.
- Connect a real email service in `src/services/mailer.js` (in development the reset link is printed in this terminal).
- Not included yet: email verification (accounts are active immediately), two-factor authentication for administrators, and a forced "change password" screen for `must_change_password`.
