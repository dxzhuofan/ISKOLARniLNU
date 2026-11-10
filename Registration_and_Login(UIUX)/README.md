# LNU Scholarship Portal Authentication — adapted to your database

This version uses your existing MySQL database **`lnu_scholarship_db`** and its current schema. It does not create a replacement users table.

## Included
- Login page with Student and Staff tabs
- Both roles sign in with an email address ending in `@lnu.edu.ph`; student-number login is not accepted
- Registration writes to `users` and `applicants` in a transaction
- Role verification uses `roles.role_name` (`applicant`, `administrator`, `scholarship_officer`, or `reviewer`)
- Active account check and login attempt logging via `login_attempts`
- Updates `last_login_at`, failed login counter, and temporary lock fields
- Responsive LNU navy/gold UI, password visibility and password rules
- Three-step student registration with LNU-domain, 7-digit ID, PH mobile, and academic-program validation
- Email verification and password-recovery/password-change OTPs (hashed, single-use, 10-minute expiry, 60-second resend limit, and 15-minute lock after five incorrect codes)
- Academic program change requests that remain pending until an administrator approves them
- New-device email alerts and hashed trusted-device tokens
- Protected student/admin placeholder dashboards
- OTP-based forgot-password flow (configure PHP mail/SMTP for delivery)

## Setup
1. Keep a backup of your database before testing.
2. Extract this folder to `C:\xampp\htdocs\lnu_scholarship_auth_dbadapt`.
3. Start Apache and MySQL in XAMPP.
4. Confirm your imported database is named `lnu_scholarship_db`.
5. In `config.php`, set the MySQL username/password to your local values.
6. Back up `lnu_scholarship_db`, then run `migrations/20261030_registration_otp.sql` and `../ADMIN_DASHBOARD/migrations/20261031_scholarship_admin.sql` in phpMyAdmin. Registration, OTP, trusted-device alerts, program-change approval, scholarship eligibility, audit logging, and staff roles require these migrations.
7. Open `http://localhost/Registration_and_Login(UIUX)/`.

**Do not import the old demo schema.** Your uploaded SQL dump already contains the tables used by this app.

## Database compatibility notes
- Student registration creates a `users` row with the `applicant` role, then creates its `applicants` profile row.
- Staff accounts must be provisioned by authorized staff using `create_admin.ps1`; the public registration form cannot create staff accounts. Existing `administrator` accounts are Super Admins. Scholarship Officers can manage scholarships and decisions; Reviewers can review applications.
- Existing users with `account_status` other than `active` cannot log in.
- Some existing sample rows may have placeholder/non-compatible passwords; reset/set a valid password through a secure admin process before testing them.

## Create the first administrator account on Windows
1. Start MySQL in XAMPP and confirm the database credentials in `config.php`.
2. Open PowerShell and run:
   ```powershell
   powershell -NoProfile -ExecutionPolicy Bypass -File "C:\xampp\htdocs\ISKOLARniLNU\Registration_and_Login(UIUX)\create_admin.ps1"
   ```
3. Enter the staff member's name and LNU email address, then set a unique password of at least 12 characters when prompted. The password prompts are hidden; the password is not passed as a command-line argument or stored in a file.
4. Choose the staff role when prompted, then sign in through `login.php` using the Staff tab.

The provisioning PHP script is CLI-only, creates the selected staff user and profile in one database transaction, and refuses to overwrite an existing email or employee number. Do not expose the setup script as a public web form.

## Production notes
Use HTTPS, secure session-cookie flags, configured SMTP delivery, rate limiting, and institutional security/privacy review before deployment. Ensure `APP_ENV` is set to `production` so verification codes are never displayed in the browser.


## Motion and interaction enhancements
- Gentle wavy shape reveal and staggered entrance animation
- Animated Student / Staff tab indicator with keyboard arrow support
- Live password requirement feedback and password confirmation styling
- Loading state on valid form submission (the server remains responsible for actual results)
- Toast notifications based on actual server-rendered success/error messages
- Button, form-card, and dashboard-card hover/press feedback
- `prefers-reduced-motion` support to minimize animations when requested by the operating system

## Suggested test checklist
1. Open the login page and confirm the animation is subtle; enable reduced motion in your OS/browser and confirm it is minimized.
2. Switch Student / Staff tabs using mouse and keyboard arrow keys.
3. Submit login with missing fields and confirm inline validation and error toast appear.
4. Enter a wrong password and confirm the server displays an error; verify no dashboard access is granted.
5. Register with a weak password, mismatched confirmation, or unchecked terms and confirm the form blocks submission.
6. Register with valid details and confirm the success notification appears only after the database insert succeeds.
7. Test the Student tab with an `applicant` account; test staff access with `administrator`, `scholarship_officer`, and `reviewer` accounts, confirming each role sees only its permitted pages.
8. Resize to mobile width and test keyboard focus, password visibility, buttons, and toast dismissal.

The loading state is visual feedback only; it does not indicate success. The PHP/database response remains authoritative. Toast messages are generated from server-rendered messages and are not used to simulate authentication outcomes.


## Email verification and recovery setup

Registration, forgot-password, and password-change verification use the `auth_otps` table created by the migration. OTPs are password-hashed in the database, expire after 10 minutes, are single-use, can be resent once per minute, and lock verification for 15 minutes after five incorrect attempts.

For reliable delivery from XAMPP, this app supports SMTP using PHPMailer. PHPMailer is installed in this workspace. On a fresh copy, install Composer for Windows from [getcomposer.org](https://getcomposer.org/download/), then run these PowerShell commands to install the dependency:

```powershell
Set-Location 'C:\xampp\htdocs\ISKOLARniLNU\Registration_and_Login(UIUX)'
composer install
```

In XAMPP's `C:\xampp\apache\conf\extra\httpd-xampp.conf`, add the `LNU_MAIL_*` settings below (do not put passwords in PHP source code or commit them):

```apache
SetEnv LNU_MAIL_HOST smtp.gmail.com
SetEnv LNU_MAIL_PORT 587
SetEnv LNU_MAIL_ENCRYPTION tls
SetEnv LNU_MAIL_USERNAME your-sender@gmail.com
SetEnv LNU_MAIL_PASSWORD your-google-app-password
SetEnv LNU_MAIL_FROM your-sender@gmail.com
```

For Gmail, use an account you control as the sender, enable 2-Step Verification, and create a Google App Password in your Google Account settings. Enter the App Password in your private Apache configuration without the spaces Google displays; never use your regular Gmail password. The recipient remains the student's LNU email. Restart Apache after configuring these values. `LNU_MAIL_FROM` should be the authenticated Gmail address.

The project is not ready to send until SMTP credentials are configured. For local development only, `APP_ENV = 'local'` displays the generated code on the verification page when delivery fails. Before deployment, set `APP_ENV = 'production'`, use HTTPS, and never expose development codes. Password recovery continues to accept previously issued reset links for compatibility; new recovery requests use OTPs.

The catalog of colleges, programs, types, and specializations is maintained in `academic_catalog.php` and validated on the server. Teacher Certificate Program year-level and scholarship eligibility rules are intentionally marked pending Scholarship Office confirmation; no eligibility exception is assumed.

Academic selections are stored in `applicant_academic_profiles` and cannot be changed through the ordinary profile form. Students submit a reasoned request from their profile; administrators review it under **Academic Change Requests**. Approval is the only path that updates the stored program.

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
