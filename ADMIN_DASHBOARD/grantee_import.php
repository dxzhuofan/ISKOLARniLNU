<?php
require_once __DIR__ . '/includes/functions.php';
$me = require_admin();

// Download the error report of one import
if (isset($_GET['errors'])) {
    $errorRows = rows($pdo, 'SELECT row_no, student_number, message FROM grantee_import_errors WHERE batch_id = ?', [(int)$_GET['errors']]);
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="import_errors.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Row', 'Student Number', 'Problem']);
    foreach ($errorRows as $r) {
        fputcsv($out, $r);
    }
    exit;
}

$required = ['student_number', 'last_name', 'first_name', 'college', 'year_level'];
$preview  = $_SESSION['import_preview'] ?? null;   // file already checked, waiting for confirmation
$error    = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $action = $_POST['do'] ?? '';

    // Steps 1-2: choose the scholarship, upload the file, check every row
    if ($action === 'preview') {
        $programId = (int)($_POST['scholarship_id'] ?? 0);
        $file      = $_FILES['file'] ?? null;

        if (!$programId) {
            $error = 'Please choose a scholarship.';
        } elseif (!$file || $file['error'] || $file['size'] > 10 * 1048576 || strtolower(pathinfo($file['name'], PATHINFO_EXTENSION)) !== 'csv') {
            $error = 'Upload a .csv file up to 10 MB. (In Excel: File > Save As > CSV.)';
        } else {
            $handle  = fopen($file['tmp_name'], 'r');
            $columns = array_map(
                fn($c) => strtolower(trim(preg_replace('/^\xEF\xBB\xBF/', '', $c))),
                fgetcsv($handle) ?: []
            );
            $missing = array_diff($required, $columns);

            if ($missing) {
                $error = 'These required columns are missing: ' . implode(', ', $missing) . '.';
            } else {
                $valid = [];
                $bad   = [];
                $seen  = [];
                $rowNo = 1;
                while (($line = fgetcsv($handle)) !== false) {
                    $rowNo++;
                    if (!array_filter($line)) {
                        continue;
                    }
                    $row = array_combine($columns, array_pad(array_slice($line, 0, count($columns)), count($columns), ''));
                    $num = trim($row['student_number']);

                    $problem = null;
                    if (!preg_match('/^\d{4}-\d{4,6}$/', $num)) {
                        $problem = 'Student number must look like 2023-00421.';
                    } elseif (trim($row['last_name']) === '' || trim($row['first_name']) === '') {
                        $problem = 'Name is missing.';
                    } elseif (!in_array((int)$row['year_level'], [1, 2, 3, 4, 5], true)) {
                        $problem = 'Year level must be 1 to 5.';
                    } elseif (isset($seen[$num])) {
                        $problem = 'Student number appears twice in the file.';
                    }

                    if ($problem) {
                        $bad[] = [$rowNo, $num, $problem];
                    } else {
                        $seen[$num] = true;
                        $valid[]    = $row;
                    }
                }
                $preview = $_SESSION['import_preview'] = ['program' => $programId, 'valid' => $valid, 'bad' => $bad, 'file' => $file['name']];
            }
        }

    // Steps 3-4: staff confirmed, now save (existing grantees are skipped, never overwritten)
    } elseif ($action === 'confirm' && $preview) {
        $added = 0;
        $skipped = 0;
        $badCount = count($preview['bad']);

        $pdo->beginTransaction();
        $st = $pdo->prepare('INSERT INTO grantee_imports (scholarship_program_id, file_name, total_rows, created_by) VALUES (?, ?, ?, ?)');
        $st->execute([$preview['program'], $preview['file'], count($preview['valid']) + $badCount, $me['user_id']]);
        $batchId = (int)$pdo->lastInsertId();

        $insert = $pdo->prepare('INSERT IGNORE INTO grantee_masterlist
            (scholarship_program_id, student_number, last_name, first_name, college_code, year_level, batch_id)
            VALUES (?, ?, ?, ?, ?, ?, ?)');
        foreach ($preview['valid'] as $row) {
            $insert->execute([$preview['program'], trim($row['student_number']), trim($row['last_name']),
                              trim($row['first_name']), trim($row['college']), (int)$row['year_level'], $batchId]);
            $insert->rowCount() ? $added++ : $skipped++;
        }

        // Link grantees who already have a student account
        $st = $pdo->prepare('UPDATE grantee_masterlist m
                             JOIN applicants ap ON ap.student_number = m.student_number
                             SET m.applicant_id = ap.applicant_id
                             WHERE m.batch_id = ? AND m.applicant_id IS NULL');
        $st->execute([$batchId]);

        $st = $pdo->prepare('UPDATE grantee_imports SET imported_rows = ?, error_rows = ? WHERE batch_id = ?');
        $st->execute([$added, $badCount, $batchId]);

        $st = $pdo->prepare('INSERT INTO grantee_import_errors (batch_id, row_no, student_number, message) VALUES (?, ?, ?, ?)');
        foreach ($preview['bad'] as $b) {
            $st->execute([$batchId, $b[0], $b[1], $b[2]]);
        }
        $pdo->commit();

        unset($_SESSION['import_preview']);
        set_flash("Import finished: $added added, $skipped already listed (skipped), $badCount rows need correction.", $badCount ? 'warn' : 'ok');
        header('Location: grantee_import.php#history');
        exit;

    } elseif ($action === 'cancel') {
        unset($_SESSION['import_preview']);
        header('Location: grantee_import.php');
        exit;
    }
}

$programs = $pdo->query('SELECT scholarship_program_id, program_name FROM scholarship_programs ORDER BY program_name')->fetchAll(PDO::FETCH_KEY_PAIR);

// Import history
$history = rows($pdo, "
    SELECT b.batch_id, b.file_name, b.total_rows, b.imported_rows, b.error_rows, b.created_at, sp.program_name
    FROM grantee_imports b
    JOIN scholarship_programs sp ON sp.scholarship_program_id = b.scholarship_program_id
    ORDER BY b.created_at DESC
    LIMIT 15");

$title = 'Import Grantee List';
require __DIR__ . '/includes/header.php';
?>
<?php if ($error): ?><div class="alert bad" role="alert">✕ <?= e($error) ?></div><?php endif; ?>
<a href="grantees.php">← Back to Grantees</a>

<?php if (!$preview): ?>
<form method="post" enctype="multipart/form-data" class="box" style="margin-top:10px;max-width:620px">
    <h2>Step 1–2: Select scholarship and upload file</h2>
    <?= csrf_field() ?>
    <input type="hidden" name="do" value="preview">
    <div class="f">
        <label for="s">Scholarship <span class="req">*</span></label>
        <select id="s" name="scholarship_id" required>
            <option value="">Choose a scholarship…</option>
            <?php foreach ($programs as $id => $name): ?><option value="<?= $id ?>"><?= e($name) ?></option><?php endforeach; ?>
        </select>
    </div>
    <div class="f">
        <label for="fl">Grantee list (.csv, max 10 MB) <span class="req">*</span></label>
        <input id="fl" type="file" name="file" accept=".csv" required>
    </div>
    <p class="mut">Required columns: <b>student_number, last_name, first_name, college, year_level</b></p>
    <button class="btn">Upload and Check File</button>
</form>
<?php else: ?>
<div class="box" style="margin-top:10px">
    <h2>Step 3–4: Preview and confirm</h2>
    <p>File: <b><?= e($preview['file']) ?></b> for <b><?= e($programs[$preview['program']] ?? '') ?></b>. Nothing is saved until you confirm.</p>
    <div class="cards">
        <div class="card"><small>Valid records</small><b><?= count($preview['valid']) ?></b></div>
        <div class="card"><small>Rows needing correction</small><b><?= count($preview['bad']) ?></b></div>
    </div>

    <?php if ($preview['bad']): ?>
        <h3>Rows that need correction</h3>
        <div class="tw"><table>
            <thead><tr><th>Row</th><th>Student Number</th><th>Problem</th></tr></thead>
            <tbody>
            <?php foreach (array_slice($preview['bad'], 0, 50) as [$rowNo, $num, $problem]): ?>
                <tr><td><?= $rowNo ?></td><td><?= e($num) ?></td><td><span class="bd bad">✕ <?= e($problem) ?></span></td></tr>
            <?php endforeach; ?>
            </tbody>
        </table></div>
    <?php endif; ?>

    <p>Students already listed for this scholarship are skipped and never overwritten.</p>
    <form method="post" class="acts" data-confirm="Import <?= count($preview['valid']) ?> records into the grantee list?">
        <?= csrf_field() ?>
        <button class="btn" name="do" value="confirm"<?= $preview['valid'] ? '' : ' disabled' ?>>Confirm Import</button>
        <button class="btn sec" name="do" value="cancel" formnovalidate>Cancel</button>
    </form>
</div>
<?php endif; ?>

<section class="box" id="history">
    <h2>Import History</h2>
    <?php if (!$history): ?><p class="mut">No imports yet.</p><?php else: ?>
    <div class="tw"><table>
        <thead><tr><th>Date</th><th>Scholarship</th><th>File</th><th>Rows imported</th><th>Rows with errors</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($history as $h): ?>
            <tr>
                <td><?= e($h['created_at']) ?></td>
                <td><?= e($h['program_name']) ?></td>
                <td><?= e($h['file_name']) ?></td>
                <td><?= $h['imported_rows'] ?> of <?= $h['total_rows'] ?></td>
                <td><?= $h['error_rows'] ? '<span class="bd warn">! ' . $h['error_rows'] . '</span>' : '0' ?></td>
                <td><?php if ($h['error_rows']): ?><a class="btn sm sec" href="?errors=<?= $h['batch_id'] ?>">Download Error Report</a><?php endif; ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
    <?php endif; ?>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
