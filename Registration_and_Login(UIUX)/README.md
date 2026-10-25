# LNU Scholarship Portal Authentication — adapted to your database

This version uses your existing MySQL database **`lnu_scholarship_db`** and its current schema. It does not create a replacement users table.

## Included
- Login page with Student and Administrator tabs
- Registration writes to `users` and `applicants` in a transaction
- Role verification uses `roles.role_name` (`applicant` / `administrator`)
- Active account check and login attempt logging via `login_attempts`
- Updates `last_login_at`, failed login counter, and temporary lock fields
- Responsive LNU navy/gold UI, password visibility and password rules
- Protected student/admin placeholder dashboards
- Forgot-password interface (email delivery/reset flow still needs configuring)

## Setup
1. Keep a backup of your database before testing.
2. Extract this folder to `C:\xampp\htdocs\lnu_scholarship_auth_dbadapt`.
3. Start Apache and MySQL in XAMPP.
4. Confirm your imported database is named `lnu_scholarship_db`.
5. In `config.php`, set the MySQL username/password to your local values.
6. Open `http://localhost/Registration_and_Login(UIUX)/`.

**Do not import the old demo schema.** Your uploaded SQL dump already contains the tables used by this app.

## Database compatibility notes
- Student registration creates a `users` row with the `applicant` role, then creates its `applicants` profile row.
- Administrator accounts must be provisioned by authorized staff using the existing `administrator` role; the public registration form cannot create admins.
- Existing users with `account_status` other than `active` cannot log in.
- Some existing sample rows may have placeholder/non-compatible passwords; reset/set a valid password through a secure admin process before testing them.
- Automated password-reset email delivery is not implemented. The UI explains this instead of pretending to send email.

## Create the first administrator account on Windows
1. Start MySQL in XAMPP and confirm the database credentials in `config.php`.
2. Open PowerShell and run:
   ```powershell
   powershell -NoProfile -ExecutionPolicy Bypass -File "C:\xampp\htdocs\ISKOLARniLNU\Registration_and_Login(UIUX)\create_admin.ps1"
   ```
3. Enter the staff member's name and email, then set a unique password of at least 12 characters when prompted. The password prompts are hidden; the password is not passed as a command-line argument or stored in a file.
4. Sign in through `login.php` using the Administrator tab.

The provisioning PHP script is CLI-only, creates both the administrator user and staff profile in one database transaction, and refuses to overwrite an existing email or employee number. Do not expose the setup script as a public web form.

## Production notes
Use HTTPS, secure session-cookie flags, real SMTP-based password recovery with expiring single-use tokens, rate limiting, email verification if required, and institutional security/privacy review before deployment.


## Motion and interaction enhancements
- Gentle wavy shape reveal and staggered entrance animation
- Animated Student / Administrator tab indicator with keyboard arrow support
- Live password requirement feedback and password confirmation styling
- Loading state on valid form submission (the server remains responsible for actual results)
- Toast notifications based on actual server-rendered success/error messages
- Button, form-card, and dashboard-card hover/press feedback
- `prefers-reduced-motion` support to minimize animations when requested by the operating system

## Suggested test checklist
1. Open the login page and confirm the animation is subtle; enable reduced motion in your OS/browser and confirm it is minimized.
2. Switch Student / Administrator tabs using mouse and keyboard arrow keys.
3. Submit login with missing fields and confirm inline validation and error toast appear.
4. Enter a wrong password and confirm the server displays an error; verify no dashboard access is granted.
5. Register with a weak password, mismatched confirmation, or unchecked terms and confirm the form blocks submission.
6. Register with valid details and confirm the success notification appears only after the database insert succeeds.
7. Test both dashboards using real accounts whose database roles are `applicant` and `administrator`.
8. Resize to mobile width and test keyboard focus, password visibility, buttons, and toast dismissal.

The loading state is visual feedback only; it does not indicate success. The PHP/database response remains authoritative. Toast messages are generated from server-rendered messages and are not used to simulate authentication outcomes.


## Password recovery setup

The recovery flow uses the existing `password_resets` table in `lnu_scholarship_db`. Tokens are generated using a cryptographically secure random source, stored only as SHA-256 hashes, expire after 30 minutes, and are invalidated after use. `forgot_password.php` sends reset messages with PHP `mail()`. Configure a working mail transport on your PHP server and set `MAIL_FROM` in `config.php`.

For local development only, `APP_ENV = 'local'` displays a reset link on the recovery page if mail delivery fails. Before deploying, set `APP_ENV = 'production'` and update `APP_BASE_URL` to the real HTTPS project URL. Do not leave local mode enabled on a public server.

## Existing database

Import your supplied `lnu_scholarship_db` SQL dump first. This project uses its existing `users`, `roles`, `applicants`, `staff_profiles`, `login_attempts`, and `password_resets` tables. Update `DB_HOST`, `DB_NAME`, `DB_USER`, and `DB_PASS` in `config.php` for your environment. Do not import the starter `schema.sql` over your existing database.

## Login illustration floating icons update

The login page now uses `assets/images/lnu-student-no-icons.png` as the student artwork and separate transparent PNG files for the graduation-cap, checklist, and books icons. Their floating motion is defined in `assets/css/style.css`; the login markup is in `login.php`.

Keep these image files together in `assets/images/`:
- `lnu-student-no-icons.png`
- `floating-cap.png`
- `floating-document.png`
- `floating-books.png`
- `lnu-student-illustration.png` (retained for other pages or fallback use)


Responsive layout update: the login page now adapts for wide desktop, tablet, and mobile. The student artwork moves into normal document flow on tablet and mobile so it no longer covers the intro copy, statistics, or sign-in form.
