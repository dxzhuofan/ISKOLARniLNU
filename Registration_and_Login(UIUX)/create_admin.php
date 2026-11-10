<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/config.php';

$input = stream_get_contents(STDIN);
$account = json_decode($input, true);
if (!is_array($account)) {
    fwrite(STDERR, "Invalid setup input.\n");
    exit(1);
}

$email = strtolower(trim((string)($account['email'] ?? '')));
$firstName = trim((string)($account['first_name'] ?? ''));
$middleName = trim((string)($account['middle_name'] ?? ''));
$lastName = trim((string)($account['last_name'] ?? ''));
$employeeNo = trim((string)($account['employee_no'] ?? ''));
$position = trim((string)($account['position_title'] ?? ''));
$roleName = trim((string)($account['role_name'] ?? 'administrator'));
$password = (string)($account['password'] ?? '');

if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 150 || !preg_match('/@lnu\.edu\.ph$/i', $email)) {
    fwrite(STDERR, "Enter a valid LNU email address ending in @lnu.edu.ph (at most 150 characters).\n");
    exit(1);
}
if ($firstName === '' || mb_strlen($firstName) > 80 || mb_strlen($middleName) > 80 || $lastName === '' || mb_strlen($lastName) > 80) {
    fwrite(STDERR, "First and last names are required and must be at most 80 characters.\n");
    exit(1);
}
if (mb_strlen($employeeNo) > 30 || mb_strlen($position) > 100) {
    fwrite(STDERR, "Employee number or position title is too long.\n");
    exit(1);
}
if (!in_array($roleName, ['administrator', 'scholarship_officer', 'reviewer'], true)) {
    fwrite(STDERR, "Choose administrator, scholarship_officer, or reviewer as the staff role.\n");
    exit(1);
}
if (strlen($password) < 12) {
    fwrite(STDERR, "Use a unique staff password with at least 12 characters.\n");
    exit(1);
}

$pdo = null;
$lockAcquired = false;
try {
    $pdo = db();
    $lockAcquired = (int)$pdo->query("SELECT GET_LOCK('lnu_scholarship_admin_setup', 10)")->fetchColumn() === 1;
    if (!$lockAcquired) {
        throw new RuntimeException('Could not acquire the administrator setup lock. Try again.');
    }

    $pdo->beginTransaction();
    $existing = $pdo->prepare('SELECT user_id FROM users WHERE email = ? LIMIT 1');
    $existing->execute([$email]);
    if ($existing->fetchColumn() !== false) {
        throw new RuntimeException('An account already uses that email address.');
    }

    if ($employeeNo !== '') {
        $existing = $pdo->prepare('SELECT staff_id FROM staff_profiles WHERE employee_no = ? LIMIT 1');
        $existing->execute([$employeeNo]);
        if ($existing->fetchColumn() !== false) {
            throw new RuntimeException('That employee number is already in use.');
        }
    }

    $roleQuery = $pdo->prepare('SELECT role_id FROM roles WHERE role_name = ? LIMIT 1');
    $roleQuery->execute([$roleName]);
    $roleId = $roleQuery->fetchColumn();
    if ($roleId === false) {
        throw new RuntimeException("The '{$roleName}' role is missing from the roles table. Run the staff roles migration first.");
    }

    $userId = (int)$pdo->query('SELECT COALESCE(MAX(user_id), -1) + 1 FROM users')->fetchColumn();
    $staffId = (int)$pdo->query('SELECT COALESCE(MAX(staff_id), -1) + 1 FROM staff_profiles')->fetchColumn();

    $createUser = $pdo->prepare(
        'INSERT INTO users (user_id, role_id, email, password_hash, account_status, email_verified_at)
         VALUES (?, ?, ?, ?, \'active\', NOW())'
    );
    $createUser->execute([$userId, (int)$roleId, $email, password_hash($password, PASSWORD_DEFAULT)]);

    $createProfile = $pdo->prepare(
        'INSERT INTO staff_profiles (staff_id, user_id, employee_no, first_name, middle_name, last_name, position_title)
         VALUES (?, ?, ?, ?, ?, ?, ?)'
    );
    $createProfile->execute([
        $staffId,
        $userId,
        $employeeNo !== '' ? $employeeNo : null,
        $firstName,
        $middleName !== '' ? $middleName : null,
        $lastName,
        $position !== '' ? $position : null,
    ]);

    $pdo->commit();
    fwrite(STDOUT, "Staff account ({$roleName}) created for {$email}.\n");
    fwrite(STDOUT, "Sign in at http://localhost/ISKOLARniLNU/Registration_and_Login(UIUX)/login.php using the Staff tab.\n");
} catch (Throwable $error) {
    if ($pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    fwrite(STDERR, "Staff account was not created: {$error->getMessage()}\n");
    exit(1);
} finally {
    if ($lockAcquired && $pdo instanceof PDO) {
        $pdo->query("SELECT RELEASE_LOCK('lnu_scholarship_admin_setup')");
    }
}
