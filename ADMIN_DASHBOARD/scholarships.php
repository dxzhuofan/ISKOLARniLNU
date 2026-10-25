<?php
require_once __DIR__ . '/includes/functions.php';
$me = require_admin();

$documentTypes = $pdo->query('SELECT document_type_id, type_name FROM document_types ORDER BY type_name')->fetchAll(PDO::FETCH_KEY_PAIR);

// Add Scholarship form (values are kept if validation fails)
$values = [
    'program_code' => '', 'program_name' => '', 'provider_type' => 'government', 'provider_name' => '',
    'benefit_amount' => '', 'eligibility_notes' => '', 'school_year' => '', 'semester' => '1st',
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
    if (strlen($values['eligibility_notes']) > 65535) {
        $errors['eligibility_notes'] = 'Eligibility notes are too long.';
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
    foreach (['provider_type' => ['government', 'private', 'institutional'], 'semester' => ['1st', '2nd', 'Summer']] as $key => $allowed) {
        if (!in_array($values[$key], $allowed, true)) {
            $values[$key] = $allowed[0];
        }
    }

    // Save the scholarship, its required documents and its first application period
    if (!$errors) {
        $lockAcquired = false;
        $saved = false;
        try {
            $lockAcquired = (int)$pdo->query("SELECT GET_LOCK('lnu_scholarship_program_setup', 10)")->fetchColumn() === 1;
            if (!$lockAcquired) {
                throw new RuntimeException('Could not acquire the scholarship setup lock. Try again.');
            }

            $pdo->beginTransaction();
            $duplicate = $pdo->prepare('SELECT 1 FROM scholarship_programs WHERE program_code = ? LIMIT 1');
            $duplicate->execute([$values['program_code']]);
            if ($duplicate->fetchColumn()) {
                $pdo->rollBack();
                $errors['program_code'] = 'A scholarship with this code already exists.';
            } else {
                $programId = (int)$pdo->query('SELECT COALESCE(MAX(scholarship_program_id), -1) + 1 FROM scholarship_programs')->fetchColumn();
                $st = $pdo->prepare('INSERT INTO scholarship_programs
                    (scholarship_program_id, program_code, program_name, provider_type, provider_name, benefit_amount, eligibility_notes, created_by)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
                $st->execute([$programId, $values['program_code'], $values['program_name'], $values['provider_type'],
                              $values['provider_name'] ?: null, $values['benefit_amount'],
                              $values['eligibility_notes'] !== '' ? $values['eligibility_notes'] : null, $me['user_id']]);

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
                $saved = true;
            }
        } catch (PDOException $ex) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('Could not save scholarship: ' . $ex->getMessage());
            $errors['form'] = 'The scholarship could not be saved. Check the entered details and server log.';
        } catch (Throwable $ex) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('Could not save scholarship: ' . $ex->getMessage());
            $errors['form'] = 'The scholarship could not be saved. Try again or check the server log.';
        } finally {
            if ($lockAcquired) {
                $pdo->query("SELECT RELEASE_LOCK('lnu_scholarship_program_setup')");
            }
        }
        if ($saved) {
            set_flash('Scholarship added successfully.');
            header('Location: scholarships.php');
            exit;
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
    <thead><tr><th>Code</th><th>Program</th><th>Provider</th><th>Benefit / sem</th><th>Eligibility</th><th>Required documents</th><th>Latest period</th><th>Status</th></tr></thead>
    <tbody>
    <?php foreach ($programs as $p): ?>
        <tr>
            <td><?= e($p['program_code']) ?></td>
            <td><?= e($p['program_name']) ?></td>
            <td><?= e($p['provider_name'] ?: ucfirst($p['provider_type'])) ?></td>
            <td><?= $p['benefit_amount'] !== null ? '₱' . number_format($p['benefit_amount']) : '—' ?></td>
            <td><?= e($p['eligibility_notes'] ?: 'Any registered applicant') ?></td>
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
    <?php if (isset($errors['form'])): ?><div class="alert bad" role="alert"><?= e($errors['form']) ?></div><?php endif; ?>
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
        <label for="eligibility_notes">Eligibility notes</label>
        <textarea id="eligibility_notes" name="eligibility_notes" rows="3" maxlength="65535"><?= e($values['eligibility_notes']) ?></textarea>
        <?php if (isset($errors['eligibility_notes'])): ?><div class="err">✕ <?= e($errors['eligibility_notes']) ?></div><?php endif; ?>
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
