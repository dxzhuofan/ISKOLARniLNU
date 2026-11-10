<?php
require_once __DIR__ . '/includes/functions.php';
$me = require_scholarship_manager();
$catalog = require __DIR__ . '/../Registration_and_Login(UIUX)/academic_catalog.php';

$pdo->exec("UPDATE application_periods SET status = 'closed' WHERE status = 'open' AND close_date < NOW()");
$documentTypes = $pdo->query('SELECT document_type_id, type_name FROM document_types ORDER BY type_name')->fetchAll(PDO::FETCH_KEY_PAIR);

$values = [
    'program_code' => '', 'program_name' => '', 'description' => '', 'provider_type' => 'government',
    'provider_name' => '', 'benefit_amount' => '0', 'eligibility_notes' => '', 'school_year' => '',
    'semester' => '1st', 'open_date' => '', 'close_date' => '', 'slot_limit' => '',
];
$chosenDocs = [];
$chosenOptionalDocs = [];
$chosenEligibility = [];
$errors = [];
$editId = max(0, (int)($_GET['edit'] ?? 0));
$periodId = 0;

if ($editId) {
    $st = $pdo->prepare(
        'SELECT sp.*, p.period_id, p.school_year, p.semester, p.open_date, p.close_date, p.slot_limit
         FROM scholarship_programs sp
         LEFT JOIN application_periods p ON p.period_id = (
             SELECT p2.period_id FROM application_periods p2
             WHERE p2.scholarship_program_id = sp.scholarship_program_id
             ORDER BY p2.close_date DESC, p2.period_id DESC LIMIT 1
         )
         WHERE sp.scholarship_program_id = ?'
    );
    $st->execute([$editId]);
    $record = $st->fetch();
    if (!$record) {
        http_response_code(404);
        exit('Scholarship program not found.');
    }
    foreach ($values as $key => $default) {
        if (array_key_exists($key, $record)) {
            $values[$key] = (string)($record[$key] ?? '');
        }
    }
    $periodId = (int)($record['period_id'] ?? 0);
    if (!empty($record['open_date'])) {
        $values['open_date'] = date('Y-m-d', strtotime($record['open_date']));
    }
    if (!empty($record['close_date'])) {
        $values['close_date'] = date('Y-m-d', strtotime($record['close_date']));
    }
    $currentRequirements = rows(
        $pdo,
        'SELECT document_type_id, is_required FROM program_requirements WHERE scholarship_program_id = ?',
        [$editId]
    );
    foreach ($currentRequirements as $requirement) {
        if ((int)$requirement['is_required'] === 1) {
            $chosenDocs[] = (int)$requirement['document_type_id'];
        } else {
            $chosenOptionalDocs[] = (int)$requirement['document_type_id'];
        }
    }
    $chosenEligibility = array_map(
        static fn(array $scope): string => $scope['program_code'] === ''
            ? $scope['college_code'] . ':*'
            : $scope['college_code'] . '|' . $scope['program_code'],
        rows($pdo, 'SELECT college_code, program_code FROM scholarship_program_eligibility WHERE scholarship_program_id = ?', [$editId])
    );
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $action = (string)($_POST['action'] ?? '');
    $postedId = max(0, (int)($_POST['program_id'] ?? 0));

    if ($action === 'operation') {
        $operation = (string)($_POST['operation'] ?? '');
        if (!$postedId || !in_array($operation, ['archive', 'restore', 'delete', 'open', 'close'], true)) {
            set_flash('Choose a valid scholarship action.', 'bad');
            header('Location: scholarships.php');
            exit;
        }

        try {
            $pdo->beginTransaction();
            $lock = $pdo->prepare('SELECT scholarship_program_id, program_name, is_active FROM scholarship_programs WHERE scholarship_program_id = ? FOR UPDATE');
            $lock->execute([$postedId]);
            $program = $lock->fetch();
            if (!$program) {
                throw new RuntimeException('Scholarship program not found.');
            }

            if ($operation === 'archive' || $operation === 'restore') {
                $active = $operation === 'restore' ? 1 : 0;
                $pdo->prepare('UPDATE scholarship_programs SET is_active = ? WHERE scholarship_program_id = ?')
                    ->execute([$active, $postedId]);
                audit_log($pdo, (int)$me['user_id'], $operation, 'scholarship_program', $postedId, [
                    'program_name' => $program['program_name'],
                    'is_active' => $active,
                ]);
            } elseif ($operation === 'open' || $operation === 'close') {
                $period = rows(
                    $pdo,
                    'SELECT period_id, close_date FROM application_periods
                     WHERE scholarship_program_id = ? ORDER BY close_date DESC, period_id DESC LIMIT 1 FOR UPDATE',
                    [$postedId]
                )[0] ?? null;
                if (!$period) {
                    throw new RuntimeException('Add an application period before opening this scholarship.');
                }
                if ($operation === 'open' && scalar(
                    $pdo,
                    'SELECT close_date < NOW() FROM application_periods WHERE period_id = ?',
                    [(int)$period['period_id']]
                )) {
                    throw new RuntimeException('The deadline has passed. Edit the period deadline before reopening.');
                }
                $periodStatus = $operation === 'open' ? 'open' : 'closed';
                $pdo->prepare('UPDATE application_periods SET status = ? WHERE period_id = ?')
                    ->execute([$periodStatus, $period['period_id']]);
                audit_log($pdo, (int)$me['user_id'], $operation, 'scholarship_program', $postedId, [
                    'program_name' => $program['program_name'],
                    'period_status' => $periodStatus,
                ]);
            } else {
                $applicationCount = scalar($pdo, 'SELECT COUNT(*) FROM applications WHERE scholarship_program_id = ?', [$postedId]);
                if ($applicationCount > 0) {
                    throw new RuntimeException('This scholarship has applications and cannot be deleted. Archive it instead.');
                }
                audit_log($pdo, (int)$me['user_id'], 'delete', 'scholarship_program', $postedId, [
                    'program_name' => $program['program_name'],
                ]);
                $pdo->prepare('DELETE FROM program_requirements WHERE scholarship_program_id = ?')->execute([$postedId]);
                $pdo->prepare('DELETE FROM scholarship_program_eligibility WHERE scholarship_program_id = ?')->execute([$postedId]);
                $pdo->prepare('DELETE FROM application_periods WHERE scholarship_program_id = ?')->execute([$postedId]);
                $pdo->prepare('DELETE FROM scholarship_programs WHERE scholarship_program_id = ?')->execute([$postedId]);
            }

            $pdo->commit();
            set_flash('Scholarship ' . ($operation === 'open' ? 'opened' : ($operation === 'close' ? 'closed' : $operation)) . ' successfully.');
        } catch (Throwable $error) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('LNU scholarship action error: ' . $error->getMessage());
            set_flash($error instanceof RuntimeException ? $error->getMessage() : 'The scholarship action could not be completed.', 'bad');
        }
        header('Location: scholarships.php');
        exit;
    }

    if ($action === 'save') {
        $editId = $postedId;
        foreach ($values as $key => $default) {
            $values[$key] = trim((string)($_POST[$key] ?? ''));
        }
        $submittedDocs = $_POST['docs'] ?? [];
        $chosenDocs = array_values(array_unique(array_filter(
            array_map('intval', is_array($submittedDocs) ? $submittedDocs : []),
            static fn(int $docId): bool => isset($documentTypes[$docId])
        )));
        $submittedOptionalDocs = $_POST['optional_docs'] ?? [];
        $chosenOptionalDocs = array_values(array_diff(array_unique(array_filter(
            array_map('intval', is_array($submittedOptionalDocs) ? $submittedOptionalDocs : []),
            static fn(int $docId): bool => isset($documentTypes[$docId])
        )), $chosenDocs));
        $submittedEligibility = $_POST['eligible'] ?? [];
        $allowedEligibility = [];
        foreach ($catalog as $collegeCode => $college) {
            $allowedEligibility[$collegeCode . ':*'] = [$collegeCode, ''];
            foreach ($college['programs'] as $programCode => $_program) {
                $allowedEligibility[$collegeCode . '|' . $programCode] = [$collegeCode, $programCode];
            }
        }
        $submittedEligibility = array_values(array_unique(
            array_filter(is_array($submittedEligibility) ? $submittedEligibility : [], 'is_string')
        ));
        $chosenEligibility = array_values(array_intersect($submittedEligibility, array_keys($allowedEligibility)));

        if ($values['program_code'] === '' || strlen($values['program_code']) > 30) {
            $errors['program_code'] = 'Enter a short code (up to 30 characters), for example TES.';
        }
        if ($values['program_name'] === '' || mb_strlen($values['program_name']) > 150) {
            $errors['program_name'] = 'Enter a scholarship name of at most 150 characters.';
        }
        if (mb_strlen($values['description']) > 65535) {
            $errors['description'] = 'The description is too long.';
        }
        if (mb_strlen($values['eligibility_notes']) > 65535) {
            $errors['eligibility_notes'] = 'Eligibility notes are too long.';
        }
        if (!is_numeric($values['benefit_amount']) || (float)$values['benefit_amount'] < 0) {
            $errors['benefit_amount'] = 'Enter a non-negative grant amount (use 0 if not fixed).';
        }
        if (!preg_match('/^\d{4}-\d{4}$/', $values['school_year'])) {
            $errors['school_year'] = 'Use the format 2026-2027.';
        }
        foreach (['open_date', 'close_date'] as $dateField) {
            $date = DateTime::createFromFormat('!Y-m-d', $values[$dateField]);
            if (!$date || $date->format('Y-m-d') !== $values[$dateField]) {
                $errors[$dateField] = $dateField === 'open_date' ? 'Choose the opening date.' : 'Choose the deadline.';
            }
        }
        if (!isset($errors['open_date']) && !isset($errors['close_date']) && $values['close_date'] < $values['open_date']) {
            $errors['close_date'] = 'The deadline must be on or after the opening date.';
        }
        if ($values['slot_limit'] !== '' && (!ctype_digit($values['slot_limit']) || (int)$values['slot_limit'] < 1)) {
            $errors['slot_limit'] = 'Enter a positive number of slots, or leave blank.';
        }
        if (!$chosenDocs) {
            $errors['docs'] = 'Choose at least one required document.';
        }
        if (!in_array($values['provider_type'], ['government', 'private', 'institutional'], true)) {
            $values['provider_type'] = 'government';
        }
        if (!in_array($values['semester'], ['1st', '2nd', 'Summer'], true)) {
            $values['semester'] = '1st';
        }

        if (!$errors) {
            try {
                $lockAcquired = (int)$pdo->query("SELECT GET_LOCK('lnu_scholarship_program_setup', 10)")->fetchColumn() === 1;
                if (!$lockAcquired) {
                    throw new RuntimeException('Could not acquire the scholarship setup lock. Try again.');
                }
                $pdo->beginTransaction();
                $duplicate = $pdo->prepare(
                    'SELECT scholarship_program_id FROM scholarship_programs
                     WHERE program_code = ? AND scholarship_program_id <> ? LIMIT 1'
                );
                $duplicate->execute([$values['program_code'], $editId]);
                if ($duplicate->fetchColumn()) {
                    throw new RuntimeException('A scholarship with this code already exists.');
                }

                $before = null;
                $beforePeriod = null;
                $beforeDocs = [];
                $beforeOptionalDocs = [];
                $beforeEligibility = [];
                if ($editId) {
                    $existing = $pdo->prepare('SELECT * FROM scholarship_programs WHERE scholarship_program_id = ? FOR UPDATE');
                    $existing->execute([$editId]);
                    $before = $existing->fetch();
                    if (!$before) {
                        throw new RuntimeException('Scholarship program not found.');
                    }
                    $beforePeriod = rows(
                        $pdo,
                        'SELECT school_year, semester, open_date, close_date, slot_limit
                         FROM application_periods WHERE scholarship_program_id = ?
                         ORDER BY close_date DESC, period_id DESC LIMIT 1',
                        [$editId]
                    )[0] ?? null;
                    $beforeRequirements = rows(
                        $pdo,
                        'SELECT document_type_id, is_required FROM program_requirements WHERE scholarship_program_id = ?',
                        [$editId]
                    );
                    foreach ($beforeRequirements as $requirement) {
                        if ((int)$requirement['is_required'] === 1) {
                            $beforeDocs[] = (int)$requirement['document_type_id'];
                        } else {
                            $beforeOptionalDocs[] = (int)$requirement['document_type_id'];
                        }
                    }
                    $beforeEligibility = rows(
                        $pdo,
                        'SELECT college_code, program_code FROM scholarship_program_eligibility WHERE scholarship_program_id = ?',
                        [$editId]
                    );
                    $programId = $editId;
                    $pdo->prepare(
                        'UPDATE scholarship_programs
                         SET program_code = ?, program_name = ?, description = ?, provider_type = ?, provider_name = ?,
                             benefit_amount = ?, eligibility_notes = ?
                         WHERE scholarship_program_id = ?'
                    )->execute([
                        $values['program_code'], $values['program_name'], $values['description'] ?: null,
                        $values['provider_type'], $values['provider_name'] ?: null,
                        $values['benefit_amount'], $values['eligibility_notes'] ?: null, $programId,
                    ]);
                } else {
                    $programId = (int)$pdo->query('SELECT COALESCE(MAX(scholarship_program_id), -1) + 1 FROM scholarship_programs')->fetchColumn();
                    $pdo->prepare(
                        'INSERT INTO scholarship_programs
                         (scholarship_program_id, program_code, program_name, description, provider_type, provider_name,
                          benefit_amount, eligibility_notes, created_by)
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
                    )->execute([
                        $programId, $values['program_code'], $values['program_name'], $values['description'] ?: null,
                        $values['provider_type'], $values['provider_name'] ?: null,
                        $values['benefit_amount'], $values['eligibility_notes'] ?: null, (int)$me['user_id'],
                    ]);
                }

                $period = $pdo->prepare(
                    'SELECT period_id FROM application_periods
                     WHERE scholarship_program_id = ? ORDER BY close_date DESC, period_id DESC LIMIT 1 FOR UPDATE'
                );
                $period->execute([$programId]);
                $currentPeriodId = $period->fetchColumn();
                if ($currentPeriodId !== false) {
                    $pdo->prepare(
                        'UPDATE application_periods
                         SET school_year = ?, semester = ?, open_date = ?, close_date = ?, slot_limit = ?
                         WHERE period_id = ?'
                    )->execute([
                        $values['school_year'], $values['semester'], $values['open_date'] . ' 00:00:00',
                        $values['close_date'] . ' 23:59:59',
                        $values['slot_limit'] !== '' ? (int)$values['slot_limit'] : null, $currentPeriodId,
                    ]);
                } else {
                    $pdo->prepare(
                        "INSERT INTO application_periods
                         (scholarship_program_id, school_year, semester, open_date, close_date, slot_limit, status)
                         VALUES (?, ?, ?, ?, ?, ?, 'open')"
                    )->execute([
                        $programId, $values['school_year'], $values['semester'], $values['open_date'] . ' 00:00:00',
                        $values['close_date'] . ' 23:59:59',
                        $values['slot_limit'] !== '' ? (int)$values['slot_limit'] : null,
                    ]);
                }

                $pdo->prepare('DELETE FROM program_requirements WHERE scholarship_program_id = ?')->execute([$programId]);
                $insertDoc = $pdo->prepare(
                    'INSERT INTO program_requirements (scholarship_program_id, document_type_id, is_required) VALUES (?, ?, ?)'
                );
                foreach ($chosenDocs as $docId) {
                    $insertDoc->execute([$programId, $docId, 1]);
                }
                foreach ($chosenOptionalDocs as $docId) {
                    $insertDoc->execute([$programId, $docId, 0]);
                }
                $pdo->prepare('DELETE FROM scholarship_program_eligibility WHERE scholarship_program_id = ?')->execute([$programId]);
                $insertEligibility = $pdo->prepare(
                    'INSERT INTO scholarship_program_eligibility (scholarship_program_id, college_code, program_code) VALUES (?, ?, ?)'
                );
                $selectedColleges = [];
                foreach ($chosenEligibility as $scope) {
                    [$collegeCode, $programCode] = $allowedEligibility[$scope];
                    if ($programCode === '') {
                        $selectedColleges[$collegeCode] = true;
                    }
                }
                foreach ($chosenEligibility as $scope) {
                    [$collegeCode, $programCode] = $allowedEligibility[$scope];
                    if ($programCode !== '' && isset($selectedColleges[$collegeCode])) {
                        continue;
                    }
                    $insertEligibility->execute([$programId, $collegeCode, $programCode]);
                }

                audit_log($pdo, (int)$me['user_id'], $editId ? 'edit' : 'create', 'scholarship_program', $programId, [
                    'before' => $before ? [
                        'program_code' => $before['program_code'],
                        'program_name' => $before['program_name'],
                        'description' => $before['description'] ?? null,
                        'provider_type' => $before['provider_type'],
                        'provider_name' => $before['provider_name'],
                        'benefit_amount' => $before['benefit_amount'],
                        'eligibility_notes' => $before['eligibility_notes'],
                        'period' => $beforePeriod,
                        'required_documents' => $beforeDocs,
                        'optional_documents' => $beforeOptionalDocs,
                        'eligibility' => $beforeEligibility,
                    ] : null,
                    'after' => [
                        'program_code' => $values['program_code'],
                        'program_name' => $values['program_name'],
                        'description' => $values['description'],
                        'provider_type' => $values['provider_type'],
                        'provider_name' => $values['provider_name'],
                        'benefit_amount' => $values['benefit_amount'],
                        'eligibility_notes' => $values['eligibility_notes'],
                        'school_year' => $values['school_year'],
                        'semester' => $values['semester'],
                        'close_date' => $values['close_date'],
                        'slot_limit' => $values['slot_limit'],
                        'required_documents' => $chosenDocs,
                        'optional_documents' => $chosenOptionalDocs,
                        'eligibility' => $chosenEligibility,
                    ],
                ]);
                $pdo->commit();
                set_flash($editId ? 'Scholarship updated successfully.' : 'Scholarship added successfully.');
                header('Location: scholarships.php');
                exit;
            } catch (Throwable $error) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                error_log('LNU scholarship save error: ' . $error->getMessage());
                $errors['form'] = $error instanceof RuntimeException
                    ? $error->getMessage()
                    : 'The scholarship could not be saved. Check the entered details and server log.';
            } finally {
                if (!empty($lockAcquired)) {
                    $pdo->query("SELECT RELEASE_LOCK('lnu_scholarship_program_setup')");
                }
            }
        }
    }
}

$q = trim((string)($_GET['q'] ?? ''));
$statusFilter = (string)($_GET['status'] ?? '');
$collegeFilter = (string)($_GET['college'] ?? '');
$deadlineFrom = (string)($_GET['deadline_from'] ?? '');
$deadlineTo = (string)($_GET['deadline_to'] ?? '');
$sort = (string)($_GET['sort'] ?? 'name_asc');
$size = page_size();
$page = max(1, (int)($_GET['page'] ?? 1));
$statusExpression = "CASE WHEN sp.is_active = 0 THEN 'archived' WHEN lp.status = 'open' AND lp.close_date >= NOW() THEN 'open' ELSE 'closed' END";
$where = [];
$params = [];
if ($q !== '') {
    $where[] = '(sp.program_code LIKE ? OR sp.program_name LIKE ? OR sp.provider_name LIKE ? OR sp.eligibility_notes LIKE ?)';
    array_push($params, "%$q%", "%$q%", "%$q%", "%$q%");
}
if (in_array($statusFilter, ['open', 'closed', 'archived'], true)) {
    $where[] = $statusExpression . ' = ?';
    $params[] = $statusFilter;
}
if (isset($catalog[$collegeFilter])) {
    $where[] = "(NOT EXISTS (SELECT 1 FROM scholarship_program_eligibility any_scope
                             WHERE any_scope.scholarship_program_id = sp.scholarship_program_id)
                 OR EXISTS (SELECT 1 FROM scholarship_program_eligibility scope
                            WHERE scope.scholarship_program_id = sp.scholarship_program_id
                              AND scope.college_code = ?))";
    $params[] = $collegeFilter;
}
if ($deadlineFrom !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $deadlineFrom)) {
    $where[] = 'DATE(lp.close_date) >= ?';
    $params[] = $deadlineFrom;
}
if ($deadlineTo !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $deadlineTo)) {
    $where[] = 'DATE(lp.close_date) <= ?';
    $params[] = $deadlineTo;
}
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
$from = "scholarship_programs sp
    LEFT JOIN application_periods lp ON lp.period_id = (
        SELECT p2.period_id FROM application_periods p2
        WHERE p2.scholarship_program_id = sp.scholarship_program_id
        ORDER BY p2.close_date DESC, p2.period_id DESC LIMIT 1
    )";
$total = scalar($pdo, "SELECT COUNT(*) FROM $from $whereSql", $params);
$page = min($page, max(1, (int)ceil($total / $size)));
$offset = ($page - 1) * $size;
$sortOptions = [
    'name_asc' => 'sp.program_name ASC',
    'name_desc' => 'sp.program_name DESC',
    'deadline_asc' => 'lp.close_date IS NULL, lp.close_date ASC',
    'deadline_desc' => 'lp.close_date IS NULL, lp.close_date DESC',
    'status' => "$statusExpression ASC, sp.program_name ASC",
];
$orderBy = $sortOptions[$sort] ?? $sortOptions['name_asc'];
$programs = rows($pdo, "
    SELECT sp.*, lp.period_id, lp.school_year, lp.semester, lp.open_date, lp.close_date, lp.slot_limit, lp.status AS period_status,
           ($statusExpression) AS display_status,
           (SELECT GROUP_CONCAT(dt.type_name SEPARATOR ', ')
            FROM program_requirements pr JOIN document_types dt ON dt.document_type_id = pr.document_type_id
            WHERE pr.scholarship_program_id = sp.scholarship_program_id) AS requirements
    FROM $from $whereSql
    ORDER BY $orderBy, sp.scholarship_program_id DESC
    LIMIT $size OFFSET $offset", $params);
$eligibilityByProgram = [];
if ($programs) {
    $programIds = array_map('intval', array_column($programs, 'scholarship_program_id'));
    $placeholders = implode(',', array_fill(0, count($programIds), '?'));
    foreach (rows(
        $pdo,
        "SELECT scholarship_program_id, college_code, program_code
         FROM scholarship_program_eligibility WHERE scholarship_program_id IN ($placeholders)",
        $programIds
    ) as $scope) {
        $eligibilityByProgram[$scope['scholarship_program_id']][] = $scope;
    }
}

$title = 'Scholarship Programs';
require __DIR__ . '/includes/header.php';
?>
<form class="bar" method="get">
    <label class="sr" for="q">Search scholarships</label>
    <input id="q" name="q" type="search" placeholder="Search code, name, provider or eligibility" value="<?= e($q) ?>">
    <select name="status" aria-label="Status">
        <option value="">All statuses</option>
        <?php foreach (['open' => 'Open', 'closed' => 'Closed', 'archived' => 'Archived'] as $key => $label): ?>
            <option value="<?= $key ?>"<?= $statusFilter === $key ? ' selected' : '' ?>><?= $label ?></option>
        <?php endforeach; ?>
    </select>
    <select name="college" aria-label="Eligible college">
        <option value="">All colleges</option>
        <?php foreach ($catalog as $code => $college): ?>
            <option value="<?= e($code) ?>"<?= $collegeFilter === $code ? ' selected' : '' ?>><?= e($college['name']) ?></option>
        <?php endforeach; ?>
    </select>
    <input type="date" name="deadline_from" aria-label="Deadline from" value="<?= e($deadlineFrom) ?>">
    <input type="date" name="deadline_to" aria-label="Deadline to" value="<?= e($deadlineTo) ?>">
    <select name="sort" aria-label="Sort scholarships">
        <?php foreach (['name_asc' => 'Name A–Z', 'name_desc' => 'Name Z–A', 'deadline_asc' => 'Deadline soonest', 'deadline_desc' => 'Deadline latest', 'status' => 'Status'] as $key => $label): ?>
            <option value="<?= e($key) ?>"<?= $sort === $key ? ' selected' : '' ?>><?= e($label) ?></option>
        <?php endforeach; ?>
    </select>
    <button class="btn">Filter</button>
    <button class="btn gold" id="add-scholarship-button" type="button">+ Add Scholarship</button>
    <?php if ($q !== '' || $statusFilter !== '' || $collegeFilter !== '' || $deadlineFrom !== '' || $deadlineTo !== ''): ?>
        <a class="btn sec" href="scholarships.php">Clear filters</a>
    <?php endif; ?>
</form>

<?php if (!$programs): ?>
    <div class="box c"><h3>No scholarship programs found</h3><p class="mut">Adjust your filters or add a scholarship program.</p></div>
<?php else: ?>
    <div class="tw"><table>
        <thead><tr><th>Code</th><th>Program</th><th>Provider</th><th>Benefit / sem</th><th>Deadline / slots</th><th>Eligibility</th><th>Required documents</th><th>Status</th><th>Actions</th></tr></thead>
        <tbody>
        <?php foreach ($programs as $program):
            $scopeLabels = [];
            foreach ($eligibilityByProgram[$program['scholarship_program_id']] ?? [] as $scope) {
                $college = $catalog[$scope['college_code']] ?? null;
                if (!$college) continue;
                $scopeLabels[] = $scope['program_code'] === ''
                    ? $college['name'] . ' (all programs)'
                    : $college['name'] . ' — ' . ($college['programs'][$scope['program_code']]['name'] ?? $scope['program_code']);
            }
            $displayStatus = $program['display_status'];
        ?>
            <tr>
                <td><?= e($program['program_code']) ?><br><a class="small" href="applications.php?program=<?= (int)$program['scholarship_program_id'] ?>">Applicants</a></td>
                <td><?= e($program['program_name']) ?></td>
                <td><?= e($program['provider_name'] ?: ucfirst($program['provider_type'])) ?></td>
                <td><?= $program['benefit_amount'] !== null ? '₱' . number_format((float)$program['benefit_amount']) : '—' ?></td>
                <td><?= $program['close_date'] ? e(date('Y-m-d', strtotime($program['close_date']))) : '—' ?><br><small><?= $program['slot_limit'] ? (int)$program['slot_limit'] . ' slots' : 'No slot limit' ?></small></td>
                <td><?= e($scopeLabels ? implode(', ', $scopeLabels) : 'All colleges/programs') ?><br><small><?= e($program['eligibility_notes'] ?: '') ?></small></td>
                <td style="white-space:normal;min-width:180px"><?= e($program['requirements'] ?: '—') ?></td>
                <td><?= badge(ucfirst($displayStatus)) ?></td>
                <td>
                    <div class="acts">
                        <a class="btn sm sec" href="scholarships.php?edit=<?= (int)$program['scholarship_program_id'] ?>#scholarship-form">Edit</a>
                        <?php if ($displayStatus !== 'archived'): ?>
                            <form method="post" data-confirm="<?= $displayStatus === 'open' ? 'Close' : 'Open' ?> this scholarship?">
                                <?= csrf_field() ?><input type="hidden" name="action" value="operation">
                                <input type="hidden" name="program_id" value="<?= (int)$program['scholarship_program_id'] ?>">
                                <input type="hidden" name="operation" value="<?= $displayStatus === 'open' ? 'close' : 'open' ?>">
                                <button class="btn sm"><?= $displayStatus === 'open' ? 'Close' : 'Open' ?></button>
                            </form>
                            <form method="post" data-confirm="Archive this scholarship? It will no longer appear to students.">
                                <?= csrf_field() ?><input type="hidden" name="action" value="operation">
                                <input type="hidden" name="program_id" value="<?= (int)$program['scholarship_program_id'] ?>">
                                <input type="hidden" name="operation" value="archive">
                                <button class="btn sm sec">Archive</button>
                            </form>
                        <?php else: ?>
                            <form method="post" data-confirm="Restore this scholarship?">
                                <?= csrf_field() ?><input type="hidden" name="action" value="operation">
                                <input type="hidden" name="program_id" value="<?= (int)$program['scholarship_program_id'] ?>">
                                <input type="hidden" name="operation" value="restore">
                                <button class="btn sm sec">Restore</button>
                            </form>
                        <?php endif; ?>
                        <form method="post" data-confirm="Permanently delete this scholarship? This cannot be undone.">
                            <?= csrf_field() ?><input type="hidden" name="action" value="operation">
                            <input type="hidden" name="program_id" value="<?= (int)$program['scholarship_program_id'] ?>">
                            <input type="hidden" name="operation" value="delete">
                            <button class="btn sm dng">Delete</button>
                        </form>
                    </div>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
<?php endif; ?>
<?= pager($total, $page, $size) ?>

<dialog class="scholarship-dialog" id="scholarship-dialog" data-open="<?= ($editId || $errors) ? 'true' : 'false' ?>" aria-labelledby="scholarship-dialog-title">
<form method="post" class="box scholarship-form" id="scholarship-form">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="save">
    <input type="hidden" name="program_id" value="<?= (int)$editId ?>">
    <header class="scholarship-form-heading">
        <h2 id="scholarship-dialog-title"><?= $editId ? 'Edit Scholarship' : 'Add Scholarship' ?></h2>
        <button class="scholarship-icon-close" id="close-scholarship-dialog" type="button" aria-label="Close dialog">×</button>
    </header>
    <div class="scholarship-form-body">
        <p class="mut"><span class="req">*</span> Required field</p>
        <?php if (isset($errors['form'])): ?><div class="alert bad" role="alert"><?= e($errors['form']) ?></div><?php endif; ?>
        <section class="scholarship-section" aria-labelledby="basic-information-title">
            <h3 id="basic-information-title">Basic information</h3>
            <div class="scholarship-grid scholarship-basic-grid">
            <?= field('program_code', 'Short code', 'text', $values, $errors) ?>
            <?= field('program_name', 'Scholarship name', 'text', $values, $errors) ?>
            </div>
            <div class="f scholarship-full-field">
                <label for="description">Description <span class="opt">(optional)</span></label>
                <textarea id="description" name="description" rows="3" maxlength="65535"><?= e($values['description']) ?></textarea>
                <?php if (isset($errors['description'])): ?><div class="err">✕ <?= e($errors['description']) ?></div><?php endif; ?>
            </div>
        </section>

        <section class="scholarship-section" aria-labelledby="provider-grant-title">
            <h3 id="provider-grant-title">Provider and grant</h3>
            <div class="scholarship-grid scholarship-provider-grid">
                <div class="f">
                <label for="provider_type">Provider type</label>
                <select id="provider_type" name="provider_type">
                    <?php foreach (['government' => 'Government', 'private' => 'Private', 'institutional' => 'Institutional (school)'] as $key => $label): ?>
                        <option value="<?= e($key) ?>"<?= $values['provider_type'] === $key ? ' selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
                </div>
                <?= field('provider_name', 'Provider name', 'text', $values, $errors, false) ?>
                <div class="f">
                    <label for="benefit_amount">Grant per semester (₱) <span class="req" aria-hidden="true">*</span></label>
                    <input id="benefit_amount" name="benefit_amount" type="text" inputmode="numeric" pattern="[0-9]*" data-digit-only value="<?= e($values['benefit_amount']) ?>" required>
                    <?php if (isset($errors['benefit_amount'])): ?><div class="err">✕ <?= e($errors['benefit_amount']) ?></div><?php endif; ?>
                </div>
                <div class="f">
                    <label for="slot_limit">Available slots <span class="opt">(optional)</span></label>
                    <input id="slot_limit" name="slot_limit" type="text" inputmode="numeric" pattern="[0-9]*" data-digit-only value="<?= e($values['slot_limit']) ?>">
                    <?php if (isset($errors['slot_limit'])): ?><div class="err">✕ <?= e($errors['slot_limit']) ?></div><?php endif; ?>
                </div>
            </div>
        </section>

        <section class="scholarship-section" aria-labelledby="schedule-title">
            <h3 id="schedule-title">Schedule</h3>
            <div class="scholarship-grid scholarship-schedule-grid">
                <?= field('school_year', 'School year (e.g. 2026-2027)', 'text', $values, $errors) ?>
                <div class="f">
                    <label for="semester">Semester</label>
                    <select id="semester" name="semester">
                        <?php foreach (['1st', '2nd', 'Summer'] as $semester): ?>
                            <option value="<?= e($semester) ?>"<?= $values['semester'] === $semester ? ' selected' : '' ?>><?= e($semester) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?= field('open_date', 'Applications open', 'date', $values, $errors) ?>
                <?= field('close_date', 'Application deadline', 'date', $values, $errors) ?>
            </div>
        </section>

        <section class="scholarship-section" aria-labelledby="eligibility-title">
            <h3 id="eligibility-title">Eligibility</h3>
            <div class="f scholarship-full-field">
                <label for="eligibility_notes">Eligibility notes <span class="opt">(optional)</span></label>
                <textarea id="eligibility_notes" name="eligibility_notes" rows="3" maxlength="65535"><?= e($values['eligibility_notes']) ?></textarea>
                <?php if (isset($errors['eligibility_notes'])): ?><div class="err">✕ <?= e($errors['eligibility_notes']) ?></div><?php endif; ?>
            </div>
            <fieldset class="scholarship-picker">
                <legend>Eligible colleges and programs</legend>
                <p class="mut scholarship-helper">Leave all unchecked to allow every college and program.</p>
                <div class="scholarship-college-list" id="eligible-college-list">
                <?php foreach ($catalog as $collegeCode => $college): ?>
                    <?php
                    $collegeProgramCodes = array_keys($college['programs']);
                    $collegeProgramCount = count($collegeProgramCodes);
                    $collegeSelectedCount = in_array($collegeCode . ':*', $chosenEligibility, true)
                        ? $collegeProgramCount
                        : count(array_filter(
                            $collegeProgramCodes,
                            static fn(string $programCode): bool => in_array($collegeCode . '|' . $programCode, $chosenEligibility, true)
                        ));
                    ?>
                    <details class="scholarship-college" data-college="<?= e($collegeCode) ?>">
                        <summary class="scholarship-college-summary">
                            <span class="scholarship-chevron" aria-hidden="true"></span>
                            <label class="scholarship-check-row scholarship-college-toggle">
                                <input type="checkbox" data-college-toggle value="<?= e($collegeCode) ?>:*"<?= in_array($collegeCode . ':*', $chosenEligibility, true) ? ' checked' : '' ?>>
                                <span><?= e($college['name']) ?></span>
                            </label>
                            <span class="scholarship-count" data-college-count><?= $collegeSelectedCount ?> of <?= $collegeProgramCount ?> selected</span>
                        </summary>
                        <div class="scholarship-program-list">
                            <?php foreach ($college['programs'] as $programCode => $program): ?>
                                <?php $programIsSelected = in_array($collegeCode . ':*', $chosenEligibility, true) || in_array($collegeCode . '|' . $programCode, $chosenEligibility, true); ?>
                                <label class="scholarship-check-row scholarship-program-row">
                                    <input type="checkbox" name="eligible[]" value="<?= e($collegeCode . '|' . $programCode) ?>" data-college-program="<?= e($collegeCode) ?>"<?= $programIsSelected ? ' checked' : '' ?>>
                                    <span><?= e($program['name']) ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </details>
                <?php endforeach; ?>
                </div>
                <p class="scholarship-eligibility-summary" id="eligibility-summary" aria-live="polite"></p>
            </fieldset>
        </section>

        <section class="scholarship-section" aria-labelledby="documents-title">
            <h3 id="documents-title">Documents</h3>
            <div class="scholarship-document-grid">
                <fieldset class="scholarship-picker">
                    <legend>Required documents <span class="req" aria-hidden="true">*</span></legend>
                    <div class="scholarship-document-list">
                <?php foreach ($documentTypes as $docId => $docName): ?>
                        <label class="scholarship-check-row scholarship-document-row">
                            <input type="checkbox" name="docs[]" value="<?= (int)$docId ?>" data-required-document<?= in_array((int)$docId, $chosenDocs, true) ? ' checked' : '' ?>>
                            <span><?= e($docName) ?></span>
                        </label>
                <?php endforeach; ?>
                    </div>
                    <?php if (isset($errors['docs'])): ?><div class="err">✕ <?= e($errors['docs']) ?></div><?php endif; ?>
                </fieldset>
                <fieldset class="scholarship-picker">
                    <legend>Optional documents</legend>
                    <div class="scholarship-document-list">
                <?php foreach ($documentTypes as $docId => $docName): ?>
                        <label class="scholarship-check-row scholarship-document-row">
                            <input type="checkbox" name="optional_docs[]" value="<?= (int)$docId ?>" data-optional-document<?= in_array((int)$docId, $chosenOptionalDocs, true) ? ' checked' : '' ?>>
                            <span><?= e($docName) ?></span>
                        </label>
                <?php endforeach; ?>
                    </div>
                </fieldset>
            </div>
        </section>
    </div>
    <footer class="scholarship-form-footer">
        <button class="btn sec scholarship-cancel" id="cancel-scholarship-dialog" type="button">Cancel</button>
        <button class="btn gold" type="submit"><?= $editId ? 'Save Changes' : 'Save Scholarship' ?></button>
    </footer>
</form>
</dialog>
<?php require __DIR__ . '/includes/footer.php'; ?>
