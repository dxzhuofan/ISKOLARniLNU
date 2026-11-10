<?php
declare(strict_types=1);
require_once __DIR__ . '/config.php';
if (current_user()) {
    redirect(dashboard_path_for_role((string)current_user()['role']));
}

$error = '';
$activeRole = (($_POST['role'] ?? $_GET['role'] ?? 'student') === 'admin') ? 'admin' : 'student';
$email = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $email = strtolower(trim((string)($_POST['email'] ?? '')));
    $password = (string)($_POST['password'] ?? '');
    $activeRole = (($_POST['role'] ?? 'student') === 'admin') ? 'admin' : 'student';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 150
        || !preg_match('/@lnu\.edu\.ph$/i', $email) || $password === '') {
        $error = 'Enter a valid email address ending in @lnu.edu.ph and your password.';
    } else {
        try {
            $pdo = db();
            $query = $pdo->prepare(
                'SELECT DISTINCT u.user_id, u.role_id, u.email, u.password_hash, u.account_status,
                        u.failed_login_count, u.locked_until, u.must_change_password, r.role_name
                 FROM users u JOIN roles r ON r.role_id = u.role_id
                 WHERE u.email = :email LIMIT 1'
            );
            $query->execute(['email' => $email]);
            $account = $query->fetch();
            $validRole = $activeRole === 'admin'
                ? in_array($account['role_name'] ?? '', ['administrator', 'scholarship_officer', 'reviewer'], true)
                : (($account['role_name'] ?? '') === 'applicant');
            if (!$account || !password_verify($password, $account['password_hash'])) {
                if ($account) {
                    $pdo->prepare(
                        'UPDATE users SET failed_login_count = LEAST(failed_login_count + 1, 255),
                         locked_until = IF(failed_login_count + 1 >= 5, DATE_ADD(NOW(), INTERVAL 15 MINUTE), locked_until)
                         WHERE user_id = ?'
                    )->execute([$account['user_id']]);
                }
                $pdo->prepare(
                    'INSERT INTO login_attempts (user_id, email_attempted, ip_address, user_agent, was_successful)
                     VALUES (?, ?, ?, ?, 0)'
                )->execute([
                    $account['user_id'] ?? null, $email, $_SERVER['REMOTE_ADDR'] ?? null,
                    substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),
                ]);
                $error = 'The email or password is incorrect.';
            } elseif (!$validRole) {
                $error = 'This account does not match the selected role tab.';
            } elseif ($account['account_status'] !== 'active') {
                $error = 'This account is not active. Contact the scholarship office or system administrator.';
            } elseif (!empty($account['locked_until']) && strtotime($account['locked_until']) > time()) {
                $error = 'Too many failed attempts. Please try again after the temporary lock expires.';
            } else {
                $profileQuery = $pdo->prepare(
                    $account['role_name'] === 'applicant'
                        ? 'SELECT first_name, middle_name, last_name FROM applicants WHERE user_id = ? LIMIT 1'
                        : 'SELECT first_name, middle_name, last_name FROM staff_profiles WHERE user_id = ? LIMIT 1'
                );
                $profileQuery->execute([$account['user_id']]);
                $profile = $profileQuery->fetch();
                $name = $profile
                    ? trim($profile['first_name'] . ' ' . ($profile['middle_name'] ?? '') . ' ' . $profile['last_name'])
                    : 'LNU User';

                $deviceToken = (string)($_COOKIE['lnu_trusted_device'] ?? '');
                $deviceHash = preg_match('/^[a-f0-9]{64}$/', $deviceToken)
                    ? hash('sha256', (int)$account['user_id'] . ':' . $deviceToken)
                    : '';
                $knownDevice = false;
                if ($deviceHash !== '') {
                    $deviceQuery = $pdo->prepare(
                        'SELECT device_id FROM trusted_login_devices WHERE user_id = ? AND token_hash = ? LIMIT 1'
                    );
                    $deviceQuery->execute([$account['user_id'], $deviceHash]);
                    $deviceId = $deviceQuery->fetchColumn();
                    if ($deviceId !== false) {
                        $knownDevice = true;
                        $pdo->prepare('UPDATE trusted_login_devices SET last_used_at = NOW() WHERE device_id = ?')
                            ->execute([$deviceId]);
                    }
                }
                if (!$knownDevice) {
                    $deviceToken = bin2hex(random_bytes(32));
                    $deviceHash = hash('sha256', (int)$account['user_id'] . ':' . $deviceToken);
                    $pdo->prepare(
                        'INSERT INTO trusted_login_devices (user_id, token_hash, user_agent) VALUES (?, ?, ?)'
                    )->execute([
                        $account['user_id'], $deviceHash,
                        substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),
                    ]);
                    setcookie('lnu_trusted_device', $deviceToken, [
                        'expires' => time() + 31536000,
                        'path' => '/',
                        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
                        'httponly' => true,
                        'samesite' => 'Lax',
                    ]);
                    if ($account['role_name'] === 'applicant') {
                        send_portal_email(
                            (string)$account['email'],
                            'New device signed in to your LNU Scholarship Portal account',
                            "A new device signed in to your account on " . date('Y-m-d H:i:s') . ".\n"
                            . "If this was not you, change your password immediately and contact the Scholarship Office.\n"
                        );
                    }
                }

                session_regenerate_id(true);
                $_SESSION['user'] = [
                    'id' => (int)$account['user_id'],
                    'name' => $name,
                    'email' => $account['email'],
                    'role' => $account['role_name'],
                    'must_change_password' => (bool)$account['must_change_password'],
                ];
                $_SESSION['user_id'] = (int)$account['user_id'];
                $_SESSION['role_id'] = (int)$account['role_id'];
                $pdo->prepare('UPDATE users SET failed_login_count = 0, locked_until = NULL, last_login_at = NOW() WHERE user_id = ?')
                    ->execute([$account['user_id']]);
                $pdo->prepare(
                    'INSERT INTO login_attempts (user_id, email_attempted, ip_address, user_agent, was_successful)
                     VALUES (?, ?, ?, ?, 1)'
                )->execute([
                    $account['user_id'], $email, $_SERVER['REMOTE_ADDR'] ?? null,
                    substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),
                ]);
                redirect(dashboard_path_for_role((string)$account['role_name']));
            }
        } catch (PDOException $exception) {
            error_log('LNU login database error: ' . $exception->getMessage());
            $error = 'Database connection or query failed. Check config.php and confirm your database is lnu_scholarship_db.';
        }
    }
}
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Sign In | LNU Scholarship Portal</title><link rel="stylesheet" href="css/style.css"></head><body class="auth-page">
<header class="topbar"><a class="brand" href="login.php"><span class="brand-mark">LNU</span><span class="brand-copy"><strong>Leyte Normal University</strong><small>SCHOLARSHIP MANAGEMENT PORTAL</small></span></a><a class="outline-btn" href="register.php">Create Account</a></header>
<main class="auth-layout"><section class="intro-panel"><span class="eyebrow"><i></i> AY 2026–2027 Applications Now Open</span><h1 class="animated-hero-title" aria-label="Fund Your Education."><span class="title-line title-line-one">Fund Your</span><span class="title-line title-line-two">Education<span class="title-period">.</span></span></h1><p class="intro-copy">The LNU Scholarship Portal lets you browse programs, apply online, upload documents with ML-powered OCR verification, and monitor your application status — all in one place.</p><div class="stats"><div><b class="stat-icon">◆</b><strong>6</strong><span>Scholarship<br>Programs</span></div><div><b class="stat-icon">♟</b><strong>215</strong><span>Available<br>Slots</span></div><div><b class="stat-icon">▥</b><strong>₱40K</strong><span>Max Grant /<br>Sem</span></div><div><b class="stat-icon">✓</b><strong>94%</strong><span>OCR<br>Accuracy</span></div></div><div class="intro-note">A clearer path to educational support.</div><div class="student-illustration" aria-hidden="true"><img class="student-art" src="images/lnu-student-illustration.png" alt=""></div></section>
<section class="form-panel"><div class="form-accent"></div><div class="form-inner"><h2 id="form-title"><?= $activeRole === 'admin' ? 'Staff' : 'Student' ?> Sign In</h2><p class="muted">Access your LNU Scholarship Portal account.</p>
<div class="role-tabs" data-active="<?= e($activeRole) ?>" role="tablist" aria-label="Account type"><button type="button" class="role-tab <?= $activeRole === 'student' ? 'active' : '' ?>" data-role="student" role="tab" aria-selected="<?= $activeRole === 'student' ? 'true' : 'false' ?>">♧ &nbsp; Student</button><button type="button" class="role-tab <?= $activeRole === 'admin' ? 'active' : '' ?>" data-role="admin" role="tab" aria-selected="<?= $activeRole === 'admin' ? 'true' : 'false' ?>">♙ &nbsp; Staff</button></div>
<?php if ($error): ?><div class="alert error" role="alert"><?= e($error) ?></div><?php endif; ?><?php if (isset($_GET['registered'])): ?><div class="alert success">Your student account was verified and created. You can sign in now.</div><?php endif; ?>
<form method="post" action="login.php" id="loginForm" novalidate><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="role" id="roleInput" value="<?= e($activeRole) ?>"><label for="email">LNU email address</label><input id="email" name="email" type="email" autocomplete="username" placeholder="yourname@lnu.edu.ph" value="<?= e($email) ?>" required maxlength="150"><div class="field-heading"><label for="password">Password</label><a href="forgot_password.php">Forgot password?</a></div><div class="password-wrap"><input id="password" name="password" type="password" autocomplete="current-password" placeholder="Enter your password" required><button type="button" class="toggle-password" data-toggle="password" aria-label="Show password"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M2 12s3.6-6 10-6 10 6 10 6-3.6 6-10 6S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/></svg></button></div><label class="check-row"><input type="checkbox" name="remember" value="1"><span>Keep me signed in</span></label><button class="primary-btn full" type="submit" formnovalidate>Sign In to Portal <span aria-hidden="true">↪</span></button></form>
<p class="form-foot">New to the portal? <a href="register.php">Create a student account</a></p><div class="security-note"><span class="lock-icon">✓</span><span><strong>Secure access</strong><br>Staff access is restricted to authorized accounts and assigned roles.</span></div></div></section></main>
<footer class="site-footer"><span>© <?= date('Y') ?> Leyte Normal University</span><span>Scholarship Management Portal</span><a href="mailto:scholarships@lnu.edu.ph">Need help?</a></footer><script src="js/auth.js"></script></body></html>
