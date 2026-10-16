<?php
// Marks notifications as read (called by assets/js/main.js). Returns JSON.
require_once __DIR__ . '/../includes/functions.php';
header('Content-Type: application/json');
if (empty($_SESSION['user_id']) || $_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(403); echo '{"ok":false}'; exit; }
csrf_check();
$uid = (int)$_SESSION['user_id'];
if (!empty($_POST['id'])) {
    $pdo->prepare('UPDATE notifications SET is_read = 1, read_at = NOW() WHERE notification_id = ? AND user_id = ?')->execute([(int)$_POST['id'], $uid]);
} else {
    $pdo->prepare("UPDATE notifications SET is_read = 1, read_at = NOW() WHERE user_id = ? AND channel = 'in_app' AND is_read = 0")->execute([$uid]);
}
echo json_encode(['ok' => true]);
