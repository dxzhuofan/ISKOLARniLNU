<?php
require_once __DIR__ . '/includes/functions.php';
$me = require_admin();

// Summary cards
$totalStudents  = scalar($pdo, 'SELECT COUNT(*) FROM applicants');
$pendingApps    = scalar($pdo, "SELECT COUNT(*) FROM applications WHERE status = 'submitted'");
$listedGrantees = scalar($pdo, 'SELECT COUNT(*) FROM grantee_masterlist');

// Needs Attention (each count opens the matching list with its filter applied)
$waiting = scalar($pdo, "SELECT COUNT(*) FROM applications WHERE status IN ('submitted', 'under_review')");

$flagCount = function (string $flag) use ($pdo): int {
    return scalar($pdo,
        "SELECT COUNT(*) FROM applications a WHERE a.status <> 'draft' AND (" . FLAG_SQL . ") = ?",
        [$flag]);
};

$attention = [
    ['Applications waiting for review',           $waiting,                                   'applications.php?status=waiting'],
    ['Missing or unreadable documents',           $flagCount('Missing document'),             'applications.php?flag=Missing+document'],
    ['OCR results needing manual verification',   $flagCount('OCR mismatch'),                 'applications.php?flag=OCR+mismatch'],
    ['Possible duplicate records',                $flagCount('Possible duplicate'),           'applications.php?flag=Possible+duplicate'],
    ['Grantee imports containing errors',         scalar($pdo, 'SELECT COUNT(*) FROM grantee_imports WHERE error_rows > 0'), 'grantee_import.php#history'],
    ['Landbank accounts waiting for verification', scalar($pdo, "SELECT COUNT(*) FROM grantee_masterlist WHERE landbank_status = 'Submitted'"), 'grantees.php?bank=Submitted'],
];
$needsAttention = array_sum(array_column($attention, 1)) - $waiting;

// Recent activity
$activity = rows($pdo, "
    SELECT h.new_status, h.changed_at, ap.last_name, ap.first_name
    FROM application_status_history h
    JOIN applications a ON a.application_id = h.application_id
    JOIN applicants ap  ON ap.applicant_id = a.applicant_id
    ORDER BY h.changed_at DESC
    LIMIT 5");

$title = 'Dashboard';
require __DIR__ . '/includes/header.php';
?>
<div class="cards">
    <a class="card" href="students.php"><small>Total Student Records</small><b><?= number_format($totalStudents) ?></b></a>
    <a class="card" href="applications.php?status=submitted"><small>Pending Applications</small><b><?= $pendingApps ?></b></a>
    <a class="card" href="grantees.php"><small>Active Grantees</small><b><?= number_format($listedGrantees) ?></b></a>
    <a class="card" href="applications.php?flag=OCR+mismatch"><small>Items Requiring Attention</small><b><?= $needsAttention ?></b></a>
</div>

<div class="grid2">
    <section class="box">
        <h2>Needs Attention</h2>
        <?php foreach ($attention as [$label, $count, $link]): ?>
            <a class="item" href="<?= $link ?>"><?= e($label) ?><b><?= $count ?></b><span class="btn sm sec">Open</span></a>
        <?php endforeach; ?>
    </section>

    <section class="box">
        <h2>Recent Activity</h2>
        <?php foreach ($activity as $a): ?>
            <div class="item">
                <?= e($a['last_name'] . ', ' . $a['first_name'] . ' — ' . (STATUS[$a['new_status']] ?? $a['new_status'])) ?>
                <span class="mut" style="margin-left:auto"><?= e(date('M j, g:i A', strtotime($a['changed_at']))) ?></span>
            </div>
        <?php endforeach; ?>
        <?php if (!$activity): ?><p class="mut">No activity yet.</p><?php endif; ?>
    </section>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
