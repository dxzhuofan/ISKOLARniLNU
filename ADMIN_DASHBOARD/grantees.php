<?php
require_once __DIR__ . '/includes/functions.php';
$me = require_admin();

// Mark a Landbank account as verified
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $st = $pdo->prepare("UPDATE grantee_masterlist SET landbank_status = 'Verified'
                         WHERE masterlist_id = ? AND landbank_status = 'Submitted'");
    $st->execute([(int)($_POST['verify'] ?? 0)]);
    set_flash('Landbank account marked as verified.');
    header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? 'grantees.php'));
    exit;
}

// Search and filters from the address bar
$q       = trim($_GET['q'] ?? '');
$program = $_GET['program'] ?? '';
$reg     = $_GET['reg'] ?? '';
$bank    = $_GET['bank'] ?? '';
$size    = page_size();
$page    = max(1, (int)($_GET['page'] ?? 1));

$where  = [];
$params = [];
if ($q !== '') {
    $where[] = '(CONCAT(m.last_name, ", ", m.first_name) LIKE ? OR m.student_number LIKE ?)';
    array_push($params, "%$q%", "%$q%");
}
if ($program !== '') {
    $where[]  = 'm.scholarship_program_id = ?';
    $params[] = $program;
}
if ($reg === 'Registered') {
    $where[] = 'm.applicant_id IS NOT NULL';
} elseif ($reg === 'Not yet registered') {
    $where[] = 'm.applicant_id IS NULL';
}
if ($bank !== '') {
    $where[]  = 'm.landbank_status = ?';
    $params[] = $bank;
}
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$from = "grantee_masterlist m
    JOIN scholarship_programs sp ON sp.scholarship_program_id = m.scholarship_program_id";

// Total for the pager
$total  = scalar($pdo, "SELECT COUNT(*) FROM $from $whereSql", $params);
$page   = min($page, max(1, (int)ceil($total / $size)));
$offset = ($page - 1) * $size;

// This page of listed grantees
$grantees = rows($pdo, "
    SELECT m.masterlist_id, m.student_number, m.last_name, m.first_name,
           sp.program_name, m.applicant_id, m.landbank_status
    FROM $from
    $whereSql
    ORDER BY m.last_name, m.first_name
    LIMIT $size OFFSET $offset", $params);

$notRegistered = scalar($pdo, 'SELECT COUNT(*) FROM grantee_masterlist WHERE applicant_id IS NULL');
$programs = $pdo->query('SELECT scholarship_program_id, program_name FROM scholarship_programs ORDER BY program_name')->fetchAll(PDO::FETCH_KEY_PAIR);

$title = 'Grantees';
require __DIR__ . '/includes/header.php';
?>
<div class="bar">
    <a class="btn gold" href="grantee_import.php">⬆ Import Grantee List</a>
    <a class="btn sec" href="grantee_import.php#history">Import History</a>
    <span class="bd warn"><?= $notRegistered ?> listed grantees have not registered yet</span>
</div>

<form class="bar" method="get">
    <label class="sr" for="q">Search</label>
    <input id="q" name="q" type="search" placeholder="Search by name or student number" value="<?= e($q) ?>">
    <?= select_filter('program', 'Scholarship', $programs, $program) ?>
    <?= select_filter('reg', 'Registration', ['Registered' => 'Registered', 'Not yet registered' => 'Not yet registered'], $reg) ?>
    <?= select_filter('bank', 'Landbank account', ['Not submitted' => 'Not submitted', 'Submitted' => 'Submitted', 'Verified' => 'Verified'], $bank) ?>
    <button class="btn">Search</button>
    <?php if ($q !== '' || $program !== '' || $reg !== '' || $bank !== ''): ?><a class="btn sec" href="grantees.php">Clear Filters</a><?php endif; ?>
</form>

<?php if (!$grantees): ?>
    <div class="box c"><h3>No grantees match</h3><p class="mut">Try different keywords, clear the filters, or import a grantee list.</p></div>
<?php else: ?>
    <div class="tw"><table>
        <thead><tr><th>Student Number</th><th>Name</th><th>Scholarship</th><th>Registration</th><th>Landbank account</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($grantees as $g): ?>
            <tr>
                <td><?= e($g['student_number']) ?></td>
                <td><?= e($g['last_name'] . ', ' . $g['first_name']) ?></td>
                <td><?= e($g['program_name']) ?></td>
                <td><?= badge($g['applicant_id'] ? 'Registered' : 'Not yet registered') ?></td>
                <td><?= badge($g['landbank_status']) ?></td>
                <td>
                    <?php if ($g['landbank_status'] === 'Submitted'): ?>
                        <form method="post" data-confirm="Mark this Landbank account as verified?">
                            <?= csrf_field() ?>
                            <button class="btn sm" name="verify" value="<?= $g['masterlist_id'] ?>">Verify</button>
                        </form>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
<?php endif; ?>
<?= pager($total, $page, $size) ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
