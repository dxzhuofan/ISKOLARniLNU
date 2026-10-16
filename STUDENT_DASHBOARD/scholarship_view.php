<?php
require_once __DIR__ . '/includes/functions.php';
$me = require_student();
$aid = (int)$me['applicant_id'];
$pid = (int)($_GET['id'] ?? 0);

$st = $pdo->prepare('SELECT * FROM scholarship_programs WHERE scholarship_program_id = ? AND is_active = 1');
$st->execute([$pid]);
$p = $st->fetch();
if (!$p) { flash('error', 'That scholarship could not be found.'); redirect('scholarships.php'); }

$st = $pdo->prepare("SELECT * FROM application_periods WHERE scholarship_program_id = ? AND status IN ('open','closed')
                     ORDER BY (status = 'open') DESC, close_date DESC LIMIT 1");
$st->execute([$pid]);
$per = $st->fetch();
if ($per) { [$state, $days] = period_state($per['open_date'], $per['close_date'], $per['status']); } else { $state = 'closed'; $days = 0; }

$st = $pdo->prepare('SELECT r.is_required, r.instructions, dt.type_name, dt.description FROM program_requirements r
                     JOIN document_types dt ON dt.document_type_id = r.document_type_id
                     WHERE r.scholarship_program_id = ? ORDER BY r.is_required DESC, dt.type_name');
$st->execute([$pid]);
$reqs = $st->fetchAll();
$required = array_filter($reqs, fn($r) => $r['is_required']);
$optional = array_filter($reqs, fn($r) => !$r['is_required']);

$mine = null;
if ($per) {
    $st = $pdo->prepare('SELECT application_id, status, application_no FROM applications WHERE applicant_id = ? AND scholarship_program_id = ? AND period_id = ?');
    $st->execute([$aid, $pid, $per['period_id']]);
    $mine = $st->fetch();
}
$benefit = trim(($p['benefit_amount'] !== null ? money($p['benefit_amount']) : '') . ($p['benefit_description'] ? ' ' . $p['benefit_description'] : ''));

$pageTitle = $p['program_name']; $active = 'scholarships';
include __DIR__ . '/includes/header.php';
?>
<a class="back" href="scholarships.php">← Back to scholarships</a>
<div class="two two-wide">
  <article class="card">
    <h1 class="serif"><?= e($p['program_name']) ?></h1>
    <p class="muted"><?= e($p['provider_name']) ?></p>
    <p><?= nl2br(e($p['description'] ?: 'The Scholarship Office will post the full description of this program soon.')) ?></p>

    <h2 class="sub">Eligibility requirements</h2>
    <p><?= nl2br(e($p['eligibility_notes'] ?: 'Eligibility details will be posted by the Scholarship Office.')) ?></p>

    <h2 class="sub">Required documents</h2>
    <?php if (!$required): ?><p class="muted">No required documents are listed yet.</p><?php endif; ?>
    <ul class="doc-list">
      <?php foreach ($required as $r): ?>
        <li><b><?= e($r['type_name']) ?></b><small><?= e($r['instructions'] ?: $r['description']) ?></small></li>
      <?php endforeach; ?>
    </ul>
    <?php if ($optional): ?>
      <h2 class="sub">Optional documents</h2>
      <ul class="doc-list">
        <?php foreach ($optional as $r): ?><li><b><?= e($r['type_name']) ?></b><small><?= e($r['instructions'] ?: $r['description']) ?></small></li><?php endforeach; ?>
      </ul>
    <?php endif; ?>

    <h2 class="sub">How to apply</h2>
    <p>Check the requirements, upload clear PDF, JPG or PNG copies (up to 5 MB each), review your answers, then submit. You can save a draft and finish later while the application period is open. After you submit, your application is locked unless the Scholarship Office asks for a correction.</p>
  </article>

  <aside class="card side">
    <h2 class="card-title">At a glance</h2>
    <dl class="kv">
      <div><dt>Benefits and coverage</dt><dd><?= $benefit ? e($benefit) : 'To be announced' ?></dd></div>
      <div><dt>Application period</dt><dd><?= $per ? fdate($per['open_date']) . ' – ' . fdate($per['close_date']) : 'No application period yet' ?></dd></div>
      <div><dt>Period status</dt><dd><?php if ($per): ?>AY <?= e($per['school_year']) ?>, <?= e($per['semester']) ?> sem · <span class="days <?= days_class($state, $days) ?>"><?= e(days_text($state, $days)) ?></span><?php else: ?>Closed<?php endif; ?></dd></div>
      <div><dt>Your application</dt><dd><?= $mine ? badge($mine['status']) . '<br><small>' . e($mine['application_no']) . '</small>' : 'Not applied' ?></dd></div>
    </dl>
    <?php if ($mine && $mine['status'] === 'draft' && $state === 'open'): ?>
      <a class="btn btn-block" href="apply.php?app=<?= (int)$mine['application_id'] ?>">Continue your draft</a>
    <?php elseif ($mine): ?>
      <a class="btn btn-alt btn-block" href="application_view.php?id=<?= (int)$mine['application_id'] ?>">View my application</a>
      <p class="muted small">You already applied to this program for this period.</p>
    <?php elseif ($state === 'open'): ?>
      <button class="btn btn-block" type="button" id="apply" data-open-modal="applyModal">Apply Now</button>
    <?php else: ?>
      <button class="btn btn-block" type="button" disabled><?= $state === 'upcoming' ? 'Not open yet' : 'Applications closed' ?></button>
    <?php endif; ?>
  </aside>
</div>

<?php if (!$mine && $state === 'open'): ?>
<div class="modal" id="applyModal" role="dialog" aria-modal="true" aria-labelledby="applyTitle" hidden>
  <form class="card modal-card" method="post" action="apply.php">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="start">
    <input type="hidden" name="program_id" value="<?= (int)$pid ?>">
    <h2 class="serif" id="applyTitle">Before you apply</h2>
    <p class="muted">Tick each item to confirm you are ready. You will upload the files in the next steps.</p>
    <?php foreach ($required as $r): ?>
      <label class="check"><input type="checkbox" data-gate> <?= e($r['type_name']) ?></label>
    <?php endforeach; ?>
    <label class="check"><input type="checkbox" data-gate> I confirm the information I provide is true and accurate.</label>
    <div class="modal-actions">
      <button class="btn btn-alt" type="button" data-close-modal>Cancel</button>
      <button class="btn" type="submit" id="applyGo" disabled>Start application</button>
    </div>
  </form>
</div>
<?php endif; ?>
<?php include __DIR__ . '/includes/footer.php'; ?>
