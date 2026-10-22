<?php
require_once __DIR__ . '/includes/functions.php';
$me = require_admin();

$q = trim((string)($_GET['q'] ?? ''));
$program = (int)($_GET['program'] ?? 0);
$status = (string)($_GET['status'] ?? '');
$statuses = [
    'active' => 'Active',
    'on_hold' => 'On hold',
    'graduated' => 'Graduated',
    'terminated' => 'Terminated',
    'withdrawn' => 'Withdrawn',
];
if (!isset($statuses[$status])) {
    $status = '';
}

$size = page_size();
$page = max(1, (int)($_GET['page'] ?? 1));
$where = [];
$params = [];
if ($q !== '') {
    $where[] = '(CONCAT(ap.last_name, ", ", ap.first_name) LIKE ? OR ap.student_number LIKE ?)';
    $params[] = "%$q%";
    $params[] = "%$q%";
}
if ($program > 0) {
    $where[] = 'g.scholarship_program_id = ?';
    $params[] = $program;
}
if ($status !== '') {
    $where[] = 'g.status = ?';
    $params[] = $status;
}
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
$from = 'grantees g
    JOIN applicants ap ON ap.applicant_id = g.applicant_id
    JOIN scholarship_programs sp ON sp.scholarship_program_id = g.scholarship_program_id';

$total = scalar($pdo, "SELECT COUNT(*) FROM $from $whereSql", $params);
$page = min($page, max(1, (int)ceil($total / $size)));
$offset = ($page - 1) * $size;
$grantees = rows($pdo, "
    SELECT g.grantee_id, ap.student_number, ap.first_name, ap.middle_name, ap.last_name, ap.suffix,
           sp.program_name, g.status, g.date_awarded
    FROM $from
    $whereSql
    ORDER BY ap.last_name, ap.first_name
    LIMIT $size OFFSET $offset", $params);

$programs = $pdo->query(
    'SELECT scholarship_program_id, program_name FROM scholarship_programs ORDER BY program_name'
)->fetchAll(PDO::FETCH_KEY_PAIR);

$title = 'Grantees';
require __DIR__ . '/includes/header.php';
?>
<form class="bar" method="get">
    <label class="sr" for="q">Search grantees</label>
    <input id="q" name="q" type="search" placeholder="Search by name or student number" value="<?= e($q) ?>">
    <?= select_filter('program', 'Scholarship', $programs, $program) ?>
    <?= select_filter('status', 'Status', $statuses, $status) ?>
    <button class="btn">Search</button>
    <?php if ($q !== '' || $program > 0 || $status !== ''): ?><a class="btn sec" href="grantees.php">Clear Filters</a><?php endif; ?>
</form>

<?php if (!$grantees): ?>
    <div class="box c"><h3>No grantees found</h3><p class="mut">Try different search terms or filters.</p></div>
<?php else: ?>
    <div class="tw"><table>
        <thead><tr><th>Student Number</th><th>Name</th><th>Scholarship</th><th>Date Awarded</th><th>Status</th></tr></thead>
        <tbody>
        <?php foreach ($grantees as $grantee): ?>
            <?php
            $name = $grantee['last_name'] . ', ' . $grantee['first_name'];
            if ($grantee['middle_name']) {
                $name .= ' ' . mb_substr($grantee['middle_name'], 0, 1) . '.';
            }
            if ($grantee['suffix']) {
                $name .= ' ' . $grantee['suffix'];
            }
            $statusLabel = $statuses[$grantee['status']] ?? $grantee['status'];
            ?>
            <tr>
                <td><?= e($grantee['student_number']) ?></td>
                <td><?= e($name) ?></td>
                <td><?= e($grantee['program_name']) ?></td>
                <td><?= e($grantee['date_awarded'] ? date('M j, Y', strtotime($grantee['date_awarded'])) : '—') ?></td>
                <td><?= badge($statusLabel) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
<?php endif; ?>
<?= pager($total, $page, $size) ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
