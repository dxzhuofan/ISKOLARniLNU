<?php
require_once __DIR__ . '/includes/functions.php';
$me = require_admin();
$catalog = require __DIR__ . '/../Registration_and_Login(UIUX)/academic_catalog.php';

// Search and filters from the address bar
$q       = trim($_GET['q'] ?? '');
$college = $_GET['college'] ?? '';
$year    = $_GET['year'] ?? '';
$size    = page_size();
$page    = max(1, (int)($_GET['page'] ?? 1));

$where  = [];
$params = [];
if ($q !== '') {
    $where[]  = '(CONCAT(ap.last_name, ", ", ap.first_name) LIKE ? OR ap.student_number LIKE ?)';
    $params[] = "%$q%";
    $params[] = "%$q%";
}
if ($college !== '') {
    $where[]  = 'COALESCE(ac.college_code, c.college_code) COLLATE utf8mb4_unicode_ci = ?';
    $params[] = $college;
}
if ($year !== '') {
    $where[]  = 'COALESCE(ac.year_level, ap.year_level) = ?';
    $params[] = $year;
}
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

// Total for the pager
$joins = 'FROM applicants ap
          LEFT JOIN applicant_academic_profiles ac ON ac.applicant_id = ap.applicant_id
          LEFT JOIN colleges c ON c.college_id = ap.college_id';
$total = scalar($pdo, "SELECT COUNT(*) $joins $whereSql", $params);
$page  = min($page, max(1, (int)ceil($total / $size)));
$offset = ($page - 1) * $size;

// This page of students
$students = rows($pdo, "
    SELECT ap.student_number, ap.last_name, ap.first_name,
           COALESCE(ac.college_code, c.college_code) AS college_code,
           COALESCE(ac.year_level, ap.year_level) AS year_level,
           ac.program_code, ac.specialization,
           (SELECT GROUP_CONCAT(sp.program_code SEPARATOR ', ')
            FROM grantees g
            JOIN scholarship_programs sp ON sp.scholarship_program_id = g.scholarship_program_id
            WHERE g.applicant_id = ap.applicant_id) AS scholarships
    $joins
    $whereSql
    ORDER BY ap.last_name, ap.first_name
    LIMIT $size OFFSET $offset", $params);

$colleges = [];
foreach ($catalog as $code => $collegeData) $colleges[$code] = $collegeData['name'];
$legacyColleges = $pdo->query('SELECT college_code, college_code FROM colleges ORDER BY college_code')->fetchAll(PDO::FETCH_KEY_PAIR);
foreach ($legacyColleges as $code => $label) if (!isset($colleges[$code])) $colleges[$code] = $label;

$title = 'Student Records';
require __DIR__ . '/includes/header.php';
?>
<form class="bar" method="get">
    <label class="sr" for="q">Search</label>
    <input id="q" name="q" type="search" placeholder="Search by name or student number" value="<?= e($q) ?>">
    <?= select_filter('college', 'College', $colleges, $college) ?>
    <?= select_filter('year', 'Year level', [1 => 1, 2 => 2, 3 => 3, 4 => 4, 5 => 5], $year) ?>
    <button class="btn">Search</button>
    <?php if ($q !== '' || $college !== '' || $year !== ''): ?><a class="btn sec" href="students.php">Clear Filters</a><?php endif; ?>
</form>

<?php if (!$students): ?>
    <div class="box c"><h3>No students match your search</h3><p class="mut">Try different keywords or clear the filters.</p></div>
<?php else: ?>
    <div class="tw"><table>
        <thead><tr><th>Student Number</th><th>Name</th><th>College</th><th>Program</th><th>Year</th><th>Scholarship</th></tr></thead>
        <tbody>
        <?php foreach ($students as $s): ?>
            <tr>
                <td><?= e($s['student_number']) ?></td>
                <td><?= e($s['last_name'] . ', ' . $s['first_name']) ?></td>
                <td><?= e($catalog[$s['college_code']]['name'] ?? $s['college_code']) ?></td>
                <td><?php
                    $programData = $catalog[$s['college_code']]['programs'][$s['program_code']] ?? null;
                    echo e($programData['name'] ?? '');
                    if ($s['specialization']) echo '<br>' . e($s['specialization']);
                ?></td>
                <td><?= e($s['year_level']) ?></td>
                <td><?= e($s['scholarships'] ?: 'None') ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
<?php endif; ?>
<?= pager($total, $page, $size) ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
