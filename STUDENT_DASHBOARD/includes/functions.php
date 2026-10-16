<?php
// Shared helpers: session, auth, CSRF, formatting, notifications, uploads.
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax']);
    session_start();
}
date_default_timezone_set('Asia/Manila');
require_once __DIR__ . '/db_connect.php';

const STATUS = [
    'draft'          => ['Draft', 'gray'],
    'submitted'      => ['Submitted', 'blue'],
    'under_review'   => ['Under Review', 'blue'],
    'for_compliance' => ['Additional Documents Required', 'orange'],
    'approved'       => ['Approved', 'green'],
    'rejected'       => ['Rejected', 'red'],
    'withdrawn'      => ['Withdrawn', 'gray'],
];
const DOC_STATUS = [
    'pending'            => ['Awaiting staff check', 'gray'],
    'verified'           => ['Verified', 'green'],
    'flagged'            => ['Needs correction', 'orange'],
    'needs_resubmission' => ['Please resubmit', 'orange'],
];
const MAX_UPLOAD = 5 * 1024 * 1024;

// TEMPORARY: there is no login page yet. While this is true, every visitor is signed in automatically
// as the first active student (role_id 1) in the database. Set to false once the login page exists;
// the portal will then require $_SESSION['user_id'] to be set by that login.
const DEV_AUTO_LOGIN = true;

function e($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function redirect($url) { header('Location: ' . $url); exit; }
function money($n) { return ($n === null || $n === '') ? null : '₱' . number_format((float)$n, 2); }
function fdate($d, $f = 'M j, Y') { return $d ? date($f, strtotime($d)) : '—'; }
function badge($status) {
    $s = STATUS[$status] ?? [ucfirst((string)$status), 'gray'];
    return '<span class="badge b-' . $s[1] . '">' . e($s[0]) . '</span>';
}
function status_label($status) { return (STATUS[$status] ?? [$status])[0]; }
function full_name($a) {
    $mid = !empty($a['middle_name']) ? mb_substr($a['middle_name'], 0, 1) . '. ' : '';
    return trim($a['first_name'] . ' ' . $mid . $a['last_name'] . ' ' . ($a['suffix'] ?? ''));
}
function initials($a) { return strtoupper(mb_substr($a['first_name'], 0, 1) . mb_substr($a['last_name'], 0, 1)); }
function prog_initials($name) {
    $w = preg_split('/[\s\-]+/', trim($name));
    $out = '';
    foreach ($w as $x) { if ($x !== '' && ctype_upper($x[0])) $out .= $x[0]; }
    return substr($out ?: strtoupper(substr($name, 0, 2)), 0, 3);
}

// ---- CSRF + flash ----
function csrf_token() {
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}
function csrf_field() { return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">'; }
function csrf_check() {
    if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) {
        http_response_code(400);
        exit('Your session expired. Go back, refresh the page and try again.');
    }
}
function flash($type = null, $msg = null) {
    if ($type !== null) { $_SESSION['flash'][] = [$type, $msg]; return null; }
    $f = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $f;
}

// ---- Auth ----
function require_student() {
    global $pdo;
    if (empty($_SESSION['user_id'])) {
        if (!DEV_AUTO_LOGIN) { http_response_code(401); exit('Please log in first.'); }
        $uid = $pdo->query("SELECT u.user_id FROM users u JOIN applicants a ON a.user_id = u.user_id
                            WHERE u.role_id = 1 AND u.account_status = 'active' ORDER BY u.user_id LIMIT 1")->fetchColumn();
        if (!$uid) { http_response_code(500); exit('No active student account found. Import sample_data.sql or add a student.'); }
        $_SESSION['user_id'] = (int)$uid;
    }
    $st = $pdo->prepare('SELECT u.email, u.role_id, u.account_status, u.last_login_at, a.*
                         FROM users u JOIN applicants a ON a.user_id = u.user_id WHERE u.user_id = ?');
    $st->execute([$_SESSION['user_id']]);
    $me = $st->fetch();
    if (!$me || (int)$me['role_id'] !== 1 || $me['account_status'] !== 'active') {
        unset($_SESSION['user_id']);
        http_response_code(403);
        exit('This account cannot use the student portal.');
    }
    return $me;
}

// ---- Application period state: [open|upcoming|closed, days] ----
function period_state($open, $close, $status) {
    $now = time(); $o = strtotime($open); $c = strtotime($close);
    if ($status !== 'open' || $now > $c) return ['closed', 0];
    if ($now < $o) return ['upcoming', (int)ceil(($o - $now) / 86400)];
    return ['open', max(0, (int)ceil(($c - $now) / 86400))];
}
function days_text($state, $days) {
    if ($state === 'closed') return 'Closed';
    if ($state === 'upcoming') return 'Opens in ' . $days . ' day' . ($days == 1 ? '' : 's');
    if ($days === 0) return 'Closes today';
    return $days . ' day' . ($days == 1 ? '' : 's') . ' left';
}
function days_class($state, $days) {
    if ($state !== 'open') return 'muted';
    return $days <= 3 ? 'urgent' : ($days <= 7 ? 'warn' : '');
}

// ---- Status history + notifications ----
function add_history($appId, $prev, $new, $userId, $remarks = null) {
    global $pdo;
    $pdo->prepare('INSERT INTO application_status_history (application_id, previous_status, new_status, changed_by, remarks) VALUES (?,?,?,?,?)')
        ->execute([$appId, $prev, $new, $userId, $remarks]);
}
function notify($userId, $appId, $type, $title, $msg) {
    global $pdo;
    $pdo->prepare("INSERT INTO notifications (user_id, application_id, channel, notification_type, title, message, delivery_status, sent_at)
                   VALUES (?,?, 'in_app', ?,?,?, 'sent', NOW())")
        ->execute([$userId, $appId, $type, $title, $msg]);
}
function notif_color($type) {
    $t = strtolower($type);
    if (preg_match('/correct|document|compliance/', $t)) return 'orange';
    if (preg_match('/deadline|urgent|reject/', $t)) return 'red';
    if (strpos($t, 'interview') !== false) return 'purple';
    if (preg_match('/submitted|approved|grantee|verified/', $t)) return 'green';
    return 'blue';
}
function time_ago($dt) {
    $d = time() - strtotime($dt);
    if ($d < 3600) return max(1, (int)($d / 60)) . ' min ago';
    if ($d < 86400) return (int)($d / 3600) . ' hr ago';
    if ($d < 604800) return (int)($d / 86400) . ' day' . ((int)($d / 86400) == 1 ? '' : 's') . ' ago';
    return fdate($dt);
}

// ---- Document upload (stores file + DB row; OCR runs separately, see README) ----
function save_document($appId, $typeId, $file, $userId) {
    global $pdo;
    if (!isset($file['error']) || $file['error'] === UPLOAD_ERR_NO_FILE) return [false, 'No file selected.'];
    if ($file['error'] !== UPLOAD_ERR_OK) return [false, 'The upload failed. Try a smaller file.'];
    if ($file['size'] > MAX_UPLOAD) return [false, 'File is larger than 5 MB.'];
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    $ext = ['application/pdf' => 'pdf', 'image/jpeg' => 'jpg', 'image/png' => 'png'][$mime] ?? null;
    if (!$ext) return [false, 'Only PDF, JPG or PNG files are accepted.'];
    $dir = dirname(__DIR__) . '/uploads/' . (int)$appId;
    if (!is_dir($dir) && !mkdir($dir, 0775, true)) return [false, 'Could not create the upload folder.'];
    $stored = bin2hex(random_bytes(16)) . '.' . $ext;
    if (!move_uploaded_file($file['tmp_name'], $dir . '/' . $stored)) return [false, 'Could not save the file.'];
    $hash = hash_file('sha256', $dir . '/' . $stored);
    $orig = mb_substr(preg_replace('/[^\w.\- ]+/u', '_', basename($file['name'])), 0, 200);
    $pdo->beginTransaction();
    $pdo->prepare('UPDATE application_documents SET is_current = 0 WHERE application_id = ? AND document_type_id = ?')->execute([$appId, $typeId]);
    $pdo->prepare('INSERT INTO application_documents (application_id, document_type_id, original_filename, stored_filename, file_path, mime_type, file_size_bytes, file_hash)
                   VALUES (?,?,?,?,?,?,?,?)')
        ->execute([$appId, $typeId, $orig, $stored, 'uploads/' . (int)$appId . '/' . $stored, $mime, $file['size'], $hash]);
    $docId = (int)$pdo->lastInsertId();
    // Hook for the OCR service: queue a row for OCR-enabled document types. The OCR worker itself is not part of the student UI.
    $en = $pdo->prepare('SELECT ocr_enabled FROM document_types WHERE document_type_id = ?');
    $en->execute([$typeId]);
    if ((int)$en->fetchColumn() === 1) {
        $pdo->prepare("INSERT INTO ocr_results (document_id, processing_status) VALUES (?, 'queued')")->execute([$docId]);
    }
    $pdo->commit();
    return [true, $docId];
}
