<?php
require_once __DIR__ . '/includes/functions.php';
$me = require_student();
$aid = (int)$me['applicant_id'];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $mobile = trim($_POST['mobile_number'] ?? '');
    if (!preg_match('/^[0-9+\-\s()]{7,20}$/', $mobile)) $errors[] = 'Enter a valid contact number (digits, spaces, + or -).';
    $v = [];
    foreach (['address_line' => 150, 'barangay' => 100, 'city_municipality' => 100, 'province' => 100] as $k => $len) $v[$k] = mb_substr(trim($_POST[$k] ?? ''), 0, $len) ?: null;
    if (!$errors) {
        $pdo->prepare('UPDATE applicants SET mobile_number = ?, address_line = ?, barangay = ?, city_municipality = ?, province = ? WHERE applicant_id = ?')
            ->execute([$mobile, $v['address_line'], $v['barangay'], $v['city_municipality'], $v['province'], $aid]);
        flash('success', 'Profile updated.');
        redirect('profile.php');
    }
}

$g = $pdo->prepare("SELECT p.program_name FROM grantees g JOIN scholarship_programs p ON p.scholarship_program_id = g.scholarship_program_id WHERE g.applicant_id = ? AND g.status = 'active' LIMIT 1");
$g->execute([$aid]);
$grantee = $g->fetchColumn();

$pageTitle = 'My Profile'; $active = '';
include __DIR__ . '/includes/header.php';
?>
<section class="hero"><div><h1 class="serif">My Profile</h1><p class="muted">You can update your contact details. Official records are managed by the university.</p></div></section>
<div class="two two-even">
  <section class="card profile-card">
    <span class="avatar avatar-lg"><?= e(initials($me)) ?></span>
    <h2 class="serif"><?= e(full_name($me)) ?></h2>
    <p class="muted"><?= e($me['student_number']) ?></p>
    <dl>
      <div><dt>Date of birth</dt><dd><?= fdate($me['birth_date']) ?></dd></div>
      <div><dt>Email</dt><dd><?= e($me['email']) ?></dd></div>
      <div><dt>Grantee status</dt><dd><?= $grantee ? 'Active grantee · ' . e($grantee) : 'Not a grantee yet' ?></dd></div>
    </dl>
    <p class="muted small">Name, student number, birth date and grantee details are verified by the Scholarship Office. Ask the office to correct them.</p>
  </section>
  <form class="card" method="post">
    <h2 class="card-title">Contact information</h2>
    <?php if ($errors): ?><div class="flash f-error"><?= e(implode(' ', $errors)) ?></div><?php endif; ?>
    <?= csrf_field() ?>
    <div class="fg fg-1">
      <label>Contact number *<input name="mobile_number" required maxlength="20" inputmode="tel" value="<?= e($_POST['mobile_number'] ?? $me['mobile_number']) ?>"></label>
      <label>Street / address line<input name="address_line" maxlength="150" value="<?= e($_POST['address_line'] ?? $me['address_line']) ?>"></label>
      <label>Barangay<input name="barangay" maxlength="100" value="<?= e($_POST['barangay'] ?? $me['barangay']) ?>"></label>
      <label>City / municipality<input name="city_municipality" maxlength="100" value="<?= e($_POST['city_municipality'] ?? $me['city_municipality']) ?>"></label>
      <label>Province<input name="province" maxlength="100" value="<?= e($_POST['province'] ?? $me['province']) ?>"></label>
    </div>
    <div class="form-actions"><button class="btn">Save changes</button></div>
  </form>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
