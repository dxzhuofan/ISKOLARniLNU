<?php
require_once __DIR__ . '/includes/functions.php';
$me = require_admin();

// Search and filters from the address bar
$q       = trim($_GET['q'] ?? '');
$status  = $_GET['status'] ?? '';
$program = $_GET['program'] ?? '';
$flag    = $_GET['flag'] ?? '';
$size    = page_size();
$page    = max(1, (int)($_GET['page'] ?? 1));

$where  = ["a.status <> 'draft'"];
$params = [];
if ($q !== '') {
    $where[]  = '(CONCAT(ap.last_name, ", ", ap.first_name) LIKE ? OR a.application_no LIKE ? OR ap.student_number LIKE ?)';
    array_push($params, "%$q%", "%$q%", "%$q%");
}
if ($status === 'waiting') {
    $where[] = "a.status IN ('submitted', 'under_review')";
} elseif ($status !== '') {
    $where[]  = 'a.status = ?';
    $params[] = $status;
}
if ($program !== '') {
    $where[]  = 'a.scholarship_program_id = ?';
    $params[] = $program;
}
if ($flag !== '') {
    $where[]  = '(' . FLAG_SQL . ') = ?';
    $params[] = $flag;
}
$whereSql = 'WHERE ' . implode(' AND ', $where);

$from = "applications a
    JOIN applicants ap ON ap.applicant_id = a.applicant_id
    JOIN scholarship_programs sp ON sp.scholarship_program_id = a.scholarship_program_id";

// Total for the pager
$total  = scalar($pdo, "SELECT COUNT(*) FROM $from $whereSql", $params);
$page   = min($page, max(1, (int)ceil($total / $size)));
$offset = ($page - 1) * $size;

// This page of applications
$apps = rows($pdo, "
    SELECT a.application_id, a.application_no, a.submitted_at, a.status,
           ap.last_name, ap.first_name, sp.program_name,
           (" . FLAG_SQL . ") AS flag,
           (SELECT m.review_priority FROM ml_predictions m
            WHERE m.application_id = a.application_id
            ORDER BY m.predicted_at DESC LIMIT 1) AS priority
    FROM $from
    $whereSql
    ORDER BY a.submitted_at DESC
    LIMIT $size OFFSET $offset", $params);

$programs = $pdo->query('SELECT scholarship_program_id, program_name FROM scholarship_programs ORDER BY program_name')->fetchAll(PDO::FETCH_KEY_PAIR);
$statuses = ['waiting' => 'Waiting for review'] + array_diff_key(STATUS, ['draft' => 1]);
$flags    = ['OCR mismatch' => 'OCR mismatch', 'Possible duplicate' => 'Possible duplicate', 'Missing document' => 'Missing document'];

$title = 'Applications';
require __DIR__ . '/includes/header.php';
?>
<form class="bar" method="get">
    <label class="sr" for="q">Search</label>
    <input id="q" name="q" type="search" placeholder="Search by name, student number or application number" value="<?= e($q) ?>">
    <?= select_filter('status', 'Status', $statuses, $status) ?>
    <?= select_filter('program', 'Scholarship', $programs, $program) ?>
    <?= select_filter('flag', 'Check needed', $flags, $flag) ?>
    <button class="btn">Search</button>
    <?php if ($q !== '' || $status !== '' || $program !== '' || $flag !== ''): ?><a class="btn sec" href="applications.php">Clear Filters</a><?php endif; ?>
</form>

<?php if (!$apps): ?>
    <div class="box c"><h3>No applications match</h3><p class="mut">Try different keywords or clear the filters.</p></div>
<?php else: ?>
    <div class="tw"><table>
        <thead><tr><th>Application</th><th>Student</th><th>Scholarship</th><th>Submitted</th><th>Status</th><th>Review priority</th><th>Check needed</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($apps as $a): ?>
            <tr>
                <td><?= e($a['application_no']) ?></td>
                <td><?= e($a['last_name'] . ', ' . $a['first_name']) ?></td>
                <td><?= e($a['program_name']) ?></td>
                <td><?= e($a['submitted_at'] ? date('Y-m-d', strtotime($a['submitted_at'])) : '—') ?></td>
                <td><?= status_badge($a['status']) ?></td>
                <td><?= $a['priority'] ? badge($a['priority']) : '—' ?></td>
                <td><?= e($a['flag'] ?: '—') ?></td>
                <td><a class="btn sm" href="application_view.php?id=<?= $a['application_id'] ?>">Review</a></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
<?php endif; ?>
<?= pager($total, $page, $size) ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
