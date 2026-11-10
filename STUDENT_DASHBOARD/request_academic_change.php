<?php
require_once __DIR__ . '/includes/functions.php';
$me = require_student();
$catalog = require __DIR__ . '/../Registration_and_Login(UIUX)/academic_catalog.php';
$errors = [];
$year = (string)($_POST['year_level'] ?? '');
$collegeCode = (string)($_POST['college_code'] ?? '');
$programCode = (string)($_POST['program_code'] ?? '');
$specialization = (string)($_POST['specialization'] ?? '');
$reason = trim((string)($_POST['reason'] ?? ''));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $college = $catalog[$collegeCode] ?? null;
    $program = $college['programs'][$programCode] ?? null;
    if (!preg_match('/^[1-5]$/', $year)) $errors[] = 'Choose a valid year level.';
    if (!$program) $errors[] = 'Choose a valid college and program.';
    elseif ($program['requires_specialization'] && !in_array($specialization, $program['specializations'], true)) $errors[] = 'Choose a valid specialization.';
    elseif (!$program['requires_specialization'] && $specialization !== '') $errors[] = 'The selected program does not have a specialization.';
    if ($reason === '' || mb_strlen($reason) > 1000) $errors[] = 'Explain the change in 1,000 characters or fewer.';

    if (!$errors) {
        $pending = $pdo->prepare("SELECT request_id FROM academic_change_requests WHERE applicant_id = ? AND status = 'pending' LIMIT 1");
        $pending->execute([$me['applicant_id']]);
        if ($pending->fetchColumn()) {
            $errors[] = 'You already have an academic change request awaiting review.';
        } else {
            $pdo->prepare(
                'INSERT INTO academic_change_requests
                 (applicant_id, requested_year_level, requested_college_code, requested_program_code, requested_specialization, reason)
                 VALUES (?, ?, ?, ?, ?, ?)'
            )->execute([
                $me['applicant_id'], (int)$year, $collegeCode, $programCode,
                $specialization !== '' ? $specialization : null, $reason,
            ]);
            flash('success', 'Your academic change request was submitted for Scholarship Office approval.');
            redirect('profile.php');
        }
    }
}
$pageTitle = 'Request Academic Change'; $active = '';
include __DIR__ . '/includes/header.php';
?>
<section class="hero"><div><h1 class="serif">Request an academic change</h1><p class="muted">Your current program stays locked until the Scholarship Office approves a request.</p></div></section>
<p class="muted">Teacher Certificate Program year-level and scholarship eligibility rules are pending Scholarship Office confirmation.</p>
<form class="card" method="post">
    <?php if ($errors): ?><div class="flash f-error"><ul><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
    <?= csrf_field() ?>
    <div class="fg fg-1">
        <label>Year level<select name="year_level" required><option value="">Choose year</option><?php for ($i = 1; $i <= 5; $i++): ?><option value="<?= $i ?>" <?= $year === (string)$i ? 'selected' : '' ?>>Year <?= $i ?></option><?php endfor; ?></select></label>
        <label>College<select name="college_code" id="college_code" required><option value="">Choose college</option><?php foreach ($catalog as $code => $college): ?><option value="<?= e($code) ?>" <?= $collegeCode === $code ? 'selected' : '' ?>><?= e($college['name']) ?></option><?php endforeach; ?></select></label>
        <label>Program<select name="program_code" id="program_code" data-selected="<?= e($programCode) ?>" required disabled><option value="">Choose program</option></select></label>
        <label id="specializationField" hidden>Specialization / major<select name="specialization" id="specialization" data-selected="<?= e($specialization) ?>" disabled><option value="">Choose specialization</option></select></label>
        <label>Reason for request<textarea name="reason" required maxlength="1000" rows="4"><?= e($reason) ?></textarea></label>
    </div>
    <div class="form-actions"><a class="btn sec" href="profile.php">Cancel</a><button class="btn">Submit for approval</button></div>
</form>
<script>window.LNU_ACADEMIC_CATALOG = <?= json_encode($catalog, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;</script><script src="assets/js/academic-change.js"></script>
<?php include __DIR__ . '/includes/footer.php'; ?>
