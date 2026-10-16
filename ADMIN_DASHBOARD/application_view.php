<?php
require_once __DIR__ . '/includes/functions.php';
$me = require_admin();

$id = (int)($_GET['id'] ?? 0);

// The application with its student and scholarship
$st = $pdo->prepare("
    SELECT a.*, ap.student_number, ap.last_name, ap.first_name, ap.year_level,
           c.college_name, u.email, sp.program_name, p.school_year, p.semester
    FROM applications a
    JOIN applicants ap ON ap.applicant_id = a.applicant_id
    LEFT JOIN colleges c ON c.college_id = ap.college_id
    JOIN users u ON u.user_id = ap.user_id
    JOIN scholarship_programs sp ON sp.scholarship_program_id = a.scholarship_program_id
    LEFT JOIN application_periods p ON p.period_id = a.period_id
    WHERE a.application_id = ?");
$st->execute([$id]);
$app = $st->fetch();
if (!$app) {
    http_response_code(404);
    exit('Application not found.');
}
$isOpen = in_array($app['status'], ['submitted', 'under_review', 'for_compliance'], true);

// Save a decision or a duplicate review
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $to = $_POST['to'] ?? '';

    if (isset($_POST['flag_id']) && in_array($_POST['verdict'] ?? '', ['confirmed_duplicate', 'not_duplicate'], true)) {
        $st = $pdo->prepare('UPDATE duplicate_flags SET review_status = ?, reviewed_by = ?, reviewed_at = NOW()
                             WHERE flag_id = ? AND application_id = ?');
        $st->execute([$_POST['verdict'], $me['user_id'], (int)$_POST['flag_id'], $id]);
        set_flash('Duplicate review saved.');
    } elseif ($isOpen && in_array($to, ['under_review', 'approved', 'rejected', 'for_compliance'], true)) {
        $note      = mb_substr(trim($_POST['note'] ?? ''), 0, 500);
        $isDecided = in_array($to, ['approved', 'rejected'], true);

        $pdo->beginTransaction();
        $st = $pdo->prepare('UPDATE applications
                             SET status = ?, decided_by = ?, decided_at = ?, decision_remarks = ?
                             WHERE application_id = ?');
        $st->execute([
            $to,
            $isDecided ? $me['user_id'] : $app['decided_by'],
            $isDecided ? date('Y-m-d H:i:s') : $app['decided_at'],
            $isDecided ? $note : $app['decision_remarks'],
            $id,
        ]);
        $st = $pdo->prepare('INSERT INTO application_status_history (application_id, previous_status, new_status, changed_by, remarks)
                             VALUES (?, ?, ?, ?, ?)');
        $st->execute([$id, $app['status'], $to, $me['user_id'], $note ?: null]);
        $pdo->commit();
        set_flash('Application status updated successfully.');
    } else {
        set_flash('This application can no longer be changed.', 'warn');
    }
    header("Location: application_view.php?id=$id");
    exit;
}

// Submitted documents
$docs = rows($pdo, "
    SELECT d.verification_status, d.staff_remarks, t.type_name
    FROM application_documents d
    JOIN document_types t ON t.document_type_id = d.document_type_id
    WHERE d.application_id = ? AND d.is_current = 1", [$id]);

// OCR verification (student-entered value beside the value read from the document)
$ocr = rows($pdo, "
    SELECT c.field_name, c.applicant_value, c.extracted_value, c.result, t.type_name
    FROM ocr_field_checks c
    JOIN ocr_results o ON o.ocr_id = c.ocr_id
    JOIN application_documents d ON d.document_id = o.document_id
    JOIN document_types t ON t.document_type_id = d.document_type_id
    WHERE d.application_id = ? AND d.is_current = 1", [$id]);

// Review priority (latest ML prediction)
$priority = rows($pdo, "
    SELECT review_priority FROM ml_predictions
    WHERE application_id = ? ORDER BY predicted_at DESC LIMIT 1", [$id])[0]['review_priority'] ?? null;

// Possible duplicates
$dups = rows($pdo, "
    SELECT f.flag_id, f.similarity_score, f.matched_fields, f.review_status,
           a2.application_no, ap2.student_number, ap2.last_name, ap2.first_name
    FROM duplicate_flags f
    JOIN applications a2 ON a2.application_id = f.matched_application_id
    JOIN applicants ap2  ON ap2.applicant_id = a2.applicant_id
    WHERE f.application_id = ?", [$id]);

// Staff notes and decision history
$history = rows($pdo, "
    SELECT h.new_status, h.remarks, h.changed_at,
           CONCAT(COALESCE(s.first_name, ''), ' ', COALESCE(s.last_name, '')) AS staff_name
    FROM application_status_history h
    LEFT JOIN staff_profiles s ON s.user_id = h.changed_by
    WHERE h.application_id = ?
    ORDER BY h.changed_at DESC", [$id]);

$docLabels = ['pending' => 'Pending', 'verified' => 'Verified', 'flagged' => 'Needs Correction', 'needs_resubmission' => 'Needs Correction'];
$dupLabels = ['pending' => 'Needs review', 'confirmed_duplicate' => 'Confirmed duplicate', 'not_duplicate' => 'Not a duplicate'];

$title = 'Review Application';
require __DIR__ . '/includes/header.php';
?>
<a href="applications.php">← Back to Applications</a>

<div class="box" style="margin-top:10px">
    <h2><?= e($app['last_name'] . ', ' . $app['first_name']) ?></h2>
    <p class="mut"><?= e($app['application_no'] . ' · ' . $app['program_name']) ?></p>
    <p><?= status_badge($app['status']) ?>
        <?= $isOpen ? 'Next step: check the documents below, then decide.' : 'This application is ' . e(STATUS[$app['status']] ?? '') . ' and can no longer be changed.' ?></p>

    <?php if ($isOpen): ?>
    <form method="post" data-confirm="Change this application's status? This is recorded in the decision history.">
        <?= csrf_field() ?>
        <div class="f">
            <label for="note">Staff note <span class="opt">(optional)</span></label>
            <input id="note" name="note" maxlength="500">
        </div>
        <div class="acts">
            <?php if ($app['status'] === 'submitted'): ?><button class="btn sec" name="to" value="under_review">Start Review</button><?php endif; ?>
            <button class="btn" name="to" value="approved">Approve</button>
            <button class="btn dng" name="to" value="rejected">Reject</button>
            <button class="btn sec" name="to" value="for_compliance">Return for Correction</button>
        </div>
    </form>
    <?php endif; ?>
</div>

<div class="grid2">
    <section class="box">
        <h3>Student Information</h3>
        <dl>
            <dt>Student number</dt><dd><?= e($app['student_number']) ?></dd>
            <dt>College</dt><dd><?= e($app['college_name']) ?></dd>
            <dt>Year level</dt><dd><?= e($app['year_level']) ?></dd>
            <dt>Email</dt><dd><?= e($app['email']) ?></dd>
        </dl>
    </section>
    <section class="box">
        <h3>Scholarship Information</h3>
        <dl>
            <dt>Program</dt><dd><?= e($app['program_name']) ?></dd>
            <dt>Period</dt><dd><?= e($app['school_year'] . ' ' . $app['semester'] . ' sem') ?></dd>
            <dt>Submitted</dt><dd><?= e($app['submitted_at']) ?></dd>
        </dl>
    </section>
</div>

<section class="box">
    <h3>Submitted Documents</h3>
    <?php if (!$docs): ?><p class="mut">No documents uploaded yet.</p><?php else: ?>
    <div class="tw"><table>
        <thead><tr><th>Document</th><th>Status</th><th>Staff remarks</th></tr></thead>
        <tbody>
        <?php foreach ($docs as $d): ?>
            <tr>
                <td><?= e($d['type_name']) ?></td>
                <td><?= badge($docLabels[$d['verification_status']]) ?></td>
                <td><?= e($d['staff_remarks'] ?: '—') ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
    <?php endif; ?>
</section>

<section class="box">
    <h3>OCR Verification</h3>
    <p class="mut">Student-entered values are compared with text read from the uploaded documents. Differences need staff verification.</p>
    <?php if (!$ocr): ?><p class="mut">No OCR results yet.</p><?php else: ?>
    <div class="tw"><table>
        <thead><tr><th>Document</th><th>Field</th><th>Student entered</th><th>Read from document</th><th>Result</th></tr></thead>
        <tbody>
        <?php foreach ($ocr as $o): ?>
            <tr>
                <td><?= e($o['type_name']) ?></td>
                <td><?= e($o['field_name']) ?></td>
                <td><?= e($o['applicant_value']) ?></td>
                <td><?= e($o['extracted_value']) ?></td>
                <td>
                    <?php if ($o['result'] === 'match'): ?><span class="bd ok">✓ Match</span>
                    <?php elseif ($o['result'] === 'mismatch'): ?><span class="bd warn">! Needs verification</span>
                    <?php else: ?><span class="bd bad">✕ Unreadable</span><?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
    <?php endif; ?>
</section>

<div class="grid2">
    <section class="box">
        <h3>Review Priority</h3>
        <?= $priority ? badge($priority) . ' Priority' : '<span class="mut">Not scored yet</span>' ?>
        <p class="mut">This label only helps organize the review queue. It does not decide scholarship eligibility.</p>
    </section>

    <section class="box">
        <h3>Possible Duplicates</h3>
        <?php if (!$dups): ?><p class="mut">No possible duplicates found.</p><?php else: ?>
        <p class="mut">Compare before deciding. Nothing is rejected automatically.</p>
        <?php foreach ($dups as $d): ?>
            <div class="item" style="flex-wrap:wrap">
                <span><b><?= e($d['application_no']) ?></b> · <?= e($d['student_number'] . ' · ' . $d['last_name'] . ', ' . $d['first_name']) ?>
                    · <?= e(round($d['similarity_score']) . '% match: ' . implode(', ', (array)json_decode($d['matched_fields'] ?? '[]', true))) ?></span>
                <?php if ($d['review_status'] === 'pending'): ?>
                    <form method="post" class="acts" data-confirm="Save this duplicate review?">
                        <?= csrf_field() ?>
                        <input type="hidden" name="flag_id" value="<?= $d['flag_id'] ?>">
                        <button class="btn sm sec" name="verdict" value="not_duplicate">Not a duplicate</button>
                        <button class="btn sm dng" name="verdict" value="confirmed_duplicate">Confirm duplicate</button>
                    </form>
                <?php else: ?>
                    <span class="mut"><?= e($dupLabels[$d['review_status']]) ?></span>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </section>
</div>

<section class="box">
    <h3>Staff Notes and Decision History</h3>
    <?php foreach ($history as $h): ?>
        <div class="item">
            <?= e($h['changed_at'] . ' — ' . (STATUS[$h['new_status']] ?? $h['new_status'])
                . (trim($h['staff_name']) !== '' ? ' by ' . trim($h['staff_name']) : '')
                . ($h['remarks'] ? ': ' . $h['remarks'] : '')) ?>
        </div>
    <?php endforeach; ?>
    <?php if (!$history): ?><p class="mut">No decisions recorded yet.</p><?php endif; ?>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
