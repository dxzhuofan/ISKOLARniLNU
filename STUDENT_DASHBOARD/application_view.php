<?php
require_once __DIR__ . '/includes/functions.php';
$me = require_student();
$aid = (int)$me['applicant_id'];
$uid = (int)$_SESSION['user_id'];
$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);

$st = $pdo->prepare('SELECT a.*, p.program_name, p.provider_name, ap.school_year, ap.semester
    FROM applications a
    JOIN scholarship_programs p ON p.scholarship_program_id = a.scholarship_program_id
    JOIN application_periods ap ON ap.period_id = a.period_id
    WHERE a.application_id = ? AND a.applicant_id = ?');
$st->execute([$id, $aid]);
$app = $st->fetch();
if (!$app) { flash('error', 'Application not found.'); redirect('my_applications.php'); }
$pid = (int)$app['scholarship_program_id'];

// ---- Actions: upload corrected document, withdraw ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';
    if ($action === 'resubmit' && in_array($app['status'], ['submitted', 'under_review', 'for_compliance'], true)) {
        $typeId = (int)($_POST['document_type_id'] ?? 0);
        $q = $pdo->prepare('SELECT dt.type_name FROM program_requirements r JOIN document_types dt ON dt.document_type_id = r.document_type_id
                            WHERE r.scholarship_program_id = ? AND r.document_type_id = ?');
        $q->execute([$pid, $typeId]);
        $typeName = $q->fetchColumn();
        $cur = $pdo->prepare('SELECT verification_status FROM application_documents WHERE application_id = ? AND document_type_id = ? AND is_current = 1');
        $cur->execute([$id, $typeId]);
        $curStatus = $cur->fetchColumn();
        $allowed = $typeName && (in_array($curStatus, ['flagged', 'needs_resubmission'], true) || ($curStatus === false && $app['status'] === 'for_compliance'));
        if (!$allowed) {
            flash('error', 'This document cannot be changed. Only documents the Scholarship Office asked you to correct can be replaced.');
        } else {
            [$ok, $msg] = save_document($id, $typeId, $_FILES['file'] ?? [], $uid);
            if ($ok) {
                add_history($id, $app['status'], $app['status'], $uid, 'Corrected document uploaded: ' . $typeName);
                flash('success', $typeName . ' uploaded. The Scholarship Office will check it again.');
            } else {
                flash('error', $typeName . ': ' . $msg);
            }
        }
        redirect('application_view.php?id=' . $id . '#documents');
    }
    if ($action === 'withdraw' && in_array($app['status'], ['submitted', 'under_review', 'for_compliance'], true)) {
        $pdo->prepare("UPDATE applications SET status = 'withdrawn' WHERE application_id = ? AND applicant_id = ?")->execute([$id, $aid]);
        add_history($id, $app['status'], 'withdrawn', $uid, 'Withdrawn by student');
        flash('success', 'Your application was withdrawn.');
        redirect('application_view.php?id=' . $id);
    }
    redirect('application_view.php?id=' . $id);
}

// ---- Data ----
$st = $pdo->prepare('SELECT d.*, dt.type_name, dt.ocr_enabled, o.processing_status AS ocr_status
    FROM application_documents d
    JOIN document_types dt ON dt.document_type_id = d.document_type_id
    LEFT JOIN ocr_results o ON o.ocr_id = (SELECT MAX(o2.ocr_id) FROM ocr_results o2 WHERE o2.document_id = d.document_id)
    WHERE d.application_id = ? AND d.is_current = 1 ORDER BY dt.type_name');
$st->execute([$id]);
$docs = $st->fetchAll();
$have = array_column($docs, 'document_type_id');

$st = $pdo->prepare('SELECT r.document_type_id, r.is_required, dt.type_name FROM program_requirements r
    JOIN document_types dt ON dt.document_type_id = r.document_type_id WHERE r.scholarship_program_id = ? AND r.is_required = 1');
$st->execute([$pid]);
$missingDocs = array_filter($st->fetchAll(), fn($r) => !in_array($r['document_type_id'], $have));

$st = $pdo->prepare('SELECT * FROM application_status_history WHERE application_id = ? ORDER BY changed_at DESC, history_id DESC');
$st->execute([$id]);
$history = $st->fetchAll();
$hadCompliance = $app['status'] === 'for_compliance';
foreach ($history as $h) if ($h['new_status'] === 'for_compliance') $hadCompliance = true;

$flaggedDocs = array_filter($docs, fn($d) => in_array($d['verification_status'], ['flagged', 'needs_resubmission'], true));

// Timeline
$rank = ['draft' => 0, 'submitted' => 1, 'under_review' => 2, 'for_compliance' => 3, 'approved' => 4, 'rejected' => 4, 'withdrawn' => -1][$app['status']];
$final = in_array($app['status'], ['approved', 'rejected'], true);
$tl = [];
$tl[] = ['Application Submitted', $rank >= 1 ? 'done' : 'pending', $app['submitted_at'] ? fdate($app['submitted_at'], 'M j, Y g:i A') : 'Not submitted yet'];
$tl[] = ['Initial Document Checking', $rank >= 2 || $final ? 'done' : ($rank === 1 ? 'cur' : 'pending'), ''];
$tl[] = ['Staff Review', $rank >= 3 || $final ? 'done' : ($rank === 2 ? 'cur' : 'pending'), ''];
if ($hadCompliance) $tl[] = ['Additional Documents or Requirements', $rank === 3 ? 'cur' : ($rank === 4 || $app['status'] === 'under_review' ? 'done' : 'pending'), ''];
$tl[] = ['Final Decision', $final ? 'done' : 'pending', $final ? status_label($app['status']) . ' · ' . fdate($app['decided_at']) : ''];
$statusText = ['done' => 'Completed', 'cur' => 'Current stage', 'pending' => 'Pending'];

$ocrLabel = function ($d) {
    if (!(int)$d['ocr_enabled']) return ['Not required', 'gray'];
    return [
        'processing' => ['Checking', 'blue'], 'completed' => ['Checked', 'green'], 'failed' => ['Could not read', 'orange'],
    ][$d['ocr_status'] ?? ''] ?? ['Waiting', 'gray'];
};

$pageTitle = $app['application_no']; $active = 'applications';
include __DIR__ . '/includes/header.php';
?>
<a class="back" href="my_applications.php">← Back to my applications</a>

<section class="card">
  <div class="split">
    <div><h1 class="serif"><?= e($app['program_name']) ?></h1><p class="muted"><?= e($app['provider_name']) ?></p></div>
    <?= badge($app['status']) ?>
  </div>
  <dl class="kv grid-kv">
    <div><dt>Reference number</dt><dd><?= e($app['application_no']) ?></dd></div>
    <div><dt>Date submitted</dt><dd><?= $app['submitted_at'] ? fdate($app['submitted_at']) : 'Not submitted' ?></dd></div>
    <div><dt>Application period</dt><dd>AY <?= e($app['school_year']) ?>, <?= e($app['semester']) ?> sem</dd></div>
    <div><dt>Last update</dt><dd><?= fdate($app['updated_at']) ?></dd></div>
  </dl>
  <div class="actions">
    <?php if ($app['status'] === 'draft'): ?><a class="btn btn-gold" href="apply.php?app=<?= $id ?>">Continue draft</a><?php endif; ?>
    <?php if (in_array($app['status'], ['submitted', 'under_review', 'for_compliance'], true)): ?>
      <form method="post" data-confirm="Withdraw this application? This cannot be undone."><?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>"><button class="btn btn-alt btn-sm" name="action" value="withdraw">Withdraw application</button></form>
    <?php endif; ?>
  </div>
</section>

<?php if ($app['status'] === 'for_compliance' || $flaggedDocs || ($final && $app['decision_remarks'])): ?>
<section class="note <?= $final ? ($app['status'] === 'approved' ? 'note-green' : 'note-red') : '' ?>" id="feedback">
  <h2 class="sub">Staff feedback</h2>
  <?php foreach ($flaggedDocs as $d): ?>
    <p><b><?= e($d['type_name']) ?>:</b> <?= e($d['staff_remarks'] ?: 'This document needs to be replaced.') ?></p>
  <?php endforeach; ?>
  <?php foreach ($missingDocs as $m): if ($app['status'] === 'for_compliance'): ?>
    <p><b><?= e($m['type_name']) ?>:</b> Missing. Please upload it below.</p>
  <?php endif; endforeach; ?>
  <?php if ($app['decision_remarks']): ?><p><b>Remarks:</b> <?= nl2br(e($app['decision_remarks'])) ?></p><?php endif; ?>
</section>
<?php endif; ?>

<div class="two two-even">
  <section class="card">
    <h2 class="card-title">Application timeline</h2>
    <?php if ($app['status'] === 'withdrawn'): ?><p class="muted">You withdrew this application.</p><?php endif; ?>
    <ul class="tl">
      <?php foreach ($tl as $s): ?>
        <li class="<?= $s[1] ?>"><span class="pt"><?= $s[1] === 'done' ? '✓' : '' ?></span><b><?= e($s[0]) ?></b>
          <small><?= $statusText[$s[1]] ?><?= $s[2] ? ' · ' . e($s[2]) : '' ?></small></li>
      <?php endforeach; ?>
    </ul>
  </section>

  <section class="card" id="documents">
    <h2 class="card-title">Submitted documents</h2>
    <?php if (!$docs && !$missingDocs): ?><p class="empty">No documents uploaded yet.</p><?php endif; ?>
    <div class="table-wrap">
    <?php if ($docs): ?>
      <table>
        <thead><tr><th>Document</th><th>Uploaded</th><th>OCR check</th><th>Staff check</th></tr></thead>
        <tbody>
        <?php foreach ($docs as $d):
            [$ol, $oc] = $ocrLabel($d); [$vl, $vc] = DOC_STATUS[$d['verification_status']];
            $canFix = in_array($d['verification_status'], ['flagged', 'needs_resubmission'], true) && in_array($app['status'], ['submitted', 'under_review', 'for_compliance'], true); ?>
          <tr>
            <td data-label="Document"><a href="download.php?id=<?= (int)$d['document_id'] ?>" target="_blank"><?= e($d['type_name']) ?></a><small class="block"><?= e($d['original_filename']) ?></small>
              <?php if ($canFix): ?>
                <form class="inline-upload" method="post" enctype="multipart/form-data"><?= csrf_field() ?>
                  <input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="action" value="resubmit"><input type="hidden" name="document_type_id" value="<?= (int)$d['document_type_id'] ?>">
                  <input type="file" name="file" accept=".pdf,.jpg,.jpeg,.png" required aria-label="Corrected <?= e($d['type_name']) ?>">
                  <button class="btn btn-sm btn-gold">Upload corrected file</button>
                </form>
              <?php endif; ?></td>
            <td data-label="Uploaded"><?= fdate($d['uploaded_at']) ?></td>
            <td data-label="OCR check"><span class="badge b-<?= $oc ?>"><?= e($ol) ?></span></td>
            <td data-label="Staff check"><span class="badge b-<?= $vc ?>"><?= e($vl) ?></span></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
    </div>
    <?php if ($app['status'] === 'for_compliance' && $missingDocs): ?>
      <h3 class="sub">Missing documents</h3>
      <?php foreach ($missingDocs as $m): ?>
        <form class="upload" method="post" enctype="multipart/form-data"><?= csrf_field() ?>
          <input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="action" value="resubmit"><input type="hidden" name="document_type_id" value="<?= (int)$m['document_type_id'] ?>">
          <div class="grow"><b><?= e($m['type_name']) ?></b></div>
          <input type="file" name="file" accept=".pdf,.jpg,.jpeg,.png" required aria-label="<?= e($m['type_name']) ?>">
          <button class="btn btn-sm">Upload</button>
        </form>
      <?php endforeach; ?>
    <?php endif; ?>
    <?php if ($app['status'] !== 'draft'): ?><p class="muted small">Submitted applications are locked. You can only replace documents the Scholarship Office asks you to correct.</p><?php endif; ?>
  </section>
</div>

<section class="card spaced">
  <h2 class="card-title">Application history</h2>
  <?php if (!$history): ?><p class="empty">No activity yet.</p><?php endif; ?>
  <?php foreach ($history as $h): ?>
    <div class="alert-row static"><i class="ind c-blue"></i>
      <span><b><?= $h['previous_status'] === $h['new_status'] ? e($h['remarks'] ?: status_label($h['new_status'])) : e(status_label($h['new_status'])) ?></b>
        <small><?= $h['previous_status'] !== $h['new_status'] && $h['remarks'] ? e($h['remarks']) . ' · ' : '' ?><?= fdate($h['changed_at'], 'M j, Y g:i A') ?></small></span></div>
  <?php endforeach; ?>
</section>
<?php include __DIR__ . '/includes/footer.php'; ?>
