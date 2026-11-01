<?php
require_once __DIR__ . '/includes/functions.php';
$me = require_application_reviewer();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'bulk_review') {
    check_csrf();
    $target = (string)($_POST['decision'] ?? '');
    $submittedIds = $_POST['application_ids'] ?? [];
    $applicationIds = array_values(array_unique(array_filter(
        array_map('intval', is_array($submittedIds) ? $submittedIds : []),
        static fn(int $id): bool => $id > 0
    )));
    $remarks = mb_substr(trim((string)($_POST['remarks'] ?? '')), 0, 500);

    if (!in_array($target, ['approved', 'rejected'], true) || !$applicationIds) {
        set_flash('Select one or more applications and a valid decision.', 'bad');
        header('Location: applications.php');
        exit;
    }

    $notifications = [];
    $updated = 0;
    $skippedAtCapacity = 0;
    try {
        $pdo->beginTransaction();
        $find = $pdo->prepare(
            "SELECT a.application_id, a.period_id, a.status, a.application_no, ap.first_name, ap.last_name, u.email
             FROM applications a
             JOIN applicants ap ON ap.applicant_id = a.applicant_id
             JOIN users u ON u.user_id = ap.user_id
             WHERE a.application_id = ? FOR UPDATE"
        );
        $save = $pdo->prepare(
            'UPDATE applications SET status = ?, decided_by = ?, decided_at = NOW(), decision_remarks = ?
             WHERE application_id = ?'
        );
        $history = $pdo->prepare(
            'INSERT INTO application_status_history (application_id, previous_status, new_status, changed_by, remarks)
             VALUES (?, ?, ?, ?, ?)'
        );

        foreach ($applicationIds as $applicationId) {
            $find->execute([$applicationId]);
            $application = $find->fetch();
            if (!$application || !in_array($application['status'], ['submitted', 'under_review', 'for_compliance'], true)) {
                continue;
            }
            if ($target === 'approved' && $application['period_id'] !== null) {
                $periodLock = $pdo->prepare('SELECT slot_limit FROM application_periods WHERE period_id = ? FOR UPDATE');
                $periodLock->execute([$application['period_id']]);
                $slotLimit = $periodLock->fetchColumn();
                if ($slotLimit !== false && $slotLimit !== null
                    && scalar($pdo, "SELECT COUNT(*) FROM applications WHERE period_id = ? AND status = 'approved'", [$application['period_id']]) >= (int)$slotLimit) {
                    $skippedAtCapacity++;
                    continue;
                }
            }
            $save->execute([$target, (int)$me['user_id'], $remarks ?: null, $applicationId]);
            $history->execute([$applicationId, $application['status'], $target, (int)$me['user_id'], $remarks ?: null]);
            audit_log($pdo, (int)$me['user_id'], $target, 'application', $applicationId, [
                'previous_status' => $application['status'],
                'new_status' => $target,
                'remarks' => $remarks,
                'application_no' => $application['application_no'],
            ]);
            $notifications[] = [
                'email' => $application['email'],
                'name' => trim($application['first_name'] . ' ' . $application['last_name']),
                'application_no' => $application['application_no'],
            ];
            $updated++;
        }
        $pdo->commit();
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('LNU bulk application review error: ' . $error->getMessage());
        set_flash('The selected applications could not be updated. Check the server log.', 'bad');
        header('Location: applications.php');
        exit;
    }

    $failedEmails = 0;
    foreach ($notifications as $notification) {
        $subject = 'Update on your LNU scholarship application';
        $body = "Dear {$notification['name']},\n\n"
            . "Your application {$notification['application_no']} has been "
            . ($target === 'approved' ? 'approved' : 'rejected') . ".\n";
        if ($remarks !== '') {
            $body .= "\nRemarks from the Scholarship Office:\n{$remarks}\n";
        }
        if (!send_portal_email((string)$notification['email'], $subject, $body)) {
            $failedEmails++;
        }
    }

    $message = $updated . ' application' . ($updated === 1 ? '' : 's') . ' updated.';
    if ($skippedAtCapacity) {
        $message .= ' ' . $skippedAtCapacity . ' skipped because the scholarship is at its slot limit.';
    }
    if ($failedEmails) {
        $message .= ' ' . $failedEmails . ' student email' . ($failedEmails === 1 ? '' : 's') . ' could not be sent; check the server mail configuration.';
        set_flash($message, 'warn');
    } elseif ($updated === 0) {
        set_flash($skippedAtCapacity
            ? 'No selected applications could be approved because the scholarship is at its slot limit.'
            : 'No selected applications were in a reviewable status.', 'warn');
    } else {
        set_flash($message);
    }
    header('Location: applications.php');
    exit;
}

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
    <form method="post" data-confirm="Apply this decision to all selected applications?">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="bulk_review">
    <div class="bar box">
        <label><input type="checkbox" id="select-all-applications"> Select all on this page</label>
        <input type="text" name="remarks" maxlength="500" placeholder="Optional remarks (emailed to students)">
        <button class="btn" name="decision" value="approved">Approve selected</button>
        <button class="btn dng" name="decision" value="rejected">Reject selected</button>
    </div>
    <div class="tw"><table>
        <thead><tr><th>Application</th><th>Student</th><th>Scholarship</th><th>Submitted</th><th>Status</th><th>Review priority</th><th>Check needed</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($apps as $a): ?>
            <tr>
                <td>
                    <?php if (in_array($a['status'], ['submitted', 'under_review', 'for_compliance'], true)): ?>
                        <label><input type="checkbox" name="application_ids[]" value="<?= (int)$a['application_id'] ?>" class="application-select"> <?= e($a['application_no']) ?></label>
                    <?php else: ?><?= e($a['application_no']) ?><?php endif; ?>
                </td>
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
    </form>
<?php endif; ?>
<?= pager($total, $page, $size) ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
