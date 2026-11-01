<?php
declare(strict_types=1);
require_once __DIR__ . '/config.php';
$user = current_user();
if (!$user || $user['role'] !== 'applicant') redirect('../STUDENT_DASHBOARD/account.php');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('../STUDENT_DASHBOARD/account.php');
if (!hash_equals((string)($_SESSION['csrf'] ?? ''), (string)($_POST['csrf'] ?? ''))) {
    http_response_code(419);
    exit('Your session expired. Refresh the page and try again.');
}

$currentPassword = (string)($_POST['current'] ?? '');
$newPassword = (string)($_POST['new'] ?? '');
$confirmPassword = (string)($_POST['confirm'] ?? '');
$pdo = db();
$query = $pdo->prepare(
    "SELECT u.email, u.password_hash FROM users u JOIN roles r ON r.role_id = u.role_id
     WHERE u.user_id = ? AND r.role_name = 'applicant' AND u.account_status = 'active' LIMIT 1"
);
$query->execute([(int)$user['id']]);
$account = $query->fetch();
$errors = [];
if (!$account || !password_verify($currentPassword, (string)$account['password_hash'])) $errors[] = 'Your current password is incorrect.';
if (strlen($newPassword) < 8 || !preg_match('/[A-Z]/', $newPassword)
    || !preg_match('/\d/', $newPassword) || !preg_match('/[^A-Za-z0-9]/', $newPassword)) {
    $errors[] = 'Use at least 8 characters, an uppercase letter, a number, and a symbol.';
}
if ($newPassword !== $confirmPassword) $errors[] = 'The new passwords do not match.';
if ($errors) {
    $_SESSION['flash'][] = ['error', implode(' ', $errors)];
    redirect('../STUDENT_DASHBOARD/account.php');
}

$_SESSION['pending_password_hash'] = password_hash($newPassword, PASSWORD_DEFAULT);
$_SESSION['pending_password_expires_at'] = time() + 600;
$_SESSION['otp_context_email'] = (string)$account['email'];
$_SESSION['otp_context_purpose'] = 'password_change';
$_SESSION['otp_context_user_id'] = (int)$user['id'];
try {
    $otp = issue_auth_otp($pdo, (int)$user['id'], (string)$account['email'], 'password_change');
    if ($otp['code'] === null) {
        unset($_SESSION['pending_password_hash'], $_SESSION['pending_password_expires_at']);
        $_SESSION['flash'][] = ['error', "Please wait {$otp['wait']} seconds before requesting another verification code."];
        redirect('../STUDENT_DASHBOARD/account.php');
    }
    $_SESSION['otp_resend_after'] = time() + 60;
    $_SESSION['dev_otp'] = APP_ENV === 'local' && !$otp['sent'] ? $otp['code'] : null;
    $_SESSION['otp_delivery_error'] = !$otp['sent'] && APP_ENV !== 'local';
    redirect('verify_otp.php?purpose=password_change');
} catch (Throwable $error) {
    unset($_SESSION['pending_password_hash'], $_SESSION['pending_password_expires_at']);
    error_log('LNU password-change OTP request error: ' . $error->getMessage());
    $_SESSION['flash'][] = ['error', 'Could not send a verification code. Please try again later.'];
    redirect('../STUDENT_DASHBOARD/account.php');
}
