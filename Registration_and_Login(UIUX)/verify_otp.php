<?php
declare(strict_types=1);
require_once __DIR__ . '/config.php';

$purpose = (string)($_GET['purpose'] ?? $_POST['purpose'] ?? $_SESSION['otp_context_purpose'] ?? '');
$allowedPurposes = ['registration', 'forgot_password', 'password_change'];
$errors = [];
$notice = (string)($_SESSION['forgot_password_notice'] ?? '');
unset($_SESSION['forgot_password_notice']);
$deliveryError = (bool)($_SESSION['otp_delivery_error'] ?? false);
unset($_SESSION['otp_delivery_error']);
$devCode = (string)($_SESSION['dev_otp'] ?? '');
$contextEmail = '';
$validContext = in_array($purpose, $allowedPurposes, true);

if ($purpose === 'registration') {
    $pending = $_SESSION['pending_registration'] ?? null;
    $validContext = is_array($pending) && ($pending['expires_at'] ?? 0) > time();
    $contextEmail = $validContext ? (string)$pending['email'] : '';
} elseif ($purpose === 'forgot_password') {
    $contextEmail = (string)($_SESSION['otp_context_email'] ?? '');
    $validContext = $validContext && $contextEmail !== '';
} elseif ($purpose === 'password_change') {
    $user = current_user();
    $contextEmail = (string)($_SESSION['otp_context_email'] ?? '');
    $validContext = $validContext && $user && isset($_SESSION['pending_password_hash'])
        && (int)($_SESSION['pending_password_expires_at'] ?? 0) > time()
        && (int)($_SESSION['otp_context_user_id'] ?? 0) === (int)$user['id'];
}

if (!$validContext && $purpose === 'registration') {
    unset($_SESSION['pending_registration'], $_SESSION['dev_otp'], $_SESSION['otp_context_email'], $_SESSION['otp_context_purpose']);
    $devCode = '';
    $deliveryError = false;
}
if (!$validContext && $purpose === 'password_change') {
    unset($_SESSION['pending_password_hash'], $_SESSION['pending_password_expires_at'], $_SESSION['dev_otp'], $_SESSION['otp_context_email'], $_SESSION['otp_context_purpose'], $_SESSION['otp_context_user_id']);
    $devCode = '';
    $deliveryError = false;
}

if (!$validContext) {
    $errors[] = 'This verification request is no longer valid. Start again from the account page.';
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    try {
        $pdo = db();
        if (($_POST['action'] ?? 'verify') === 'resend') {
            $userId = (int)($_SESSION['otp_context_user_id'] ?? 0) ?: null;
            if ($purpose === 'forgot_password' && $userId === null) {
                $sent = ['wait' => 60, 'code' => null, 'sent' => false];
            } else {
                $sent = issue_auth_otp(
                    $pdo,
                    $purpose === 'registration' ? null : $userId,
                    $contextEmail,
                    $purpose
                );
            }
            if ($sent['wait'] > 0 && $sent['code'] === null) {
                $_SESSION['otp_resend_after'] = time() + (int)$sent['wait'];
                $errors[] = "Please wait {$sent['wait']} seconds before requesting another code.";
            } else {
                $_SESSION['otp_resend_after'] = time() + 60;
                $_SESSION['dev_otp'] = APP_ENV === 'local' && !$sent['sent'] ? $sent['code'] : null;
                $_SESSION['otp_delivery_error'] = !$sent['sent'] && APP_ENV !== 'local' && $purpose !== 'forgot_password';
                $deliveryError = (bool)$_SESSION['otp_delivery_error'];
                $notice = 'If the address is eligible, a new code has been sent. Codes expire in 10 minutes.';
                $devCode = (string)($_SESSION['dev_otp'] ?? '');
            }
        } else {
            $code = trim((string)($_POST['code'] ?? ''));
            if (!preg_match('/^\d{6}$/', $code)) {
                $errors[] = 'Enter the 6-digit verification code.';
            } else {
                $pdo->beginTransaction();
                $lookup = $pdo->prepare(
                    'SELECT otp_id, user_id, otp_hash, attempts, locked_until, expires_at
                     FROM auth_otps
                     WHERE email = ? AND purpose = ? AND used_at IS NULL
                     ORDER BY otp_id DESC LIMIT 1 FOR UPDATE'
                );
                $lookup->execute([$contextEmail, $purpose]);
                $otp = $lookup->fetch();
                $expectedUserId = $purpose === 'registration' ? null : (int)($_SESSION['otp_context_user_id'] ?? 0);
                $userMismatch = $purpose !== 'registration'
                    && (!$otp || !$expectedUserId || (int)$otp['user_id'] !== $expectedUserId);
                if (!$otp || strtotime($otp['expires_at']) <= time() || $userMismatch) {
                    $errors[] = 'That code is invalid or expired. Request a new code.';
                    $pdo->rollBack();
                } elseif (!empty($otp['locked_until']) && strtotime($otp['locked_until']) > time()) {
                    $remaining = (int)ceil((strtotime($otp['locked_until']) - time()) / 60);
                    $errors[] = "Too many incorrect codes. Try again in about {$remaining} minute(s).";
                    $pdo->rollBack();
                } else {
                    if (!empty($otp['locked_until'])) {
                        $pdo->prepare('UPDATE auth_otps SET attempts = 0, locked_until = NULL WHERE otp_id = ?')
                            ->execute([$otp['otp_id']]);
                    }
                    if (!password_verify($code, $otp['otp_hash'])) {
                        $attempts = (int)$otp['attempts'] + 1;
                        if (!empty($otp['locked_until'])) $attempts = 1;
                        $pdo->prepare(
                            'UPDATE auth_otps SET attempts = ?, locked_until = IF(? >= 5, DATE_ADD(NOW(), INTERVAL 15 MINUTE), NULL) WHERE otp_id = ?'
                        )->execute([$attempts, $attempts, $otp['otp_id']]);
                        $pdo->commit();
                        $errors[] = $attempts >= 5
                            ? 'Too many incorrect codes. Verification is locked for 15 minutes.'
                            : 'That code is incorrect. Check the code and try again.';
                    } else {
                        $pdo->prepare('UPDATE auth_otps SET used_at = NOW() WHERE otp_id = ?')->execute([$otp['otp_id']]);
                        if ($purpose === 'registration') {
                            $pending = $_SESSION['pending_registration'];
                            $registrationLock = 'lnu_student_' . substr(hash('sha256', $pending['student_number']), 0, 48);
                            $lockQuery = $pdo->prepare('SELECT GET_LOCK(?, 5)');
                            $lockQuery->execute([$registrationLock]);
                            if ((int)$lockQuery->fetchColumn() !== 1) {
                                throw new RuntimeException('Could not safely finish registration. Please try again.');
                            }
                            try {
                                $duplicate = $pdo->prepare(
                                    'SELECT 1 FROM applicants a JOIN users u ON u.user_id = a.user_id
                                     WHERE a.student_number = ? OR u.email = ? LIMIT 1'
                                );
                                $duplicate->execute([$pending['student_number'], $pending['email']]);
                                if ($duplicate->fetchColumn()) {
                                    throw new RuntimeException('That email or student ID is already registered.');
                                }
                                $roleQuery = $pdo->prepare("SELECT role_id FROM roles WHERE role_name = 'applicant' LIMIT 1");
                                $roleQuery->execute();
                                $roleId = $roleQuery->fetchColumn();
                                if ($roleId === false) throw new RuntimeException('The applicant role is missing from the roles table.');
                                $pdo->prepare(
                                    "INSERT INTO users (role_id, email, password_hash, account_status, email_verified_at)
                                     VALUES (?, ?, ?, 'active', NOW())"
                                )->execute([$roleId, $pending['email'], $pending['password_hash']]);
                                $userId = (int)$pdo->lastInsertId();
                                $pdo->prepare(
                                    'INSERT INTO applicants (user_id, student_number, first_name, middle_name, last_name, mobile_number)
                                     VALUES (?, ?, ?, ?, ?, ?)'
                                )->execute([
                                    $userId, $pending['student_number'], $pending['first_name'],
                                    $pending['middle_name'] !== '' ? $pending['middle_name'] : null,
                                    $pending['last_name'], $pending['mobile_number'],
                                ]);
                                $applicantId = (int)$pdo->lastInsertId();
                                $pdo->prepare(
                                    'INSERT INTO applicant_academic_profiles
                                     (applicant_id, year_level, college_code, program_code, program_type, specialization)
                                     VALUES (?, ?, ?, ?, ?, ?)'
                                )->execute([
                                    $applicantId, $pending['year_level'], $pending['college_code'],
                                    $pending['program_code'], $pending['program_type'],
                                    $pending['specialization'] !== '' ? $pending['specialization'] : null,
                                ]);
                            } finally {
                                $releaseLock = $pdo->prepare('SELECT RELEASE_LOCK(?)');
                                $releaseLock->execute([$registrationLock]);
                            }
                            $pdo->commit();
                            unset($_SESSION['pending_registration']);
                            unset($_SESSION['otp_context_email'], $_SESSION['otp_context_purpose'], $_SESSION['otp_resend_after'], $_SESSION['dev_otp'], $_SESSION['otp_delivery_error']);
                            redirect('login.php?registered=1');
                        }
                        if ($purpose === 'forgot_password') {
                            $_SESSION['password_reset_user_id'] = (int)$otp['user_id'];
                            $_SESSION['password_reset_expires_at'] = time() + 600;
                            $pdo->commit();
                            unset($_SESSION['otp_context_email'], $_SESSION['otp_context_purpose'], $_SESSION['otp_context_user_id'], $_SESSION['otp_resend_after'], $_SESSION['dev_otp'], $_SESSION['otp_delivery_error']);
                            redirect('reset_password.php');
                        }

                        $userId = (int)$_SESSION['otp_context_user_id'];
                        $passwordHash = (string)$_SESSION['pending_password_hash'];
                        $pdo->prepare(
                            'UPDATE users SET password_hash = ?, failed_login_count = 0, locked_until = NULL,
                             must_change_password = 0, updated_at = NOW()
                             WHERE user_id = ?'
                        )->execute([$passwordHash, $userId]);
                        $pdo->commit();
                        $emailSent = send_password_change_notice($contextEmail);
                        unset($_SESSION['pending_password_hash'], $_SESSION['pending_password_expires_at'], $_SESSION['otp_context_email'], $_SESSION['otp_context_purpose'], $_SESSION['otp_context_user_id'], $_SESSION['otp_resend_after'], $_SESSION['dev_otp'], $_SESSION['otp_delivery_error']);
                        flash($emailSent ? 'success' : 'error', $emailSent
                            ? 'Password changed and a security notification was sent to your email.'
                            : 'Password changed, but the security email could not be delivered. Contact the Scholarship Office if you did not make this change.');
                        redirect('../STUDENT_DASHBOARD/account.php');
                    }
                }
            }
        }
    } catch (RuntimeException $error) {
        if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
        error_log('LNU OTP workflow error: ' . $error->getMessage());
        $errors[] = $error->getMessage();
    } catch (Throwable $error) {
        if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
        error_log('LNU OTP verification error: ' . $error->getMessage());
        $errors[] = 'Verification could not be completed. Please try again later.';
    }
}

$resendWait = max(0, (int)($_SESSION['otp_resend_after'] ?? 0) - time());
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Verify Email | LNU Scholarship Portal</title><link rel="stylesheet" href="css/style.css"></head>
<body class="auth-page"><header class="topbar"><a class="brand" href="login.php"><span class="brand-mark">LNU</span><span class="brand-copy"><strong>Leyte Normal University</strong><small>SCHOLARSHIP MANAGEMENT PORTAL</small></span></a><a class="outline-btn" href="login.php">Sign In</a></header>
<main class="single-layout"><section class="form-panel recovery-panel"><div class="form-accent"></div><div class="form-inner"><p class="kicker">EMAIL VERIFICATION</p><h2>Enter your verification code</h2><p class="muted">Enter the 6-digit code sent to <?= e($contextEmail) ?>. Codes expire in 10 minutes and can be used once.</p>
<?php foreach ($errors as $error): ?><div class="alert error" role="alert"><?= e($error) ?></div><?php endforeach; ?>
<?php if ($notice): ?><div class="alert success" role="status"><?= e($notice) ?></div><?php endif; ?>
<?php if ($deliveryError): ?><div class="alert error" role="alert">Email delivery failed. Check the mail server configuration or try resending the code after the countdown.</div><?php endif; ?>
<?php if ($devCode !== ''): ?><div class="alert success"><strong>Local development code:</strong> <?= e($devCode) ?><br>Configure PHP mail before production deployment.</div><?php endif; ?>
<?php if ($validContext): ?><form method="post" id="otpForm" novalidate><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="purpose" value="<?= e($purpose) ?>"><label for="code">6-digit code</label><input id="code" name="code" inputmode="numeric" autocomplete="one-time-code" maxlength="6" pattern="\d{6}" required autofocus><button class="primary-btn full" type="submit">Verify code →</button></form>
<form method="post" class="resend-form"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="purpose" value="<?= e($purpose) ?>"><input type="hidden" name="action" value="resend"><button class="secondary-btn" id="resendButton" type="submit" data-wait="<?= $resendWait ?>">Resend code</button></form>
<?php endif; ?><p class="form-foot"><a href="<?= $purpose === 'forgot_password' ? 'forgot_password.php' : ($purpose === 'password_change' ? '../STUDENT_DASHBOARD/account.php' : 'register.php') ?>">← Go back</a></p></div></section></main>
<footer class="site-footer"><span>© <?= date('Y') ?> Leyte Normal University</span><span>Scholarship Management Portal</span></footer><script src="js/auth.js"></script><script src="js/otp.js"></script></body></html>
