<?php
require_once __DIR__ . '/includes/functions.php';
$me = require_student();
$aid = (int)$me['applicant_id'];

$st = $pdo->prepare('SELECT a.application_id, a.application_no, a.status, a.submitted_at, a.updated_at, p.program_name, ap.school_year, ap.semester
    FROM applications a
    JOIN scholarship_programs p ON p.scholarship_program_id = a.scholarship_program_id
    JOIN application_periods ap ON ap.period_id = a.period_id
    WHERE a.applicant_id = ?');
$st->execute([$aid]);
$all = $st->fetchAll();

$fl = $pdo->prepare("SELECT DISTINCT d.application_id FROM application_documents d JOIN applications a ON a.application_id = d.application_id
    WHERE a.applicant_id = ? AND d.is_current = 1 AND d.verification_status IN ('flagged','needs_resubmission')
    AND a.status IN ('submitted','under_review','for_compliance')");
$fl->execute([$aid]);
$flagged = array_flip($fl->fetchAll(PDO::FETCH_COLUMN));
$needsAction = fn($a) => $a['status'] === 'for_compliance' || isset($flagged[$a['application_id']]);

$counts = ['all' => count($all), 'review' => 0, 'action' => 0, 'approved' => 0];
foreach ($all as $a) {
    if (in_array($a['status'], ['submitted', 'under_review'])) $counts['review']++;
    if ($needsAction($a)) $counts['action']++;
    if ($a['status'] === 'approved') $counts['approved']++;
}

$f = $_GET['f'] ?? 'all';      if (!isset($counts[$f])) $f = 'all';
$q = trim($_GET['q'] ?? '');
$sort = $_GET['sort'] ?? 'new'; if (!in_array($sort, ['new', 'old', 'name'], true)) $sort = 'new';
$statusF = $_GET['status'] ?? '';

$rows = array_filter($all, function ($a) use ($f, $q, $statusF, $needsAction) {
    if ($f === 'review' && !in_array($a['status'], ['submitted', 'under_review'])) return false;
    if ($f === 'action' && !$needsAction($a)) return false;
    if ($f === 'approved' && $a['status'] !== 'approved') return false;
    if ($statusF !== '' && $a['status'] !== $statusF) return false;
    if ($q !== '' && stripos($a['program_name'] . ' ' . $a['application_no'], $q) === false) return false;
    return true;
});
usort($rows, function ($a, $b) use ($sort) {
    if ($sort === 'name') return strcasecmp($a['program_name'], $b['program_name']);
    $c = strtotime($a['updated_at']) <=> strtotime($b['updated_at']);
    return $sort === 'old' ? $c : -$c;
});

$pageTitle = 'My Applications'; $active = 'applications';
include __DIR__ . '/includes/header.php';
$tabs = ['all' => 'All Applications', 'review' => 'Under Review', 'action' => 'Action Required', 'approved' => 'Approved'];
?>
<section class="hero">
  <div><h1 class="serif">My Applications</h1><p class="muted">Track every scholarship application and respond to requests from the Scholarship Office.</p></div>
</section>

<div class="summary">
  <?php foreach ($tabs as $k => $label): ?>
    <a class="card sum <?= $f === $k ? 'on' : '' ?>" href="?f=<?= $k ?>"><b><?= $counts[$k] ?></b><span><?= $label ?></span></a>
  <?php endforeach; ?>
</div>

<form class="filters" method="get">
  <input type="hidden" name="f" value="<?= e($f) ?>">
  <input type="search" name="q" value="<?= e($q) ?>" placeholder="Search by reference or scholarship" aria-label="Search applications">
  <select name="status" aria-label="Status">
    <option value="">Any status</option>
    <?php foreach (STATUS as $k => [$lab]): ?><option value="<?= $k ?>" <?= $statusF === $k ? 'selected' : '' ?>><?= e($lab) ?></option><?php endforeach; ?>
  </select>
  <select name="sort" aria-label="Sort">
    <option value="new" <?= $sort === 'new' ? 'selected' : '' ?>>Latest update first</option>
    <option value="old" <?= $sort === 'old' ? 'selected' : '' ?>>Oldest update first</option>
    <option value="name" <?= $sort === 'name' ? 'selected' : '' ?>>Scholarship name</option>
  </select>
  <button class="btn btn-alt" type="submit">Apply filters</button>
</form>

<div class="card table-wrap">
<?php if (!$rows): ?>
  <p class="empty"><?= $all ? 'No applications match your filters.' : 'You have no applications yet.' ?> <a href="scholarships.php">Browse scholarships</a></p>
<?php else: ?>
  <table>
    <thead><tr><th>Reference</th><th>Scholarship</th><th>Submitted</th><th>Period</th><th>Status</th><th>Last update</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($rows as $a): $isDraft = $a['status'] === 'draft'; ?>
      <tr>
        <td data-label="Reference"><?= e($a['application_no']) ?></td>
        <td data-label="Scholarship"><b><?= e($a['program_name']) ?></b></td>
        <td data-label="Submitted"><?= fdate($a['submitted_at']) ?></td>
        <td data-label="Period">AY <?= e($a['school_year']) ?>, <?= e($a['semester']) ?> sem</td>
        <td data-label="Status"><?= badge($a['status']) ?></td>
        <td data-label="Last update"><?= fdate($a['updated_at']) ?></td>
        <td><a class="btn btn-sm <?= $isDraft || $needsAction($a) ? 'btn-gold' : 'btn-alt' ?>" href="<?= $isDraft ? 'apply.php?app=' : 'application_view.php?id=' ?><?= (int)$a['application_id'] ?>"><?= $isDraft ? 'Continue draft' : ($needsAction($a) ? 'Fix documents' : 'View') ?></a></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
<?php endif; ?>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
