<?php
require_once __DIR__ . '/includes/functions.php';
$me = require_admin();

$documentTypes = $pdo->query('SELECT document_type_id, type_name FROM document_types ORDER BY type_name')->fetchAll(PDO::FETCH_KEY_PAIR);

// Add Scholarship form (values are kept if validation fails)
$values = [
    'program_code' => '', 'program_name' => '', 'provider_type' => 'government', 'provider_name' => '',
    'benefit_amount' => '', 'application_mode' => 'open', 'school_year' => '', 'semester' => '1st',
    'open_date' => '', 'close_date' => '', 'slot_limit' => '',
];
$chosenDocs = [];
$errors     = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    foreach ($values as $key => $default) {
        $values[$key] = trim($_POST[$key] ?? '');
    }
    $chosenDocs = array_values(array_filter(
        array_map('intval', $_POST['docs'] ?? []),
        fn($docId) => isset($documentTypes[$docId])
    ));

    // Validation (each message shows beside its field)
    if ($values['program_code'] === '' || strlen($values['program_code']) > 30) {
        $errors['program_code'] = 'Enter a short code (up to 30 characters), for example TES.';
    }
    if ($values['program_name'] === '') {
        $errors['program_name'] = 'Enter the scholarship name.';
    }
    if (!is_numeric($values['benefit_amount']) || $values['benefit_amount'] < 0) {
        $errors['benefit_amount'] = 'Enter the grant amount (use 0 if not fixed).';
    }
    if (!preg_match('/^\d{4}-\d{4}$/', $values['school_year'])) {
        $errors['school_year'] = 'Use the format 2026-2027.';
    }
    if (!preg_match('/^\d{4}-\d\d-\d\d$/', $values['open_date'])) {
        $errors['open_date'] = 'Choose the opening date.';
    }
    if (!preg_match('/^\d{4}-\d\d-\d\d$/', $values['close_date'])) {
        $errors['close_date'] = 'Choose the deadline.';
    } elseif (!isset($errors['open_date']) && $values['close_date'] < $values['open_date']) {
        $errors['close_date'] = 'The deadline must be after the opening date.';
    }
    if ($values['slot_limit'] !== '' && (int)$values['slot_limit'] < 1) {
        $errors['slot_limit'] = 'Enter a number of slots, or leave blank.';
    }
    if (!$chosenDocs) {
        $errors['docs'] = 'Choose at least one required document.';
    }
    foreach (['provider_type' => ['government', 'private', 'institutional'], 'application_mode' => ['open', 'grantee_list'], 'semester' => ['1st', '2nd', 'Summer']] as $key => $allowed) {
        if (!in_array($values[$key], $allowed, true)) {
            $values[$key] = $allowed[0];
        }
    }

    // Save the scholarship, its required documents and its first application period
    if (!$errors) {
        try {
            $pdo->beginTransaction();
            $st = $pdo->prepare('INSERT INTO scholarship_programs
                (program_code, program_name, provider_type, provider_name, benefit_amount, application_mode, created_by)
                VALUES (?, ?, ?, ?, ?, ?, ?)');
            $st->execute([$values['program_code'], $values['program_name'], $values['provider_type'],
                          $values['provider_name'] ?: null, $values['benefit_amount'], $values['application_mode'], $me['user_id']]);
            $programId = $pdo->lastInsertId();

            $st = $pdo->prepare('INSERT INTO program_requirements (scholarship_program_id, document_type_id) VALUES (?, ?)');
            foreach ($chosenDocs as $docId) {
                $st->execute([$programId, $docId]);
            }

            $st = $pdo->prepare("INSERT INTO application_periods
                (scholarship_program_id, school_year, semester, open_date, close_date, slot_limit, status)
                VALUES (?, ?, ?, ?, ?, ?, 'open')");
            $st->execute([$programId, $values['school_year'], $values['semester'],
                          $values['open_date'] . ' 00:00:00', $values['close_date'] . ' 23:59:59',
                          $values['slot_limit'] !== '' ? (int)$values['slot_limit'] : null]);
            $pdo->commit();

            set_flash('Scholarship added successfully.');
            header('Location: scholarships.php');
            exit;
        } catch (PDOException $ex) {
            $pdo->rollBack();
            $errors['program_code'] = 'A scholarship with this code already exists.';
        }
    }
}

// Scholarship list
$programs = rows($pdo, "
    SELECT sp.*,
           (SELECT GROUP_CONCAT(dt.type_name SEPARATOR ', ')
            FROM program_requirements pr
            JOIN document_types dt ON dt.document_type_id = pr.document_type_id
            WHERE pr.scholarship_program_id = sp.scholarship_program_id) AS requirements,
           (SELECT CONCAT(p.school_year, ' ', p.semester, ' (until ', DATE(p.close_date), ')')
            FROM application_periods p
            WHERE p.scholarship_program_id = sp.scholarship_program_id
            ORDER BY p.close_date DESC LIMIT 1) AS latest_period
    FROM scholarship_programs sp
    ORDER BY sp.program_name");

$title = 'Scholarship Programs';
require __DIR__ . '/includes/header.php';
?>
<div class="tw"><table>
    <thead><tr><th>Code</th><th>Program</th><th>Provider</th><th>Benefit / sem</th><th>Who can apply</th><th>Required documents</th><th>Latest period</th><th>Status</th></tr></thead>
    <tbody>
    <?php foreach ($programs as $p): ?>
        <tr>
            <td><?= e($p['program_code']) ?></td>
            <td><?= e($p['program_name']) ?></td>
            <td><?= e($p['provider_name'] ?: ucfirst($p['provider_type'])) ?></td>
            <td><?= $p['benefit_amount'] !== null ? '₱' . number_format($p['benefit_amount']) : '—' ?></td>
            <td><?= $p['application_mode'] === 'grantee_list' ? 'Listed grantees only' : 'Any registered student' ?></td>
            <td style="white-space:normal;min-width:220px"><?= e($p['requirements'] ?: '—') ?></td>
            <td><?= e($p['latest_period'] ?: '—') ?></td>
            <td><?= $p['is_active'] ? '<span class="bd ok">✓ Active</span>' : '<span class="bd warn">! Inactive</span>' ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table></div>

<form method="post" class="box" style="margin-top:18px;max-width:600px">
    <h2>Add Scholarship</h2>
    <p class="mut"><span class="req">*</span> Required field</p>
    <?= csrf_field() ?>
    <?= field('program_code', 'Short code', 'text', $values, $errors) ?>
    <?= field('program_name', 'Scholarship name', 'text', $values, $errors) ?>
    <div class="f">
        <label for="provider_type">Provider type</label>
        <select id="provider_type" name="provider_type">
            <?php foreach (['government' => 'Government', 'private' => 'Private', 'institutional' => 'Institutional (school)'] as $v => $t): ?>
                <option value="<?= $v ?>"<?= $values['provider_type'] === $v ? ' selected' : '' ?>><?= $t ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <?= field('provider_name', 'Provider name', 'text', $values, $errors, false) ?>
    <?= field('benefit_amount', 'Grant per semester (₱)', 'number', $values, $errors) ?>
    <div class="f">
        <label for="application_mode">Who can apply?</label>
        <select id="application_mode" name="application_mode">
            <option value="grantee_list"<?= $values['application_mode'] === 'grantee_list' ? ' selected' : '' ?>>Listed grantees only (staff uploads the official list)</option>
            <option value="open"<?= $values['application_mode'] === 'open' ? ' selected' : '' ?>>Any registered student (students apply)</option>
        </select>
    </div>
    <fieldset class="f">
        <legend><b>Required documents <span class="req">*</span></b></legend>
        <?php foreach ($documentTypes as $docId => $docName): ?>
            <label style="font-weight:400"><input type="checkbox" name="docs[]" value="<?= $docId ?>"<?= in_array($docId, $chosenDocs, true) ? ' checked' : '' ?>> <?= e($docName) ?></label>
        <?php endforeach; ?>
        <?php if (isset($errors['docs'])): ?><div class="err">✕ <?= e($errors['docs']) ?></div><?php endif; ?>
    </fieldset>
    <?= field('school_year', 'School year (e.g. 2026-2027)', 'text', $values, $errors) ?>
    <div class="f">
        <label for="semester">Semester</label>
        <select id="semester" name="semester">
            <?php foreach (['1st', '2nd', 'Summer'] as $s): ?><option<?= $values['semester'] === $s ? ' selected' : '' ?>><?= $s ?></option><?php endforeach; ?>
        </select>
    </div>
    <?= field('open_date', 'Applications open', 'date', $values, $errors) ?>
    <?= field('close_date', 'Application deadline', 'date', $values, $errors) ?>
    <?= field('slot_limit', 'Available slots', 'number', $values, $errors, false) ?>
    <button class="btn gold">Save Scholarship</button>
</form>
<?php require __DIR__ . '/includes/footer.php'; ?>
