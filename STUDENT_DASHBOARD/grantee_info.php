<?php
require_once __DIR__ . '/includes/functions.php';
$me = require_student();
$aid = (int)$me['applicant_id'];

$st = $pdo->prepare('SELECT g.*, p.program_name, p.provider_name, p.benefit_amount, p.benefit_description
    FROM grantees g JOIN scholarship_programs p ON p.scholarship_program_id = g.scholarship_program_id
    WHERE g.applicant_id = ? ORDER BY (g.status = \'active\') DESC, g.date_awarded DESC');
$st->execute([$aid]);
$grants = $st->fetchAll();

$terms = []; $disb = [];
if ($grants) {
    $ids = array_column($grants, 'grantee_id');
    $in = implode(',', array_fill(0, count($ids), '?'));
    // Only renewal status is shown to students (no grades).
    $t = $pdo->prepare("SELECT grantee_id, school_year, semester, renewal_status, remarks FROM grantee_terms WHERE grantee_id IN ($in)
                        ORDER BY school_year DESC, FIELD(semester,'Summer','2nd','1st')");
    $t->execute($ids);
    foreach ($t->fetchAll() as $r) $terms[$r['grantee_id']][] = $r;
    $d = $pdo->prepare("SELECT * FROM disbursements WHERE grantee_id IN ($in) ORDER BY school_year DESC, FIELD(semester,'Summer','2nd','1st'), disbursement_id DESC");
    $d->execute($ids);
    foreach ($d->fetchAll() as $r) $disb[$r['grantee_id']][] = $r;
}
$gLabel = ['active' => ['Active', 'green'], 'on_hold' => ['On hold', 'orange'], 'graduated' => ['Graduated', 'blue'], 'terminated' => ['Terminated', 'red'], 'withdrawn' => ['Withdrawn', 'gray']];
$rLabel = ['pending' => ['Pending', 'gray'], 'renewed' => ['Renewed', 'green'], 'not_renewed' => ['Not renewed', 'red'], 'completed' => ['Completed', 'blue']];
$dLabel = ['pending' => ['Processing', 'blue'], 'released' => ['Released', 'green'], 'on_hold' => ['On hold', 'orange'], 'returned' => ['Returned', 'red']];

$pageTitle = 'Grantee Information'; $active = 'grantee';
include __DIR__ . '/includes/header.php';
?>
<section class="hero">
  <div><h1 class="serif">Grantee Information</h1><p class="muted">Official grant records entered and verified by the Scholarship Office. These details cannot be edited here.</p></div>
</section>

<?php if (!$grants): ?>
  <div class="card">
    <p class="empty">No grantee record is linked to your account yet. If CHED selected you for a program such as TES or TDP, the Scholarship Office will add your record after verification.</p>
    <p class="center"><a class="btn" href="scholarships.php">Browse scholarships</a></p></div>
<?php endif; ?>

<?php foreach ($grants as $g):
    [$gl, $gc] = $gLabel[$g['status']] ?? [$g['status'], 'gray'];
    $benefit = trim(($g['benefit_amount'] !== null ? money($g['benefit_amount']) : '') . ($g['benefit_description'] ? ' ' . $g['benefit_description'] : '')); ?>
  <section class="card spaced">
    <div class="split"><div><h2 class="serif"><?= e($g['program_name']) ?></h2><p class="muted"><?= e($g['provider_name']) ?></p></div>
      <span class="badge b-<?= $gc ?>"><?= e($gl) ?></span></div>
    <dl class="kv grid-kv">
      <div><dt>Date awarded</dt><dd><?= fdate($g['date_awarded']) ?></dd></div>
      <div><dt>Benefit</dt><dd><?= $benefit ? e($benefit) : '—' ?></dd></div>
      <div><dt>Student number</dt><dd><?= e($me['student_number']) ?></dd></div>
      <div><dt>Remarks</dt><dd><?= e($g['remarks'] ?: '—') ?></dd></div>
    </dl>

    <div class="two two-even">
      <div>
        <h3 class="sub">Renewal status per term</h3>
        <?php if (empty($terms[$g['grantee_id']])): ?><p class="muted">No term records yet.</p><?php else: ?>
        <div class="table-wrap"><table><thead><tr><th>Term</th><th>Renewal</th><th>Remarks</th></tr></thead><tbody>
          <?php foreach ($terms[$g['grantee_id']] as $t): [$rl, $rc] = $rLabel[$t['renewal_status']] ?? [$t['renewal_status'], 'gray']; ?>
            <tr><td data-label="Term">AY <?= e($t['school_year']) ?>, <?= e($t['semester']) ?> sem</td><td data-label="Renewal"><span class="badge b-<?= $rc ?>"><?= e($rl) ?></span></td><td data-label="Remarks"><?= e($t['remarks'] ?: '—') ?></td></tr>
          <?php endforeach; ?></tbody></table></div>
        <?php endif; ?>
      </div>
      <div>
        <h3 class="sub">Disbursement history</h3>
        <?php if (empty($disb[$g['grantee_id']])): ?><p class="muted">No disbursements recorded yet.</p><?php else: ?>
        <div class="table-wrap"><table><thead><tr><th>Term</th><th>Amount</th><th>Status</th><th>Reference</th></tr></thead><tbody>
          <?php foreach ($disb[$g['grantee_id']] as $r): [$dl, $dc] = $dLabel[$r['status']] ?? [$r['status'], 'gray']; ?>
            <tr><td data-label="Term">AY <?= e($r['school_year']) ?>, <?= e($r['semester']) ?> sem</td><td data-label="Amount"><?= money($r['amount']) ?></td>
                <td data-label="Status"><span class="badge b-<?= $dc ?>"><?= e($dl) ?></span><?= $r['release_date'] ? '<small class="block">' . fdate($r['release_date']) . '</small>' : '' ?></td>
                <td data-label="Reference"><?= e($r['reference_no'] ?: '—') ?></td></tr>
          <?php endforeach; ?></tbody></table></div>
        <?php endif; ?>
      </div>
    </div>
  </section>
<?php endforeach; ?>
<?php include __DIR__ . '/includes/footer.php'; ?>
