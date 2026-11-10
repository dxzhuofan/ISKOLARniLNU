<?php
require_once __DIR__ . '/includes/functions.php';
$me = require_admin();

$q = trim((string)($_GET['q'] ?? ''));
$action = trim((string)($_GET['action'] ?? ''));
$entity = trim((string)($_GET['entity'] ?? ''));
$size = page_size();
$page = max(1, (int)($_GET['page'] ?? 1));
$where = [];
$params = [];
if ($q !== '') {
    $where[] = '(u.email LIKE ? OR CONCAT(s.first_name, " ", s.last_name) LIKE ? OR l.details LIKE ? OR l.entity_id LIKE ?)';
    array_push($params, "%$q%", "%$q%", "%$q%", "%$q%");
}
if ($action !== '') {
    $where[] = 'l.action = ?';
    $params[] = $action;
}
if ($entity !== '') {
    $where[] = 'l.entity_type = ?';
    $params[] = $entity;
}
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
$from = 'admin_audit_log l
    JOIN users u ON u.user_id = l.actor_user_id
    LEFT JOIN staff_profiles s ON s.user_id = l.actor_user_id';
$total = scalar($pdo, "SELECT COUNT(*) FROM $from $whereSql", $params);
$page = min($page, max(1, (int)ceil($total / $size)));
$offset = ($page - 1) * $size;
$entries = rows($pdo, "
    SELECT l.audit_id, l.action, l.entity_type, l.entity_id, l.details, l.created_at,
           u.email, s.first_name, s.last_name
    FROM $from $whereSql
    ORDER BY l.created_at DESC, l.audit_id DESC
    LIMIT $size OFFSET $offset", $params);

$title = 'Audit Log';
require __DIR__ . '/includes/header.php';
?>
<form class="bar" method="get">
    <label class="sr" for="q">Search audit log</label>
    <input id="q" name="q" type="search" placeholder="Search staff, entity ID or details" value="<?= e($q) ?>">
    <select name="action" aria-label="Action">
        <option value="">All actions</option>
        <?php foreach (['create', 'edit', 'archive', 'restore', 'open', 'close', 'approved', 'rejected', 'for_compliance', 'under_review', 'delete', 'confirmed_duplicate', 'not_duplicate'] as $value): ?>
            <option value="<?= e($value) ?>"<?= $action === $value ? ' selected' : '' ?>><?= e(ucwords(str_replace('_', ' ', $value))) ?></option>
        <?php endforeach; ?>
    </select>
    <select name="entity" aria-label="Entity">
        <option value="">All records</option>
        <?php foreach (['scholarship_program', 'application', 'duplicate_flag'] as $value): ?>
            <option value="<?= e($value) ?>"<?= $entity === $value ? ' selected' : '' ?>><?= e(ucwords(str_replace('_', ' ', $value))) ?></option>
        <?php endforeach; ?>
    </select>
    <button class="btn">Filter</button>
    <?php if ($q !== '' || $action !== '' || $entity !== ''): ?><a class="btn sec" href="audit_log.php">Clear</a><?php endif; ?>
</form>

<?php if (!$entries): ?>
    <div class="box c"><h3>No audit entries found</h3><p class="mut">Scholarship edits and application decisions will be recorded here.</p></div>
<?php else: ?>
    <div class="tw"><table>
        <thead><tr><th>When</th><th>Staff member</th><th>Action</th><th>Record</th><th>Details</th></tr></thead>
        <tbody>
        <?php foreach ($entries as $entry):
            $details = json_decode((string)$entry['details'], true);
            $detailText = $details
                ? json_encode($details, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
                : (string)$entry['details'];
        ?>
            <tr>
                <td><?= e(date('Y-m-d H:i:s', strtotime($entry['created_at']))) ?></td>
                <td><?= e(trim(($entry['first_name'] ?? '') . ' ' . ($entry['last_name'] ?? '')) ?: $entry['email']) ?><br><small><?= e($entry['email']) ?></small></td>
                <td><?= badge(ucwords(str_replace('_', ' ', $entry['action']))) ?></td>
                <td><?= e(ucwords(str_replace('_', ' ', $entry['entity_type']))) ?> #<?= (int)$entry['entity_id'] ?></td>
                <td style="white-space:normal;min-width:300px"><pre><?= e($detailText ?: '—') ?></pre></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
<?php endif; ?>
<?= pager($total, $page, $size) ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
