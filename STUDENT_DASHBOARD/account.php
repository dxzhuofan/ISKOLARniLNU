<?php
require_once __DIR__ . '/includes/functions.php';
$me = require_student();
$uid = (int)$_SESSION['user_id'];
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
  <form class="card" method="post" action="../Registration_and_Login(UIUX)/request_password_change.php" id="password">
    <h2 class="card-title">Change password</h2>
    <?= csrf_field() ?>
    <div class="fg fg-1">
      <label>Current password<input type="password" name="current" required autocomplete="current-password"></label>
      <label>New password<input type="password" name="new" required minlength="8" autocomplete="new-password" pattern="(?=.*[A-Z])(?=.*\d)(?=.*[^A-Za-z0-9]).{8,}" title="At least 8 characters, an uppercase letter, a number, and a symbol"></label>
      <label>Confirm new password<input type="password" name="confirm" required minlength="8" autocomplete="new-password"></label>
    </div>
    <p class="muted small">A one-time code will be sent to <?= e($me['email']) ?> to confirm the password change.</p>
    <div class="form-actions"><button class="btn">Change password</button></div>
  </form>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
