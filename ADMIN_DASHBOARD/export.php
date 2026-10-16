<?php
require_once __DIR__ . '/includes/functions.php';
$me = require_admin();

// Columns staff can choose: key => [heading in the file, SQL]
$statusCase = 'CASE a.status';
foreach (STATUS as $key => $label) {
    $statusCase .= " WHEN '$key' THEN '$label'";
}
$statusCase .= ' END';

$columns = [
    'number'         => ['Application No.',        'a.application_no'],
    'student_number' => ['Student Number',         'ap.student_number'],
    'name'           => ['Name',                   'CONCAT(ap.last_name, ", ", ap.first_name)'],
    'college'        => ['College',                'c.college_code'],
    'year'           => ['Year Level',             'ap.year_level'],
    'email'          => ['Email',                  'u.email'],
    'mobile'         => ['Mobile Number',          'ap.mobile_number'],
    'program'        => ['Scholarship',            'sp.program_name'],
    'status'         => ['Status',                 $statusCase],
    'submitted'      => ['Submitted date',         'DATE(a.submitted_at)'],
    'gwa'            => ['GWA at application',     'a.gwa_at_application'],
    'bank'           => ['Landbank account status', 'COALESCE(m.landbank_status, "")'],
];
$sorts = [
    'Last name (A–Z)' => 'ap.last_name, ap.first_name',
    'Date submitted'  => 'a.submitted_at',
    'Status'          => 'a.status, ap.last_name',
];

// What staff chose
$programId = (int)($_GET['scholarship_id'] ?? 0);
$periodId  = (int)($_GET['period_id'] ?? 0);
$status    = $_GET['status'] ?? '';
$sortName  = isset($sorts[$_GET['sort'] ?? '']) ? $_GET['sort'] : 'Last name (A–Z)';
$picked    = array_values(array_intersect(array_keys($columns), $_GET['c'] ?? ['number', 'student_number', 'name', 'college', 'year', 'program', 'status']));

$from = "applications a
    JOIN applicants ap ON ap.applicant_id = a.applicant_id
    JOIN users u ON u.user_id = ap.user_id
    LEFT JOIN colleges c ON c.college_id = ap.college_id
    JOIN scholarship_programs sp ON sp.scholarship_program_id = a.scholarship_program_id
    LEFT JOIN grantee_masterlist m ON m.student_number = ap.student_number
                                  AND m.scholarship_program_id = a.scholarship_program_id";

$where  = ['a.scholarship_program_id = ?', "a.status <> 'draft'"];
$params = [$programId];
if ($periodId) {
    $where[]  = 'a.period_id = ?';
    $params[] = $periodId;
}
if ($status !== '' && isset(STATUS[$status])) {
    $where[]  = 'a.status = ?';
    $params[] = $status;
}
$whereSql = implode(' AND ', $where);

// Preview count
$matching = $programId ? scalar($pdo, "SELECT COUNT(*) FROM $from WHERE $whereSql", $params) : 0;

// Download the file (CSV opens directly in Excel)
if (isset($_GET['download']) && $programId && $picked) {
    $select = implode(', ', array_map(fn($k) => $columns[$k][1], $picked));
    $st = $pdo->prepare("SELECT $select FROM $from WHERE $whereSql ORDER BY " . $sorts[$sortName]);
    $st->execute($params);

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="applicants_' . $programId . '_' . date('Ymd') . '.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");   // lets Excel read accents and ₱ correctly
    fputcsv($out, array_map(fn($k) => $columns[$k][0], $picked));
    while ($row = $st->fetch(PDO::FETCH_NUM)) {
        fputcsv($out, $row);
    }
    exit;
}

$programs = $pdo->query('SELECT scholarship_program_id, program_name FROM scholarship_programs ORDER BY program_name')->fetchAll(PDO::FETCH_KEY_PAIR);
$periods  = $pdo->query("
    SELECT p.period_id, CONCAT(sp.program_code, ' · ', p.school_year, ' ', p.semester, ' sem')
    FROM application_periods p
    JOIN scholarship_programs sp ON sp.scholarship_program_id = p.scholarship_program_id
    ORDER BY p.school_year DESC, sp.program_code")->fetchAll(PDO::FETCH_KEY_PAIR);

$title = 'Reports & Excel Export';
require __DIR__ . '/includes/header.php';
?>
<form method="get" class="box" style="max-width:700px">
    <h2>Export applicants to Excel</h2>

    <div class="f">
        <label for="s">1. Scholarship program <span class="req">*</span></label>
        <select id="s" name="scholarship_id" required>
            <option value="">Choose a scholarship…</option>
            <?php foreach ($programs as $id => $name): ?><option value="<?= $id ?>"<?= $id === $programId ? ' selected' : '' ?>><?= e($name) ?></option><?php endforeach; ?>
        </select>
    </div>
    <div class="f">
        <label for="pe">2. Academic year / application period <span class="opt">(optional)</span></label>
        <select id="pe" name="period_id">
            <option value="">All periods</option>
            <?php foreach ($periods as $id => $name): ?><option value="<?= $id ?>"<?= $id === $periodId ? ' selected' : '' ?>><?= e($name) ?></option><?php endforeach; ?>
        </select>
    </div>
    <div class="f">
        <label for="st">3. Status <span class="opt">(optional)</span></label>
        <select id="st" name="status">
            <option value="">All statuses</option>
            <?php foreach (array_diff_key(STATUS, ['draft' => 1]) as $key => $label): ?><option value="<?= $key ?>"<?= $key === $status ? ' selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?>
        </select>
    </div>
    <div class="f">
        <label for="so">Sort rows by</label>
        <select id="so" name="sort">
            <?php foreach (array_keys($sorts) as $name): ?><option<?= $name === $sortName ? ' selected' : '' ?>><?= e($name) ?></option><?php endforeach; ?>
        </select>
    </div>

    <fieldset>
        <legend><b>4. Columns to include</b></legend>
        <?php foreach ($columns as $key => [$heading]): ?>
            <label style="display:inline-flex;gap:6px;margin-right:14px;font-weight:400">
                <input type="checkbox" name="c[]" value="<?= $key ?>"<?= in_array($key, $picked, true) ? ' checked' : '' ?>> <?= e($heading) ?>
            </label>
        <?php endforeach; ?>
    </fieldset>

    <p><b>
        <?php if ($programId): ?><?= number_format($matching) ?> records match<?= $status !== '' ? ' (status: ' . e(STATUS[$status] ?? $status) . ')' : ' (all statuses)' ?>
        <?php else: ?>Choose a scholarship to see the matching record count.<?php endif; ?>
    </b></p>

    <div class="acts">
        <button class="btn sec">Update Preview</button>
        <button class="btn gold" name="download" value="1"<?= $programId && $matching ? '' : ' disabled' ?>>Export to Excel</button>
    </div>
</form>
<?php require __DIR__ . '/includes/footer.php'; ?>
