<?php
// Multi-step online application form: personal information → scholarship questions (if any) → documents → review & submit.
require_once __DIR__ . '/includes/functions.php';
$me  = require_student();
$aid = (int)$me['applicant_id'];
$uid = (int)$_SESSION['user_id'];

function open_period($pid) {
    global $pdo;
    $s = $pdo->prepare("SELECT ap.* FROM application_periods ap
        JOIN scholarship_programs p ON p.scholarship_program_id = ap.scholarship_program_id AND p.is_active = 1
        WHERE ap.scholarship_program_id = ? AND ap.status = 'open' AND NOW() BETWEEN ap.open_date AND ap.close_date
        ORDER BY ap.close_date DESC LIMIT 1");
    $s->execute([$pid]);
    return $s->fetch();
}

// ---- Start a new application (from the checklist modal) ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'start') {
    csrf_check();
    $pid = (int)($_POST['program_id'] ?? 0);
    $per = open_period($pid);
    if (!$per) { flash('error', 'Applications for this program are not open right now.'); redirect('scholarships.php'); }
    $ex = $pdo->prepare('SELECT application_id, status FROM applications WHERE applicant_id = ? AND scholarship_program_id = ? AND period_id = ?');
    $ex->execute([$aid, $pid, $per['period_id']]);
    if ($row = $ex->fetch()) {
        flash('info', 'You already have an application for this program in this period.');
        redirect($row['status'] === 'draft' ? 'apply.php?app=' . $row['application_id'] : 'application_view.php?id=' . $row['application_id']);
    }
    try {
        $pdo->beginTransaction();
        $pdo->prepare("INSERT INTO applications (application_no, applicant_id, scholarship_program_id, period_id, status) VALUES (?,?,?,?, 'draft')")
            ->execute(['TMP' . bin2hex(random_bytes(6)), $aid, $pid, $per['period_id']]);
        $newId = (int)$pdo->lastInsertId();
        $pdo->prepare('UPDATE applications SET application_no = ? WHERE application_id = ?')->execute([sprintf('LNU-%s-%05d', date('Y'), $newId), $newId]);
        add_history($newId, null, 'draft', $uid, 'Draft started');
        $pdo->commit();
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        flash('error', 'You already applied to this program for this period.');
        redirect('my_applications.php');
    }
    redirect('apply.php?app=' . $newId . '&step=personal');
}

// ---- Load the draft ----
$appId = (int)($_GET['app'] ?? $_POST['app'] ?? 0);
$st = $pdo->prepare('SELECT a.*, p.program_name, p.provider_name, ap.open_date, ap.close_date, ap.status AS period_status, ap.school_year, ap.semester
    FROM applications a
    JOIN scholarship_programs p ON p.scholarship_program_id = a.scholarship_program_id
    JOIN application_periods ap ON ap.period_id = a.period_id
    WHERE a.application_id = ? AND a.applicant_id = ?');
$st->execute([$appId, $aid]);
$app = $st->fetch();
if (!$app) { flash('error', 'Application not found.'); redirect('my_applications.php'); }
if ($app['status'] !== 'draft') { flash('info', 'This application was already submitted and can no longer be edited.'); redirect('application_view.php?id=' . $appId); }
[$pstate] = period_state($app['open_date'], $app['close_date'], $app['period_status']);
if ($pstate !== 'open') { flash('error', 'The application period has closed, so this draft can no longer be submitted.'); redirect('application_view.php?id=' . $appId); }

$pid = (int)$app['scholarship_program_id'];
$fs = $pdo->prepare('SELECT * FROM program_form_fields WHERE scholarship_program_id = ? ORDER BY display_order, field_id');
$fs->execute([$pid]);
$fields = $fs->fetchAll();
$rs = $pdo->prepare('SELECT r.document_type_id, r.is_required, r.instructions, dt.type_name, dt.description
    FROM program_requirements r JOIN document_types dt ON dt.document_type_id = r.document_type_id
    WHERE r.scholarship_program_id = ? ORDER BY r.is_required DESC, dt.type_name');
$rs->execute([$pid]);
$reqs = $rs->fetchAll();

$steps = ['personal' => 'Personal information'];
if ($fields) $steps['questions'] = 'Scholarship questions';
$steps['documents'] = 'Documents';
$steps['review'] = 'Review and submit';
$keys = array_keys($steps);

function load_values($appId) {
    global $pdo;
    $s = $pdo->prepare('SELECT field_id, field_value FROM application_field_values WHERE application_id = ?');
    $s->execute([$appId]);
    return $s->fetchAll(PDO::FETCH_KEY_PAIR);
}
function load_docs($appId) {
    global $pdo;
    $s = $pdo->prepare('SELECT * FROM application_documents WHERE application_id = ? AND is_current = 1');
    $s->execute([$appId]);
    $out = [];
    foreach ($s->fetchAll() as $d) $out[$d['document_type_id']] = $d;
    return $out;
}
function compute_missing($me, $fields, $reqs, $values, $docs) {
    $m = [];
    if (trim((string)$me['mobile_number']) === '') $m[] = ['personal', 'Contact number'];
    foreach ($fields as $f) {
        $v = $values[$f['field_id']] ?? '';
        if ($f['is_required'] && ($v === '' || ($f['field_type'] === 'checkbox' && $v !== '1'))) $m[] = ['questions', $f['field_label']];
    }
    foreach ($reqs as $r) if ($r['is_required'] && empty($docs[$r['document_type_id']])) $m[] = ['documents', $r['type_name']];
    return $m;
}

$errors = [];
$step = $_GET['step'] ?? 'personal';

// ---- Save a step / submit ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($_POST['action'] ?? '', ['save', 'submit'], true)) {
    csrf_check();
    $step = $_POST['step'] ?? 'personal';
    if (!isset($steps[$step])) $step = 'personal';
    $go = $_POST['go'] ?? 'next';
    $strict = ($go === 'next');

    if ($_POST['action'] === 'save') {
        if ($step === 'personal') {
            $mobile = trim($_POST['mobile_number'] ?? '');
            $addr = ['address_line' => 150, 'barangay' => 100, 'city_municipality' => 100, 'province' => 100];
            $vals = [];
            foreach ($addr as $k => $len) $vals[$k] = mb_substr(trim($_POST[$k] ?? ''), 0, $len) ?: null;
            if (!preg_match('/^[0-9+\-\s()]{7,20}$/', $mobile)) {
                if ($strict) $errors[] = 'Enter a valid contact number (digits, spaces, + or -).';
            } else {
                $pdo->prepare('UPDATE applicants SET mobile_number = ?, address_line = ?, barangay = ?, city_municipality = ?, province = ? WHERE applicant_id = ?')
                    ->execute([$mobile, $vals['address_line'], $vals['barangay'], $vals['city_municipality'], $vals['province'], $aid]);
            }
            $me = require_student();
        } elseif ($step === 'questions') {
            $up = $pdo->prepare('INSERT INTO application_field_values (application_id, field_id, field_value) VALUES (?,?,?)
                                 ON DUPLICATE KEY UPDATE field_value = VALUES(field_value)');
            foreach ($fields as $f) {
                $raw = $_POST['f'][$f['field_id']] ?? '';
                $val = $f['field_type'] === 'checkbox' ? (isset($_POST['f'][$f['field_id']]) ? '1' : '0') : mb_substr(trim((string)$raw), 0, 5000);
                if ($val !== '' && $f['field_type'] === 'number' && !is_numeric($val)) { if ($strict) $errors[] = $f['field_label'] . ' must be a number.'; continue; }
                if ($val !== '' && $f['field_type'] === 'date' && !strtotime($val)) { if ($strict) $errors[] = $f['field_label'] . ' must be a valid date.'; continue; }
                if ($val !== '' && $f['field_type'] === 'select') {
                    $opts = json_decode((string)$f['options'], true) ?: [];
                    if (!in_array($val, $opts, true)) { if ($strict) $errors[] = 'Choose a valid option for ' . $f['field_label'] . '.'; continue; }
                }
                if ($strict && $f['is_required'] && ($val === '' || ($f['field_type'] === 'checkbox' && $val !== '1'))) $errors[] = $f['field_label'] . ' is required.';
                $up->execute([$appId, $f['field_id'], $val]);
            }
        } elseif ($step === 'documents') {
            foreach ($reqs as $r) {
                $key = 'doc_' . $r['document_type_id'];
                if (!empty($_FILES[$key]) && $_FILES[$key]['error'] !== UPLOAD_ERR_NO_FILE) {
                    [$ok, $msg] = save_document($appId, (int)$r['document_type_id'], $_FILES[$key], $uid);
                    if (!$ok) $errors[] = $r['type_name'] . ': ' . $msg;
                }
            }
        }
        if (!$errors) {
            if ($go === 'exit') { flash('success', 'Draft saved. You can continue it any time before the deadline.'); redirect('my_applications.php'); }
            $i = array_search($step, $keys, true);
            $ni = max(0, min(count($keys) - 1, $go === 'back' ? $i - 1 : $i + 1));
            redirect('apply.php?app=' . $appId . '&step=' . $keys[$ni]);
        }
    } else { // submit
        $missing = compute_missing($me, $fields, $reqs, load_values($appId), load_docs($appId));
        if (empty($_POST['confirm'])) $errors[] = 'Please confirm that your information is accurate.';
        if ($missing) $errors[] = 'Complete the missing items before submitting.';
        if (!$errors) {
            $pdo->beginTransaction();
            $u = $pdo->prepare("UPDATE applications SET status = 'submitted', submitted_at = NOW() WHERE application_id = ? AND applicant_id = ? AND status = 'draft'");
            $u->execute([$appId, $aid]);
            if ($u->rowCount()) {
                add_history($appId, 'draft', 'submitted', $uid, 'Application submitted by student');
                notify($uid, $appId, 'application_submitted', 'Application successfully submitted', $app['program_name'] . ' (' . $app['application_no'] . ') was received.');
            }
            $pdo->commit();
            flash('success', 'Application submitted. Track its progress here.');
            redirect('application_view.php?id=' . $appId);
        }
        $step = 'review';
    }
}
if (!isset($steps[$step])) $step = 'personal';
$values = load_values($appId);
$docs   = load_docs($appId);
$missing = compute_missing($me, $fields, $reqs, $values, $docs);
$idx = array_search($step, $keys, true);

$pageTitle = 'Apply: ' . $app['program_name']; $active = 'scholarships';
include __DIR__ . '/includes/header.php';
?>
<a class="back" href="scholarship_view.php?id=<?= $pid ?>">← Back to scholarship</a>
<section class="card">
  <h1 class="serif">Apply: <?= e($app['program_name']) ?></h1>
  <p class="muted"><?= e($app['provider_name']) ?> · Reference <?= e($app['application_no']) ?> · Deadline <?= fdate($app['close_date']) ?></p>
  <ol class="steps">
    <?php foreach ($keys as $i => $k): ?>
      <li class="<?= $i < $idx ? 'done' : ($i === $idx ? 'cur' : '') ?>">
        <?php if ($i < $idx): ?><a href="apply.php?app=<?= $appId ?>&step=<?= $k ?>"><span><?= '✓' ?></span><?= e($steps[$k]) ?></a>
        <?php else: ?><span class="n"><?= $i + 1 ?></span><?= e($steps[$k]) ?><?php endif; ?>
      </li>
    <?php endforeach; ?>
  </ol>

  <?php if ($errors): ?><div class="flash f-error"><ul><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul></div><?php endif; ?>

<?php if ($step === 'review'): ?>
  <div class="review">
    <h2 class="sub">Personal information</h2>
    <dl class="kv grid-kv">
      <div><dt>Full name</dt><dd><?= e(full_name($me)) ?></dd></div>
      <div><dt>Student number</dt><dd><?= e($me['student_number']) ?></dd></div>
      <div><dt>Date of birth</dt><dd><?= fdate($me['birth_date']) ?></dd></div>
      <div><dt>Contact number</dt><dd><?= e($me['mobile_number'] ?: '—') ?></dd></div>
      <div><dt>Email</dt><dd><?= e($me['email']) ?></dd></div>
      <div><dt>Address</dt><dd><?= e(implode(', ', array_filter([$me['address_line'], $me['barangay'], $me['city_municipality'], $me['province']])) ?: '—') ?></dd></div>
    </dl>
    <?php if ($fields): ?>
      <h2 class="sub">Scholarship questions</h2>
      <dl class="kv">
        <?php foreach ($fields as $f): $v = $values[$f['field_id']] ?? ''; ?>
          <div><dt><?= e($f['field_label']) ?></dt><dd><?= $f['field_type'] === 'checkbox' ? ($v === '1' ? 'Yes' : 'No') : nl2br(e($v !== '' ? $v : '—')) ?></dd></div>
        <?php endforeach; ?>
      </dl>
    <?php endif; ?>
    <h2 class="sub">Documents</h2>
    <ul class="doc-list">
      <?php foreach ($reqs as $r): $d = $docs[$r['document_type_id']] ?? null; ?>
        <li><b><?= e($r['type_name']) ?> <?= $r['is_required'] ? '' : '<span class="tag">Optional</span>' ?></b>
          <small><?= $d ? '✓ ' . e($d['original_filename']) . ' · ' . fdate($d['uploaded_at']) : ($r['is_required'] ? '<span class="urgent">Missing</span>' : 'Not uploaded') ?></small></li>
      <?php endforeach; ?>
      <?php if (!$reqs): ?><li class="muted">This program has no document requirements.</li><?php endif; ?>
    </ul>
  </div>

  <?php if ($missing): ?>
    <div class="note"><b>Finish these before submitting:</b>
      <ul><?php foreach ($missing as [$k, $t]): ?><li><a href="apply.php?app=<?= $appId ?>&step=<?= $k ?>"><?= e($t) ?></a></li><?php endforeach; ?></ul>
    </div>
  <?php endif; ?>

  <form method="post">
    <?= csrf_field() ?><input type="hidden" name="app" value="<?= $appId ?>"><input type="hidden" name="step" value="review">
    <label class="check"><input type="checkbox" name="confirm" value="1" data-requires-check="#submitBtn"> I confirm that the information and documents I provided are true and accurate.</label>
    <p class="muted small">After you submit, you cannot edit the application unless the Scholarship Office asks for a correction.</p>
    <div class="form-actions">
      <a class="btn btn-alt" href="apply.php?app=<?= $appId ?>&step=<?= $keys[$idx - 1] ?>">Back</a>
      <button class="btn btn-gold" name="action" value="submit" id="submitBtn" <?= $missing ? 'data-locked ' : '' ?>disabled>Submit application</button>
    </div>
  </form>

<?php else: ?>
  <form method="post" enctype="multipart/form-data" class="step-form">
    <?= csrf_field() ?><input type="hidden" name="action" value="save"><input type="hidden" name="app" value="<?= $appId ?>"><input type="hidden" name="step" value="<?= e($step) ?>">

    <?php if ($step === 'personal'): ?>
      <p class="muted">Your name, student number, birth date and email are verified by the university and cannot be changed here. Update your contact details if needed.</p>
      <div class="fg">
        <label>Full name<input value="<?= e(full_name($me)) ?>" disabled></label>
        <label>Student number<input value="<?= e($me['student_number']) ?>" disabled></label>
        <label>Date of birth<input value="<?= fdate($me['birth_date']) ?>" disabled></label>
        <label>Email address<input value="<?= e($me['email']) ?>" disabled></label>
        <label>Contact number *<input name="mobile_number" required maxlength="20" inputmode="tel" value="<?= e($_POST['mobile_number'] ?? $me['mobile_number']) ?>"></label>
        <label>Street / address line<input name="address_line" maxlength="150" value="<?= e($_POST['address_line'] ?? $me['address_line']) ?>"></label>
        <label>Barangay<input name="barangay" maxlength="100" value="<?= e($_POST['barangay'] ?? $me['barangay']) ?>"></label>
        <label>City / municipality<input name="city_municipality" maxlength="100" value="<?= e($_POST['city_municipality'] ?? $me['city_municipality']) ?>"></label>
        <label>Province<input name="province" maxlength="100" value="<?= e($_POST['province'] ?? $me['province']) ?>"></label>
      </div>

    <?php elseif ($step === 'questions'): ?>
      <p class="muted">Answer the questions set by this scholarship. Fields marked * are required.</p>
      <div class="fg fg-1">
      <?php foreach ($fields as $f):
          $name = 'f[' . (int)$f['field_id'] . ']';
          $v = $_POST['f'][$f['field_id']] ?? ($values[$f['field_id']] ?? '');
          $req = $f['is_required'] ? 'required' : ''; $lab = e($f['field_label']) . ($f['is_required'] ? ' *' : ''); ?>
        <?php if ($f['field_type'] === 'checkbox'): ?>
          <label class="check"><input type="checkbox" name="<?= $name ?>" value="1" <?= $v === '1' || $v === 'on' ? 'checked' : '' ?> <?= $req ?>> <?= $lab ?></label>
        <?php elseif ($f['field_type'] === 'textarea'): ?>
          <label><?= $lab ?><textarea name="<?= $name ?>" rows="5" maxlength="5000" <?= $req ?>><?= e($v) ?></textarea></label>
        <?php elseif ($f['field_type'] === 'select'): $opts = json_decode((string)$f['options'], true) ?: []; ?>
          <label><?= $lab ?><select name="<?= $name ?>" <?= $req ?>><option value="">Choose…</option>
            <?php foreach ($opts as $o): ?><option <?= $v === $o ? 'selected' : '' ?>><?= e($o) ?></option><?php endforeach; ?></select></label>
        <?php else: $type = in_array($f['field_type'], ['number', 'date'], true) ? $f['field_type'] : 'text'; ?>
          <label><?= $lab ?><input type="<?= $type ?>" name="<?= $name ?>" value="<?= e($v) ?>" <?= $type === 'number' ? 'step="any"' : '' ?> <?= $req ?>></label>
        <?php endif; ?>
      <?php endforeach; ?>
      </div>

    <?php elseif ($step === 'documents'): ?>
      <p class="muted">Upload clear PDF, JPG or PNG files, up to 5 MB each. Choosing a new file replaces the previous one.</p>
      <?php if (!$reqs): ?><p class="empty">This program has no document requirements yet.</p><?php endif; ?>
      <?php foreach ($reqs as $r): $d = $docs[$r['document_type_id']] ?? null; ?>
        <div class="upload">
          <div class="grow">
            <b><?= e($r['type_name']) ?> <span class="tag"><?= $r['is_required'] ? 'Required' : 'Optional' ?></span></b>
            <small><?= e($r['instructions'] ?: $r['description']) ?></small>
            <?php if ($d): ?><small class="ok">✓ Uploaded: <a href="download.php?id=<?= (int)$d['document_id'] ?>" target="_blank"><?= e($d['original_filename']) ?></a> · <?= fdate($d['uploaded_at']) ?></small><?php endif; ?>
          </div>
          <input type="file" name="doc_<?= (int)$r['document_type_id'] ?>" accept=".pdf,.jpg,.jpeg,.png" aria-label="<?= e($r['type_name']) ?>">
        </div>
      <?php endforeach; ?>
    <?php endif; ?>

    <div class="form-actions">
      <?php if ($idx > 0): ?><button class="btn btn-alt" name="go" value="back" formnovalidate>Back</button><?php endif; ?>
      <button class="btn btn-alt" name="go" value="exit" formnovalidate>Save draft and exit</button>
      <button class="btn" name="go" value="next">Continue</button>
    </div>
  </form>
<?php endif; ?>
</section>
<?php include __DIR__ . '/includes/footer.php'; ?>
