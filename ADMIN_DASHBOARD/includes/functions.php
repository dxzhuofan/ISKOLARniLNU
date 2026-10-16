<?php
// Shared helpers for the ADMIN_DASHBOARD pages (same idea as STUDENT_DASHBOARD/includes/functions.php).
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Same database connection the student side uses ($pdo).
require_once __DIR__ . '/../../STUDENT_DASHBOARD/includes/db_connect.php';
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

const STAFF_ROLE = 2;                                 // roles.role_id 2 = administrator
const LOGIN_URL  = '../STUDENT_DASHBOARD/index.php';  // change to your real login page

// Names shown to staff for each applications.status value.
const STATUS = [
    'draft'          => 'Draft',
    'submitted'      => 'Pending',
    'under_review'   => 'Under Review',
    'for_compliance' => 'Needs Correction',
    'approved'       => 'Approved',
    'rejected'       => 'Rejected',
    'withdrawn'      => 'Withdrawn',
];

// "Check needed" label for an application (alias `a` = applications).
const FLAG_SQL = <<<'SQL'
CASE
    WHEN EXISTS (
        SELECT 1
        FROM application_documents d
        JOIN ocr_results o      ON o.document_id = d.document_id
        JOIN ocr_field_checks c ON c.ocr_id = o.ocr_id
        WHERE d.application_id = a.application_id AND d.is_current = 1 AND c.result = 'mismatch'
    ) THEN 'OCR mismatch'
    WHEN EXISTS (
        SELECT 1 FROM duplicate_flags f
        WHERE f.application_id = a.application_id AND f.review_status = 'pending'
    ) THEN 'Possible duplicate'
    WHEN EXISTS (
        SELECT 1 FROM application_documents d
        WHERE d.application_id = a.application_id AND d.is_current = 1
          AND d.verification_status IN ('flagged', 'needs_resubmission')
    ) THEN 'Missing document'
END
SQL;

// Like require_student(): returns the signed-in staff member.
function require_admin(): array
{
    // TEMPORARY: login check off for testing on localhost only.
    // Remove these two comment lines and uncomment the block to turn it back on.
    /*
    $role = $_SESSION['role_id'] ?? $_SESSION['user']['role_id'] ?? 0;
    if (!in_array($role, [STAFF_ROLE, (string)STAFF_ROLE, 'administrator'], true)) {
        header('Location: ' . LOGIN_URL);
        exit;
    }
    */
    return ['user_id' => $_SESSION['user_id'] ?? $_SESSION['user']['user_id'] ?? null];
}

function e($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

// Run a query and return all rows.
function rows(PDO $pdo, string $sql, array $params = []): array
{
    $st = $pdo->prepare($sql);
    $st->execute($params);
    return $st->fetchAll();
}

// Run a query and return one number.
function scalar(PDO $pdo, string $sql, array $params = []): int
{
    $st = $pdo->prepare($sql);
    $st->execute($params);
    return (int)$st->fetchColumn();
}

function csrf_field(): string
{
    $_SESSION['csrf'] = $_SESSION['csrf'] ?? bin2hex(random_bytes(16));
    return '<input type="hidden" name="csrf" value="' . $_SESSION['csrf'] . '">';
}

function check_csrf(): void
{
    if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) {
        http_response_code(400);
        exit('Invalid request.');
    }
}

function set_flash(string $message, string $type = 'ok'): void
{
    $_SESSION['flash'] = [$message, $type];
}

function show_flash(): string
{
    if (empty($_SESSION['flash'])) {
        return '';
    }
    [$message, $type] = $_SESSION['flash'];
    unset($_SESSION['flash']);
    return '<div class="alert ' . $type . '" role="status">' . e($message) . '</div>';
}

// Coloured status pill (always has an icon and text, never colour alone).
function badge(string $label): string
{
    $colors = [
        'Approved' => 'ok', 'Verified' => 'ok', 'Registered' => 'ok', 'Low' => 'ok',
        'Rejected' => 'bad', 'High' => 'bad', 'Unreadable' => 'bad',
        'Pending' => 'warn', 'Medium' => 'warn', 'Needs Correction' => 'warn',
        'Not submitted' => 'warn', 'Not yet registered' => 'warn',
        'Under Review' => 'info', 'Submitted' => 'info',
    ];
    $icons = ['ok' => '✓', 'bad' => '✕', 'warn' => '!', 'info' => '◉'];
    $color = $colors[$label] ?? 'info';
    return '<span class="bd ' . $color . '">' . $icons[$color] . ' ' . e($label) . '</span>';
}

function status_badge(string $status): string
{
    return badge(STATUS[$status] ?? $status);
}

function page_size(): int
{
    $size = (int)($_GET['size'] ?? 25);
    return in_array($size, [10, 25, 50, 100], true) ? $size : 25;
}

// "Per page" links and Previous / Next buttons.
function pager(int $total, int $page, int $size): string
{
    $pages = max(1, (int)ceil($total / $size));
    $link  = fn(array $change) => e('?' . http_build_query(array_merge($_GET, $change)));

    $html = '<div class="pg"><span>' . number_format($total) . ' records</span><div>Per page: ';
    foreach ([10, 25, 50, 100] as $n) {
        $html .= '<a' . ($n === $size ? ' aria-current="true"' : '') . ' href="' . $link(['size' => $n, 'page' => 1]) . '">' . $n . '</a> ';
    }
    if ($page > 1) {
        $html .= '<a class="btn sm sec" href="' . $link(['page' => $page - 1]) . '">‹ Previous</a>';
    }
    $html .= " Page $page of $pages ";
    if ($page < $pages) {
        $html .= '<a class="btn sm sec" href="' . $link(['page' => $page + 1]) . '">Next ›</a>';
    }
    return $html . '</div></div>';
}

// A filter dropdown that reloads the list when changed.
function select_filter(string $name, string $label, array $options, $current): string
{
    $html = '<select name="' . $name . '" aria-label="' . e($label) . '" onchange="this.form.submit()">';
    $html .= '<option value="">' . e($label) . ': All</option>';
    foreach ($options as $value => $text) {
        $selected = ((string)$current === (string)$value) ? ' selected' : '';
        $html .= '<option value="' . e($value) . '"' . $selected . '>' . e($text) . '</option>';
    }
    return $html . '</select>';
}

// A labelled form input with its error message beside it.
function field(string $id, string $label, string $type, array $values, array $errors, bool $required = true): string
{
    $mark  = $required ? ' <span class="req" aria-hidden="true">*</span>' : ' <span class="opt">(optional)</span>';
    $html  = '<div class="f"><label for="' . $id . '">' . $label . $mark . '</label>';
    $html .= '<input id="' . $id . '" name="' . $id . '" type="' . $type . '" value="' . e($values[$id] ?? '') . '"';
    $html .= $required ? ' required' : '';
    $html .= isset($errors[$id]) ? ' aria-invalid="true"' : '';
    $html .= '>';
    if (isset($errors[$id])) {
        $html .= '<div class="err">✕ ' . e($errors[$id]) . '</div>';
    }
    return $html . '</div>';
}
