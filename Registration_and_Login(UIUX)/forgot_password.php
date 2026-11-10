<?php
declare(strict_types=1);
require_once __DIR__ . '/config.php';
if (current_user()) redirect(dashboard_path_for_role((string)current_user()['role']));

$message = '';
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $email = strtolower(trim((string)($_POST['email'] ?? '')));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 150) {
        $error = 'Enter a valid email address.';
    } else {
        try {
            $pdo = db();
            $query = $pdo->prepare("SELECT user_id FROM users WHERE email = ? AND account_status = 'active' LIMIT 1");
            $query->execute([$email]);
            $userId = $query->fetchColumn();
            $_SESSION['otp_context_email'] = $email;
            $_SESSION['otp_context_purpose'] = 'forgot_password';
            unset($_SESSION['dev_otp']);
            if ($userId !== false) {
                $_SESSION['otp_context_user_id'] = (int)$userId;
                $otp = issue_auth_otp($pdo, (int)$userId, $email, 'forgot_password');
                if ($otp['code'] !== null) {
                    $_SESSION['otp_resend_after'] = time() + 60;
                    $_SESSION['dev_otp'] = APP_ENV === 'local' && !$otp['sent'] ? $otp['code'] : null;
                } else {
                    $_SESSION['otp_resend_after'] = time() + (int)$otp['wait'];
                }
            } else {
                unset($_SESSION['otp_context_user_id']);
            }
            // Same response and destination whether or not an active account exists.
            $_SESSION['forgot_password_notice'] = 'If an active account matches that email, a verification code has been sent.';
            redirect('verify_otp.php?purpose=forgot_password');
        } catch (Throwable $exception) {
            error_log('LNU password recovery OTP error: ' . $exception->getMessage());
            $error = 'Password recovery is temporarily unavailable. Please try again later or contact the scholarship office.';
        }
    }
}
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Password Recovery | LNU Scholarship Portal</title><link rel="stylesheet" href="css/style.css"></head>
<body class="auth-page"><header class="topbar"><a class="brand" href="login.php"><span class="brand-mark">LNU</span><span class="brand-copy"><strong>Leyte Normal University</strong><small>SCHOLARSHIP MANAGEMENT PORTAL</small></span></a><a class="outline-btn" href="login.php">Back to Sign In</a></header>
<main class="single-layout"><section class="form-panel recovery-panel"><div class="form-accent"></div><div class="form-inner"><div class="recovery-symbol">↻</div><p class="kicker">ACCOUNT RECOVERY</p><h2>Forgot your password?</h2><p class="muted">Enter the email address associated with your account. If it matches an active account, we’ll send a one-time verification code.</p>
<?php if ($error): ?><div class="alert error" role="alert"><?= e($error) ?></div><?php endif; ?>
<form method="post" action="forgot_password.php"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><label for="email">Email address</label><input id="email" name="email" type="email" autocomplete="email" placeholder="yourname@lnu.edu.ph" required maxlength="150"><button class="primary-btn full" type="submit">Send verification code →</button></form>
<p class="form-foot"><a href="login.php">← Return to sign in</a></p><div class="security-note"><span class="lock-icon">i</span><span><strong>Codes expire in 10 minutes</strong><br>After five incorrect attempts, verification is locked for 15 minutes.</span></div></div></section></main>
<footer class="site-footer"><span>© <?= date('Y') ?> Leyte Normal University</span><span>Scholarship Management Portal</span><a href="mailto:scholarships@lnu.edu.ph">Need help?</a></footer><script src="js/auth.js"></script></body></html>
