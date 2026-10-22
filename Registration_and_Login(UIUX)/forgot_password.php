<?php
declare(strict_types=1);
require_once __DIR__ . '/config.php';
if (current_user()) redirect(current_user()['role'] === 'administrator' ? '../ADMIN_DASHBOARD/dashboard.php' : '../STUDENT_DASHBOARD/dashboard.php');
$message = '';
$devResetUrl = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $email = strtolower(trim((string)($_POST['email'] ?? '')));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 150) {
        $message = 'Enter a valid email address.';
    } else {
        try {
            $pdo = db();
            $stmt = $pdo->prepare("SELECT user_id, email FROM users WHERE email = ? AND account_status = 'active' LIMIT 1");
            $stmt->execute([$email]);
            $user = $stmt->fetch();
            if ($user) {
                $rawToken = bin2hex(random_bytes(32));
                $tokenHash = hash('sha256', $rawToken);
                $pdo->prepare('UPDATE password_resets SET used_at = NOW() WHERE user_id = ? AND used_at IS NULL')->execute([$user['user_id']]);
                $pdo->prepare('INSERT INTO password_resets (user_id, token_hash, expires_at, ip_address) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 30 MINUTE), ?)')
                    ->execute([$user['user_id'], $tokenHash, $_SERVER['REMOTE_ADDR'] ?? null]);
                $baseUrl = rtrim(APP_BASE_URL, '/');
                $url = $baseUrl . '/reset_password.php?token=' . urlencode($rawToken);
                $subject = 'Reset your LNU Scholarship Portal password';
                $body = "Hello,\n\nA password reset was requested for your LNU Scholarship Portal account. Use the link below within 30 minutes:\n\n" . $url . "\n\nIf you did not request this, you can ignore this message.\n";
                $headers = 'From: ' . MAIL_FROM . "\r\n" . 'Content-Type: text/plain; charset=UTF-8';
                $sent = @mail($user['email'], $subject, $body, $headers);
                if (!$sent && APP_ENV === 'local') $devResetUrl = $url;
            }
            // Do not disclose whether an email exists in the database.
            $message = 'If an active account matches that email, a password reset link has been generated. Check your inbox or spam folder.';
        } catch (Throwable $e) {
            error_log('LNU password recovery error: ' . $e->getMessage());
            $message = 'Password recovery is temporarily unavailable. Please try again later or contact the scholarship office.';
        }
    }
}
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Password Recovery | LNU Scholarship Portal</title><link rel="stylesheet" href="assets/css/style.css"></head><body class="auth-page"><header class="topbar"><a class="brand" href="login.php"><span class="brand-mark">LNU</span><span class="brand-copy"><strong>Leyte Normal University</strong><small>SCHOLARSHIP MANAGEMENT PORTAL</small></span></a><a class="outline-btn" href="login.php">Back to Sign In</a></header><main class="single-layout"><section class="form-panel recovery-panel"><div class="form-accent"></div><div class="form-inner"><div class="recovery-symbol">↻</div><p class="kicker">ACCOUNT RECOVERY</p><h2>Forgot your password?</h2><p class="muted">Enter the email address associated with your account. If it matches an active account, we’ll send a time-limited reset link.</p><?php if($message): ?><div class="alert <?= str_starts_with($message,'Enter') || str_starts_with($message,'Password recovery is')?'error':'success' ?>" role="status"><?= e($message) ?></div><?php endif; ?><?php if($devResetUrl): ?><div class="alert success"><strong>Local development reset link</strong><br><a href="<?= e($devResetUrl) ?>">Reset password</a><p class="muted">This link is shown only when APP_ENV is set to local. Configure PHP mail for production.</p></div><?php endif; ?><form method="post" action="forgot_password.php" novalidate><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><label for="email">Email address</label><input id="email" name="email" type="email" autocomplete="email" placeholder="yourname@lnu.edu.ph" required maxlength="150"><button class="primary-btn full" type="submit">Send Reset Link <span aria-hidden="true">→</span></button></form><p class="form-foot"><a href="login.php">← Return to sign in</a></p><div class="security-note"><span class="lock-icon">i</span><span><strong>Reset links expire in 30 minutes</strong><br>For help, contact the LNU scholarship office or system administrator.</span></div></div></section></main><footer class="site-footer"><span>© <?= date('Y') ?> Leyte Normal University</span><span>Scholarship Management Portal</span><a href="mailto:scholarships@lnu.edu.ph">Need help?</a></footer><script src="assets/js/auth.js"></script></body></html>
