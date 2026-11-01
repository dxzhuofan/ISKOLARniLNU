<?php
declare(strict_types=1);
date_default_timezone_set('Asia/Manila');
if (session_status() !== PHP_SESSION_ACTIVE) {
    ini_set('session.use_strict_mode', '1');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

/*
 * LNU Scholarship Portal authentication starter.
 * Update these values for your local MySQL environment.
 */
const DB_HOST = '127.0.0.1';
const DB_NAME = 'lnu_scholarship_db';
const DB_USER = 'root';
const DB_PASS = '';

// Set APP_ENV to 'production' on a live server. APP_BASE_URL must be the public
// URL of this project directory (without a trailing slash).
const APP_ENV = 'local';
const APP_BASE_URL = 'http://localhost/ISKOLARniLNU/Registration_and_Login(UIUX)';

function db(): PDO {
    static $pdo = null;
    if ($pdo instanceof PDO) return $pdo;
    $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    $pdo->exec("SET time_zone = '+08:00'");
    return $pdo;
}

function e(string $value): string {
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf_token'];
}
function verify_csrf(): void {
    $sent = $_POST['csrf_token'] ?? '';
    if (!is_string($sent) || !hash_equals(csrf_token(), $sent)) {
        http_response_code(419);
        exit('Your session expired. Refresh the page and try again.');
    }
}
function redirect(string $path): never {
    header('Location: ' . $path);
    exit;
}
function current_user(): ?array {
    $user = $_SESSION['user'] ?? null;
    if (!is_array($user)) return null;

    $userId = $_SESSION['user_id'] ?? $user['id'] ?? $user['user_id'] ?? null;
    if (!is_numeric($userId) || (int)$userId < 0) {
        unset($_SESSION['user'], $_SESSION['user_id'], $_SESSION['role_id']);
        return null;
    }

    $_SESSION['user_id'] = (int)$userId;
    return $user;
}
function require_login(?string $role = null): void {
    $user = current_user();
    if (!$user || ($role !== null && $user['role'] !== $role)) redirect('login.php');
}

function dashboard_path_for_role(string $role): string {
    if ($role === 'reviewer') return '../ADMIN_DASHBOARD/applications.php';
    if (in_array($role, ['administrator', 'scholarship_officer'], true)) return '../ADMIN_DASHBOARD/dashboard.php';
    return '../STUDENT_DASHBOARD/dashboard.php';
}

require_once __DIR__ . '/mail.php';

function issue_auth_otp(PDO $pdo, ?int $userId, string $email, string $purpose): array {
    $lockName = 'lnu_otp_' . substr(hash('sha256', $email . "\0" . $purpose), 0, 48);
    $lock = $pdo->prepare('SELECT GET_LOCK(?, 5)');
    $lock->execute([$lockName]);
    if ((int)$lock->fetchColumn() !== 1) throw new RuntimeException('Could not safely start verification. Please try again.');
    try {
        $latest = $pdo->prepare(
            'SELECT last_sent_at, locked_until FROM auth_otps
             WHERE email = ? AND purpose = ? ORDER BY otp_id DESC LIMIT 1'
        );
        $latest->execute([$email, $purpose]);
        $last = $latest->fetch();
        if ($last) {
            if (!empty($last['locked_until']) && strtotime($last['locked_until']) > time()) {
                return ['wait' => strtotime($last['locked_until']) - time(), 'code' => null, 'sent' => false];
            }
            $wait = 60 - (time() - strtotime((string)$last['last_sent_at']));
            if ($wait > 0) return ['wait' => $wait, 'code' => null, 'sent' => false];
        }

        $code = (string)random_int(100000, 999999);
        $pdo->beginTransaction();
        try {
            $pdo->prepare('UPDATE auth_otps SET used_at = NOW() WHERE email = ? AND purpose = ? AND used_at IS NULL')
                ->execute([$email, $purpose]);
            $pdo->prepare(
                'INSERT INTO auth_otps (user_id, email, purpose, otp_hash, expires_at, last_sent_at)
                 VALUES (?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL 10 MINUTE), NOW())'
            )->execute([$userId, $email, $purpose, password_hash($code, PASSWORD_DEFAULT)]);
            $pdo->commit();
        } catch (Throwable $error) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $error;
        }
    } finally {
        $release = $pdo->prepare('SELECT RELEASE_LOCK(?)');
        $release->execute([$lockName]);
    }

    $subject = match ($purpose) {
        'registration' => 'Verify your LNU Scholarship Portal email',
        'forgot_password' => 'Your LNU password reset code',
        'password_change' => 'Confirm your LNU password change',
        default => 'Your LNU Scholarship Portal verification code',
    };
    $sent = send_portal_email(
        $email,
        $subject,
        "Your verification code is {$code}. It expires in 10 minutes and can be used once. "
        . "If you did not request this code, you can ignore this message.\n"
    );
    return ['wait' => 60, 'code' => $code, 'sent' => $sent];
}

function send_password_change_notice(string $email): bool {
    return send_portal_email(
        $email,
        'Your LNU Scholarship Portal password was changed',
        "The password for your LNU Scholarship Portal account was changed. "
        . "If you did not make this change, contact the Scholarship Office immediately.\n"
    );
}
