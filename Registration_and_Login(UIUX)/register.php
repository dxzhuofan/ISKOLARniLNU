<?php
declare(strict_types=1);
require_once __DIR__ . '/config.php';
if (current_user()) redirect(dashboard_path_for_role((string)current_user()['role']));

$catalog = require __DIR__ . '/academic_catalog.php';
$errors = [];
$values = [
    'email' => '', 'first_name' => '', 'middle_name' => '', 'last_name' => '',
    'student_number' => '', 'mobile_number' => '', 'year_level' => '',
    'college_code' => '', 'program_code' => '', 'specialization' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    foreach ($values as $key => $_) {
        $values[$key] = trim((string)($_POST[$key] ?? ''));
    }
    $password = (string)($_POST['password'] ?? '');
    $confirm = (string)($_POST['confirm_password'] ?? '');

    foreach (['first_name' => 'First name', 'last_name' => 'Last name'] as $key => $label) {
        if ($values[$key] === '' || mb_strlen($values[$key]) > 80) $errors[] = "{$label} is required and must be at most 80 characters.";
    }
    if (mb_strlen($values['middle_name']) > 80) $errors[] = 'Middle name must be at most 80 characters.';
    if (!preg_match('/^\d{7}$/', $values['student_number'])) $errors[] = 'Student ID must contain exactly 7 digits.';
    if (!preg_match('/^09\d{9}$/', $values['mobile_number'])) $errors[] = 'Phone number must contain 11 digits and start with 09.';
    if (!filter_var($values['email'], FILTER_VALIDATE_EMAIL) || mb_strlen($values['email']) > 150
        || !preg_match('/@lnu\.edu\.ph$/i', $values['email'])) {
        $errors[] = 'Use a valid LNU email address ending in @lnu.edu.ph.';
    }
    if (strlen($password) < 8 || !preg_match('/[A-Z]/', $password) || !preg_match('/\d/', $password)
        || !preg_match('/[^A-Za-z0-9]/', $password)) {
        $errors[] = 'Password must be at least 8 characters and include an uppercase letter, a number, and a symbol.';
    }
    if ($password !== $confirm) $errors[] = 'The passwords do not match.';
    if (($_POST['agree'] ?? '') !== '1') $errors[] = 'Confirm the account information before continuing.';

    $college = $catalog[$values['college_code']] ?? null;
    $program = $college['programs'][$values['program_code']] ?? null;
    if (!$values['year_level'] || !preg_match('/^[1-5]$/', $values['year_level'])) {
        $errors[] = 'Choose a year level from 1 to 5.';
    }
    if (!$college || !$program) {
        $errors[] = 'Choose a valid college and program.';
    } elseif ($program['requires_specialization']) {
        if (!in_array($values['specialization'], $program['specializations'], true)) {
            $errors[] = 'Choose a valid specialization for the selected program.';
        }
    } elseif ($values['specialization'] !== '') {
        $errors[] = 'The selected program does not have a specialization.';
    }

    if (!$errors) {
        try {
            $pdo = db();
            $duplicate = $pdo->prepare(
                'SELECT a.student_number, u.email FROM applicants a
                 JOIN users u ON u.user_id = a.user_id
                 WHERE a.student_number = ? OR u.email = ? LIMIT 1'
            );
            $duplicate->execute([$values['student_number'], $values['email']]);
            if ($duplicate->fetch()) {
                $errors[] = 'That email or student ID is already registered.';
            } else {
                $_SESSION['pending_registration'] = [
                    'email' => $values['email'],
                    'first_name' => $values['first_name'],
                    'middle_name' => $values['middle_name'],
                    'last_name' => $values['last_name'],
                    'student_number' => $values['student_number'],
                    'mobile_number' => $values['mobile_number'],
                    'year_level' => (int)$values['year_level'],
                    'college_code' => $values['college_code'],
                    'program_code' => $values['program_code'],
                    'program_type' => $program['type'],
                    'specialization' => $values['specialization'],
                    'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                    'expires_at' => time() + 600,
                ];
                $otp = issue_auth_otp($pdo, null, $values['email'], 'registration');
                if ($otp['wait'] > 0 && $otp['code'] === null) {
                    $errors[] = 'A verification code was recently sent. Please wait before requesting another one.';
                } else {
                    $_SESSION['otp_context_email'] = $values['email'];
                    $_SESSION['otp_context_purpose'] = 'registration';
                    $_SESSION['otp_resend_after'] = time() + 60;
                    $_SESSION['dev_otp'] = APP_ENV === 'local' && !$otp['sent'] ? $otp['code'] : null;
                    $_SESSION['otp_delivery_error'] = !$otp['sent'] && APP_ENV !== 'local';
                    redirect('verify_otp.php?purpose=registration');
                }
            }
        } catch (Throwable $error) {
            error_log('LNU registration initiation error: ' . $error->getMessage());
            $errors[] = 'Registration could not be started. Confirm the database connection and try again.';
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Create Student Account | LNU Scholarship Portal</title>
<link rel="stylesheet" href="css/style.css">
</head>
<body class="auth-page register-page">
<header class="topbar">
  <a class="brand" href="login.php"><span class="brand-mark">LNU</span><span class="brand-copy"><strong>Leyte Normal University</strong><small>SCHOLARSHIP MANAGEMENT PORTAL</small></span></a>
  <a class="outline-btn" href="login.php">Sign In</a>
</header>
<main class="single-layout register-layout">
<section class="form-panel register-panel"><div class="form-accent"></div><div class="form-inner">
  <p class="kicker">GET STARTED</p><h2>Create your student account</h2><p class="muted">Complete the three steps, then verify your LNU email address.</p>
  <?php if ($errors): ?><div class="alert error" role="alert"><strong>Please check the following:</strong><ul><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
  <div class="register-progress" role="progressbar" aria-valuemin="1" aria-valuemax="3" aria-valuenow="1" aria-label="Registration progress, step 1 of 3"><span id="registerProgressBar"></span></div>
  <div class="register-steps" role="tablist" aria-label="Registration steps">
    <button class="register-step active" type="button" role="tab" aria-selected="true" aria-controls="register-step-1" data-step="1"><span class="step-number">1</span><span><strong>Account</strong><small>Email and password</small></span></button>
    <span class="step-connector" aria-hidden="true"></span>
    <button class="register-step" type="button" role="tab" aria-selected="false" aria-controls="register-step-2" data-step="2"><span class="step-number">2</span><span><strong>Personal Info</strong><small>Your contact details</small></span></button>
    <span class="step-connector" aria-hidden="true"></span>
    <button class="register-step" type="button" role="tab" aria-selected="false" aria-controls="register-step-3" data-step="3"><span class="step-number">3</span><span><strong>Academic Info</strong><small>Program selection</small></span></button>
  </div>
  <form method="post" action="register.php" id="registerForm" novalidate>
    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
    <section class="register-step-panel active" id="register-step-1" role="tabpanel" aria-label="Account">
      <div class="register-field"><label for="email">LNU email address</label><input id="email" name="email" type="email" autocomplete="email" placeholder="juan@lnu.edu.ph" value="<?= e($values['email']) ?>" required maxlength="150"><small class="field-hint" id="emailHint" aria-live="polite">Use your LNU email: juan@lnu.edu.ph</small></div>
      <div class="register-field"><label for="password">Create password</label><div class="password-wrap"><input id="password" name="password" type="password" autocomplete="new-password" placeholder="At least 8 characters" required><button type="button" class="toggle-password" data-toggle="password" aria-label="Show password"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M2 12s3.6-6 10-6 10 6 10 6-3.6 6-10 6S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/></svg></button></div></div>
      <div class="password-strength" aria-label="Password strength"><span></span><span></span><span></span><span></span></div>
      <div class="password-rules" id="passwordRules"><span data-rule="length">○ At least 8 characters</span><span data-rule="uppercase">○ Uppercase letter</span><span data-rule="number">○ Number</span><span data-rule="symbol">○ Symbol</span></div>
      <div class="register-field"><label for="confirm_password">Confirm password</label><div class="password-wrap"><input id="confirm_password" name="confirm_password" type="password" autocomplete="new-password" placeholder="Re-enter password" required><button type="button" class="toggle-password" data-toggle="confirm_password" aria-label="Show password"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M2 12s3.6-6 10-6 10 6 10 6-3.6 6-10 6S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/></svg></button></div></div>
      <div class="register-actions"><span class="step-hint">Step 1 of 3</span><button class="primary-btn" type="button" id="registerNext">Continue <span aria-hidden="true">→</span></button></div>
    </section>
    <section class="register-step-panel" id="register-step-2" role="tabpanel" aria-label="Personal information" hidden>
      <div class="register-fields two-col">
        <div class="register-field"><label for="first_name">First name</label><input id="first_name" name="first_name" autocomplete="given-name" value="<?= e($values['first_name']) ?>" required maxlength="80"></div>
        <div class="register-field"><label for="middle_name">Middle name <span class="optional">(optional)</span></label><input id="middle_name" name="middle_name" autocomplete="additional-name" value="<?= e($values['middle_name']) ?>" maxlength="80"></div>
        <div class="register-field"><label for="last_name">Last name</label><input id="last_name" name="last_name" autocomplete="family-name" value="<?= e($values['last_name']) ?>" required maxlength="80"></div>
        <div class="register-field"><label for="student_number">Student ID</label><input id="student_number" name="student_number" inputmode="numeric" autocomplete="off" placeholder="7 digits" value="<?= e($values['student_number']) ?>" required maxlength="7" pattern="\d{7}" title="Enter exactly 7 digits"></div>
        <div class="register-field"><label for="mobile_number">Phone number</label><input id="mobile_number" name="mobile_number" type="tel" inputmode="numeric" autocomplete="tel" placeholder="09XXXXXXXXX" value="<?= e($values['mobile_number']) ?>" required maxlength="11" pattern="09\d{9}" title="Enter 11 digits starting with 09"></div>
      </div>
      <div class="register-actions"><button class="secondary-btn" type="button" data-previous>← Back</button><span class="step-hint">Step 2 of 3</span><button class="primary-btn" type="button" data-next>Continue <span aria-hidden="true">→</span></button></div>
    </section>
    <section class="register-step-panel" id="register-step-3" role="tabpanel" aria-label="Academic information" hidden>
      <div class="register-fields two-col">
        <div class="register-field"><label for="year_level">Year level</label><select id="year_level" name="year_level" required><option value="">Choose year</option><?php for ($year = 1; $year <= 5; $year++): ?><option value="<?= $year ?>" <?= $values['year_level'] === (string)$year ? 'selected' : '' ?>>Year <?= $year ?></option><?php endfor; ?></select></div>
        <div class="register-field"><label for="college_code">College</label><select id="college_code" name="college_code" required><option value="">Choose college</option><?php foreach ($catalog as $code => $college): ?><option value="<?= e($code) ?>" <?= $values['college_code'] === $code ? 'selected' : '' ?>><?= e($college['name']) ?></option><?php endforeach; ?></select></div>
        <div class="register-field"><label for="program_code">Program</label><select id="program_code" name="program_code" data-selected="<?= e($values['program_code']) ?>" required disabled><option value="">Choose program</option></select></div>
        <div class="register-field" id="specializationField" hidden><label for="specialization">Specialization / major</label><select id="specialization" name="specialization" data-selected="<?= e($values['specialization']) ?>" disabled><option value="">Choose specialization</option></select></div>
      </div>
      <p class="field-hint certificate-note" id="certificateNote" hidden>Teacher Certificate Program year-level and scholarship eligibility rules are pending Scholarship Office confirmation.</p>
      <label class="check-row terms-row"><input type="checkbox" id="agree" name="agree" value="1" required><span>I confirm the information is accurate and agree to the portal's account terms.</span></label>
      <div class="register-actions"><button class="secondary-btn" type="button" data-previous>← Back</button><span class="step-hint">Step 3 of 3</span><button class="primary-btn" type="submit">Create Account <span aria-hidden="true">→</span></button></div>
    </section>
  </form>
  <p class="form-foot">Already registered? <a href="login.php">Sign in instead</a></p>
  <div class="security-note"><span class="lock-icon">✓</span><span><strong>Account details are stored securely</strong><br>Your password is hashed and program choices are validated by the server.</span></div>
</div></section>
</main>
<footer class="site-footer"><span>© <?= date('Y') ?> Leyte Normal University</span><span>Scholarship Management Portal</span><a href="mailto:scholarships@lnu.edu.ph">Need help?</a></footer>
<script>window.LNU_ACADEMIC_CATALOG = <?= json_encode($catalog, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;</script>
<script src="js/auth.js"></script>
<script src="js/register.js"></script>
</body></html>
