<?php
require_once __DIR__ . '/includes/functions.php';
$me = require_student();
$aid = (int)$me['applicant_id'];

// Applications
$st = $pdo->prepare('SELECT a.application_id, a.application_no, a.status, a.submitted_at, a.updated_at,
        p.program_name, p.benefit_amount, p.benefit_description, ap.school_year, ap.semester
    FROM applications a
    JOIN scholarship_programs p ON p.scholarship_program_id = a.scholarship_program_id
    JOIN application_periods ap ON ap.period_id = a.period_id
    WHERE a.applicant_id = ? ORDER BY a.updated_at DESC, a.application_id DESC');
$st->execute([$aid]);
$apps = $st->fetchAll();
$cnt = ['active' => 0, 'review' => 0, 'action' => 0, 'draft' => 0];
foreach ($apps as $a) {
    if (in_array($a['status'], ['submitted', 'under_review', 'for_compliance'])) $cnt['active']++;
    if (in_array($a['status'], ['submitted', 'under_review'])) $cnt['review']++;
    if ($a['status'] === 'for_compliance') $cnt['action']++;
    if ($a['status'] === 'draft') $cnt['draft']++;
}

// Approved grants
$st = $pdo->prepare("SELECT g.grantee_id, g.date_awarded, p.program_name,
        (SELECT t.school_year FROM grantee_terms t WHERE t.grantee_id = g.grantee_id
         ORDER BY t.school_year DESC, FIELD(t.semester,'1st','2nd','Summer') DESC LIMIT 1) AS latest_sy
    FROM grantees g JOIN scholarship_programs p ON p.scholarship_program_id = g.scholarship_program_id
    WHERE g.applicant_id = ? AND g.status = 'active' ORDER BY g.date_awarded DESC");
$st->execute([$aid]);
$grants = $st->fetchAll();

// Pending documents (flagged by staff)
$st = $pdo->prepare("SELECT d.application_id FROM application_documents d JOIN applications a ON a.application_id = d.application_id
    WHERE a.applicant_id = ? AND d.is_current = 1 AND d.verification_status IN ('flagged','needs_resubmission')
    AND a.status NOT IN ('withdrawn','rejected','approved')");
$st->execute([$aid]);
$pendingDocs = $st->fetchAll(PDO::FETCH_COLUMN);
$pendingLink = $pendingDocs ? 'application_view.php?id=' . (int)$pendingDocs[0] . '#documents' : 'my_applications.php';

// Deadlines
$st = $pdo->prepare("SELECT p.scholarship_program_id, p.program_name, p.benefit_amount, p.benefit_description,
        ap.period_id, ap.open_date, ap.close_date, ap.status AS period_status, ap.school_year, ap.semester, x.status AS my_status, x.application_id AS my_app
    FROM application_periods ap
    JOIN scholarship_programs p ON p.scholarship_program_id = ap.scholarship_program_id AND p.is_active = 1
    LEFT JOIN applications x ON x.scholarship_program_id = ap.scholarship_program_id AND x.period_id = ap.period_id AND x.applicant_id = ?
    WHERE ap.status IN ('open','closed') AND ap.close_date >= NOW() - INTERVAL 30 DAY
    ORDER BY (ap.status = 'open' AND ap.close_date >= NOW()) DESC, ap.close_date ASC LIMIT 6");
$st->execute([$aid]);
$deadlines = $st->fetchAll();

// Alerts
$st = $pdo->prepare("SELECT notification_id, application_id, notification_type, title, message, is_read, created_at FROM notifications
    WHERE user_id = ? AND channel = 'in_app' ORDER BY created_at DESC, notification_id DESC LIMIT 7");
$st->execute([$_SESSION['user_id']]);
$alerts = $st->fetchAll();

$pageTitle = 'Dashboard'; $active = 'dashboard';
include __DIR__ . '/includes/header.php';
?>
<section class="hero">
  <div>
    <h1 class="serif">Welcome back, <?= e($me['first_name']) ?>.</h1>
    <p class="muted">Track your scholarship applications and grants in one place.</p>
  </div>
  <div class="hero-actions">
    <a class="btn btn-alt" href="scholarships.php">Browse Scholarships</a>
    <a class="btn" href="my_applications.php">Track Status</a>
  </div>
</section>

<section class="grid3">
  <a class="card stat" href="my_applications.php">
    <small>Active Applications</small>
    <div class="num"><?= $cnt['active'] ?></div>
    <small><?= $cnt['review'] ?> under review · <?= $cnt['action'] ?> need action<?= $cnt['draft'] ? ' · ' . $cnt['draft'] . ' draft' . ($cnt['draft'] > 1 ? 's' : '') : '' ?></small>
  </a>
  <a class="card stat" href="grantee_info.php">
    <small>Approved Grants</small>
    <div class="num"><?= count($grants) ?></div>
    <?php if ($grants): foreach (array_slice($grants, 0, 2) as $g): ?>
      <small><?= e($g['program_name']) ?><?= $g['latest_sy'] ? ' · AY ' . e($g['latest_sy']) : '' ?></small>
    <?php endforeach; else: ?><small>No active grants yet</small><?php endif; ?>
  </a>
  <a class="card stat" href="<?= e($pendingLink) ?>">
    <small>Pending Documents</small>
    <div class="num"><?= count($pendingDocs) ?></div>
    <small><?= $pendingDocs ? 'Need correction · <span class="link">Upload corrected files</span>' : 'Nothing to fix right now' ?></small>
  </a>
</section>

<section class="two">
  <div class="card">
    <h2 class="card-title">Recent Applications <a href="my_applications.php">View All</a></h2>
    <?php if (!$apps): ?>
      <p class="empty">You have not applied yet. <a href="scholarships.php">Browse scholarships</a> to start an application.</p>
    <?php endif; ?>
    <?php foreach (array_slice($apps, 0, 5) as $a):
        $benefit = $a['benefit_amount'] !== null ? money($a['benefit_amount']) : $a['benefit_description']; ?>
      <div class="row">
        <div class="ico"><?= e(prog_initials($a['program_name'])) ?></div>
        <div class="grow">
          <b><?= e($a['program_name']) ?></b>
          <small><?= $a['submitted_at'] ? 'Submitted ' . fdate($a['submitted_at']) : 'Not yet submitted' ?><?= $benefit ? ' · ' . e($benefit) : '' ?> · <?= e($a['application_no']) ?></small>
        </div>
        <?= badge($a['status']) ?>
        <a class="link" href="<?= $a['status'] === 'draft' ? 'apply.php?app=' . (int)$a['application_id'] : 'application_view.php?id=' . (int)$a['application_id'] ?>"><?= $a['status'] === 'draft' ? 'Continue' : 'View Details' ?></a>
      </div>
    <?php endforeach; ?>
  </div>

  <aside class="card profile-card">
    <span class="avatar avatar-lg"><?= e(initials($me)) ?></span>
    <h2 class="serif"><?= e(full_name($me)) ?></h2>
    <p class="muted"><?= e($me['student_number']) ?></p>
    <dl>
      <div><dt>Email</dt><dd><?= e($me['email']) ?></dd></div>
      <div><dt>Grantee status</dt><dd><?= $grants ? 'Active grantee · ' . e($grants[0]['program_name']) : 'Not a grantee yet' ?></dd></div>
    </dl>
    <a class="btn btn-alt btn-block" href="profile.php">Edit Profile</a>
  </aside>
</section>

<section class="card spaced">
  <h2 class="card-title">Approaching Deadlines <a href="scholarships.php">View All Scholarships</a></h2>
  <?php if (!$deadlines): ?><p class="empty">No application periods are open right now. Check back soon.</p><?php endif; ?>
  <?php foreach ($deadlines as $d):
      [$state, $days] = period_state($d['open_date'], $d['close_date'], $d['period_status']);
      $benefit = $d['benefit_amount'] !== null ? money($d['benefit_amount']) : $d['benefit_description']; ?>
    <div class="row">
      <div class="ico"><?= e(prog_initials($d['program_name'])) ?></div>
      <div class="grow">
        <b><?= e($d['program_name']) ?></b>
        <small><?= $benefit ? e($benefit) . ' · ' : '' ?>Deadline <?= fdate($d['close_date']) ?></small>
      </div>
      <span class="days <?= days_class($state, $days) ?>"><?= e(days_text($state, $days)) ?></span>
      <?php if ($d['my_status']): ?>
        <?= badge($d['my_status']) ?>
      <?php elseif ($state === 'open'): ?>
        <a class="btn btn-sm" href="scholarship_view.php?id=<?= (int)$d['scholarship_program_id'] ?>#apply">Apply</a>
      <?php else: ?>
        <span class="btn btn-sm btn-off" aria-disabled="true"><?= $state === 'closed' ? 'Closed' : 'Not open yet' ?></span>
      <?php endif; ?>
    </div>
  <?php endforeach; ?>
</section>

<section class="card spaced" id="alerts">
  <h2 class="card-title">Recent Alerts <button type="button" class="link-btn" data-mark-all>Mark all as read</button></h2>
  <?php if (!$alerts): ?><p class="empty">No alerts yet. We will notify you when something changes.</p><?php endif; ?>
  <?php foreach ($alerts as $n): ?>
    <a class="alert-row <?= $n['is_read'] ? 'read' : '' ?>" href="<?= $n['application_id'] ? 'application_view.php?id=' . (int)$n['application_id'] : '#alerts' ?>">
      <i class="ind c-<?= notif_color($n['notification_type']) ?>"></i>
      <span><b><?= e($n['title']) ?></b><small><?= e($n['message']) ?></small><small><?= fdate($n['created_at'], 'M j, Y g:i A') ?></small></span>
    </a>
  <?php endforeach; ?>
</section>
<?php include __DIR__ . '/includes/footer.php'; ?>
