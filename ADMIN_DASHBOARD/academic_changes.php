<?php
require_once __DIR__ . '/includes/functions.php';
$me = require_admin();
$catalog = require __DIR__ . '/../Registration_and_Login(UIUX)/academic_catalog.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $requestId = filter_var($_POST['request_id'] ?? null, FILTER_VALIDATE_INT);
    $decision = (string)($_POST['decision'] ?? '');
    if (!$requestId || !in_array($decision, ['approved', 'rejected'], true)) {
        set_flash('Choose a valid academic change request and decision.', 'bad');
        redirect('academic_changes.php');
    }
    try {
        $pdo->beginTransaction();
        $query = $pdo->prepare(
            "SELECT request_id, applicant_id, requested_year_level, requested_college_code,
                    requested_program_code, requested_specialization
             FROM academic_change_requests WHERE request_id = ? AND status = 'pending' FOR UPDATE"
        );
        $query->execute([$requestId]);
        $request = $query->fetch();
        if (!$request) throw new RuntimeException('This request has already been reviewed.');
        if ($decision === 'approved') {
            $college = $catalog[$request['requested_college_code']] ?? null;
            $program = $college['programs'][$request['requested_program_code']] ?? null;
            if (!$program || (int)$request['requested_year_level'] < 1 || (int)$request['requested_year_level'] > 5
                || ($program['requires_specialization'] && !in_array($request['requested_specialization'], $program['specializations'], true))
                || (!$program['requires_specialization'] && $request['requested_specialization'] !== null)) {
                throw new RuntimeException('The requested academic combination is invalid.');
            }
            $pdo->prepare(
                'UPDATE applicant_academic_profiles
                 SET year_level = ?, college_code = ?, program_code = ?, program_type = ?, specialization = ?, approved_at = NOW()
                 WHERE applicant_id = ?'
            )->execute([
                $request['requested_year_level'], $request['requested_college_code'],
                $request['requested_program_code'], $program['type'],
                $request['requested_specialization'], $request['applicant_id'],
            ]);
        }
        $pdo->prepare(
            'UPDATE academic_change_requests SET status = ?, reviewed_by = ?, reviewed_at = NOW() WHERE request_id = ?'
        )->execute([$decision, (int)$me['user_id'], $requestId]);
        $pdo->commit();
        set_flash('Academic change request ' . $decision . '.', 'ok');
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log('LNU academic change review error: ' . $error->getMessage());
        set_flash($error instanceof RuntimeException ? $error->getMessage() : 'Could not review the request.', 'bad');
    }
    redirect('academic_changes.php');
}

$requests = rows(
    $pdo,
    'SELECT r.request_id, r.requested_year_level, r.requested_college_code, r.requested_program_code,
            r.requested_specialization, r.reason, r.status, r.requested_at,
            a.student_number, a.first_name, a.last_name
     FROM academic_change_requests r
     JOIN applicants a ON a.applicant_id = r.applicant_id
     ORDER BY CASE WHEN r.status = \'pending\' THEN 0 ELSE 1 END, r.requested_at DESC'
);
$title = 'Academic Change Requests';
require __DIR__ . '/includes/header.php';
?>
<?php if (!$requests): ?>
    <div class="box c"><h3>No academic change requests</h3><p class="mut">Student requests will appear here for approval.</p></div>
<?php else: ?>
    <div class="tw"><table>
        <thead><tr><th>Student</th><th>Requested program</th><th>Reason</th><th>Status</th><th>Action</th></tr></thead>
        <tbody>
        <?php foreach ($requests as $request):
            $college = $catalog[$request['requested_college_code']] ?? null;
            $program = $college['programs'][$request['requested_program_code']] ?? null;
        ?>
            <tr>
                <td><?= e($request['last_name'] . ', ' . $request['first_name']) ?><br><small><?= e($request['student_number']) ?></small></td>
                <td><?= e($college['name'] ?? $request['requested_college_code']) ?><br><?= e($program['name'] ?? $request['requested_program_code']) ?> · Year <?= (int)$request['requested_year_level'] ?><?php if ($request['requested_specialization']): ?><br><?= e($request['requested_specialization']) ?><?php endif; ?></td>
                <td><?= e($request['reason']) ?></td>
                <td><?= badge(ucfirst($request['status'])) ?></td>
                <td><?php if ($request['status'] === 'pending'): ?>
                    <form method="post" class="inline-form"><?= csrf_field() ?><input type="hidden" name="request_id" value="<?= (int)$request['request_id'] ?>"><button class="btn sm" name="decision" value="approved">Approve</button><button class="btn sm sec" name="decision" value="rejected">Reject</button></form>
                <?php else: ?><?= e(date('M j, Y', strtotime($request['requested_at']))) ?><?php endif; ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
<?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
