<?php
declare(strict_types=1);
require_once __DIR__ . '/config.php';
if (current_user()) redirect(current_user()['role'] === 'administrator' ? '../ADMIN_DASHBOARD/dashboard.php' : '../STUDENT_DASHBOARD/dashboard.php');
$token = trim((string)($_GET['token'] ?? $_POST['token'] ?? ''));
$error = '';
$success = false;
$validToken = false;
if (preg_match('/^[a-f0-9]{64}$/', $token)) {
    try {
        $stmt = db()->prepare('SELECT reset_id, user_id FROM password_resets WHERE token_hash = ? AND used_at IS NULL AND expires_at > NOW() ORDER BY reset_id DESC LIMIT 1');
        $stmt->execute([hash('sha256', $token)]);
        $validToken = (bool)$stmt->fetch();
    } catch (Throwable $e) { error_log('LNU reset-token lookup error: ' . $e->getMessage()); }
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $password = (string)($_POST['password'] ?? '');
    $confirm = (string)($_POST['confirm_password'] ?? '');
    if (!$validToken) $error = 'This reset link is invalid, expired, or already used. Request a new one.';
    elseif (strlen($password) < 8 || !preg_match('/[A-Za-z]/', $password) || !preg_match('/[0-9]/', $password)) $error = 'Use at least 8 characters, including at least one letter and one number.';
    elseif ($password !== $confirm) $error = 'The passwords do not match.';
    else {
        try {
            $pdo = db();
            $pdo->beginTransaction();
            $stmt = $pdo->prepare('SELECT reset_id, user_id FROM password_resets WHERE token_hash = ? AND used_at IS NULL AND expires_at > NOW() FOR UPDATE');
            $stmt->execute([hash('sha256', $token)]);
            $reset = $stmt->fetch();
            if (!$reset) throw new RuntimeException('expired');
            $pdo->prepare('UPDATE users SET password_hash = ?, failed_login_count = 0, locked_until = NULL, updated_at = NOW() WHERE user_id = ?')
                ->execute([password_hash($password, PASSWORD_DEFAULT), $reset['user_id']]);
            $pdo->prepare('UPDATE password_resets SET used_at = NOW() WHERE user_id = ? AND used_at IS NULL')->execute([$reset['user_id']]);
            $pdo->commit();
            $success = true;
            $validToken = false;
        } catch (Throwable $e) {
            if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
            $error = $e instanceof RuntimeException ? 'This reset link is invalid, expired, or already used. Request a new one.' : 'Could not reset the password right now. Please try again.';
            if (!($e instanceof RuntimeException)) error_log('LNU password reset error: ' . $e->getMessage());
        }
    }
}
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Set New Password | LNU Scholarship Portal</title><link rel="stylesheet" href="assets/css/style.css"></head><body class="auth-page"><header class="topbar"><a class="brand" href="login.php"><span class="brand-mark">LNU</span><span class="brand-copy"><strong>Leyte Normal University</strong><small>SCHOLARSHIP MANAGEMENT PORTAL</small></span></a><a class="outline-btn" href="login.php">Sign In</a></header><main class="single-layout"><section class="form-panel recovery-panel"><div class="form-accent"></div><div class="form-inner"><p class="kicker">SECURE YOUR ACCOUNT</p><h2>Set a new password</h2><?php if($success): ?><div class="alert success" role="status">Your password has been updated. You can now sign in with your new password.</div><a class="primary-btn full button-link" href="login.php">Return to Sign In →</a><?php else: ?><p class="muted">Choose a strong password you haven’t used for this account before.</p><?php if($error): ?><div class="alert error" role="alert"><?= e($error) ?></div><?php endif; ?><?php if($validToken): ?><form method="post" action="reset_password.php" id="resetForm" novalidate><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="token" value="<?= e($token) ?>"><label for="password">New password</label><div class="password-wrap"><input id="password" name="password" type="password" autocomplete="new-password" required><button type="button" class="toggle-password" data-toggle="password" aria-label="Show password"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M2 12s3.6-6 10-6 10 6 10 6-3.6 6-10 6S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/></svg></button></div><div class="password-rules" id="passwordRules"><span data-rule="length">○ At least 8 characters</span><span data-rule="letter">○ At least one letter</span><span data-rule="number">○ At least one number</span></div><label for="confirm_password">Confirm new password</label><div class="password-wrap"><input id="confirm_password" name="confirm_password" type="password" autocomplete="new-password" required><button type="button" class="toggle-password" data-toggle="confirm_password" aria-label="Show confirm password"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M2 12s3.6-6 10-6 10 6 10 6-3.6 6-10 6S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/></svg></button></div><button class="primary-btn full" type="submit">Update Password →</button></form><?php else: ?><p class="form-foot"><a href="forgot_password.php">Request a new reset link</a></p><?php endif; ?><?php endif; ?><p class="form-foot"><a href="login.php">← Return to sign in</a></p></div></section></main><footer class="site-footer"><span>© <?= date('Y') ?> Leyte Normal University</span><span>Scholarship Management Portal</span></footer><script src="assets/js/auth.js"></script></body></html>
