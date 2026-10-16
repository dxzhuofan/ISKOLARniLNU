<?php
require_once __DIR__ . '/includes/functions.php';
$me = require_student();
$uid = (int)$_SESSION['user_id'];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $cur = $_POST['current'] ?? ''; $new = $_POST['new'] ?? ''; $conf = $_POST['confirm'] ?? '';
    $hash = $pdo->prepare('SELECT password_hash FROM users WHERE user_id = ?');
    $hash->execute([$uid]);
    if (!password_verify($cur, (string)$hash->fetchColumn())) $errors[] = 'Your current password is incorrect.';
    if (strlen($new) < 8) $errors[] = 'The new password must be at least 8 characters.';
    if (!preg_match('/[A-Za-z]/', $new) || !preg_match('/\d/', $new)) $errors[] = 'Use at least one letter and one number.';
    if ($new !== $conf) $errors[] = 'The new passwords do not match.';
    if (!$errors) {
        $pdo->prepare('UPDATE users SET password_hash = ?, must_change_password = 0 WHERE user_id = ?')->execute([password_hash($new, PASSWORD_DEFAULT), $uid]);
        session_regenerate_id(true);
        flash('success', 'Password changed.');
        redirect('account.php');
    }
}
$pageTitle = 'Account Settings'; $active = '';
include __DIR__ . '/includes/header.php';
?>
<section class="hero"><div><h1 class="serif">Account Settings</h1><p class="muted">Manage how you sign in to the portal.</p></div></section>
<div class="two two-even">
  <section class="card">
    <h2 class="card-title">Account</h2>
    <dl class="kv">
      <div><dt>Email (used to log in)</dt><dd><?= e($me['email']) ?></dd></div>
      <div><dt>Student number</dt><dd><?= e($me['student_number']) ?></dd></div>
      <div><dt>Last login</dt><dd><?= $me['last_login_at'] ? fdate($me['last_login_at'], 'M j, Y g:i A') : '—' ?></dd></div>
    </dl>
    <p class="muted small">To change your email address, contact the Scholarship Office.</p>
  </section>
  <form class="card" method="post" id="password">
    <h2 class="card-title">Change password</h2>
    <?php if ($errors): ?><div class="flash f-error"><ul><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
    <?= csrf_field() ?>
    <div class="fg fg-1">
      <label>Current password<input type="password" name="current" required autocomplete="current-password"></label>
      <label>New password<input type="password" name="new" required minlength="8" autocomplete="new-password"></label>
      <label>Confirm new password<input type="password" name="confirm" required minlength="8" autocomplete="new-password"></label>
    </div>
    <div class="form-actions"><button class="btn">Change password</button></div>
  </form>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
