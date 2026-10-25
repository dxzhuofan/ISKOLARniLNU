<?php
declare(strict_types=1);
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
const MAIL_FROM = 'no-reply@lnu.edu.ph';

function db(): PDO {
    static $pdo = null;
    if ($pdo instanceof PDO) return $pdo;
    $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
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
