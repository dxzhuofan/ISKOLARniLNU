<?php
// Serves an uploaded document to its owner only.
require_once __DIR__ . '/includes/functions.php';
$me = require_student();
$st = $pdo->prepare('SELECT d.* FROM application_documents d JOIN applications a ON a.application_id = d.application_id
                     WHERE d.document_id = ? AND a.applicant_id = ?');
$st->execute([(int)($_GET['id'] ?? 0), (int)$me['applicant_id']]);
$d = $st->fetch();
$base = realpath(__DIR__ . '/uploads');
$path = $d ? realpath(__DIR__ . '/' . $d['file_path']) : false;
if (!$d || !$path || !$base || strpos($path, $base) !== 0 || !is_file($path)) {
    http_response_code(404);
    exit('File not found.');
}
header('Content-Type: ' . $d['mime_type']);
header('Content-Length: ' . filesize($path));
header('Content-Disposition: inline; filename="' . rawurlencode($d['original_filename']) . '"');
header('X-Content-Type-Options: nosniff');
readfile($path);
