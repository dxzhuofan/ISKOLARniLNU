<?php
require_once __DIR__ . '/includes/functions.php';
$me = require_student();
$aid = (int)$me['applicant_id'];

$st = $pdo->prepare("SELECT p.*, ap.period_id, ap.open_date, ap.close_date, ap.status AS period_status, ap.school_year, ap.semester,
        x.status AS my_status, x.application_id AS my_app
    FROM scholarship_programs p
    LEFT JOIN application_periods ap ON ap.period_id = (
        SELECT p2.period_id FROM application_periods p2
        WHERE p2.scholarship_program_id = p.scholarship_program_id AND p2.status IN ('open','closed')
        ORDER BY (p2.status = 'open') DESC, p2.close_date DESC LIMIT 1)
    LEFT JOIN applications x ON x.period_id = ap.period_id AND x.scholarship_program_id = p.scholarship_program_id AND x.applicant_id = ?
    WHERE p.is_active = 1 ORDER BY p.program_name");
$st->execute([$aid]);
$programs = $st->fetchAll();

$reqs = [];
foreach ($pdo->query('SELECT r.scholarship_program_id AS pid, r.document_type_id, r.is_required, dt.type_name
                      FROM program_requirements r JOIN document_types dt ON dt.document_type_id = r.document_type_id ORDER BY r.is_required DESC, dt.type_name') as $r) {
    $reqs[$r['pid']][] = $r;
}
$sponsors = array_values(array_unique(array_filter(array_column($programs, 'provider_name'))));
sort($sponsors);
$typeLabel = ['government' => 'Government', 'private' => 'Private', 'institutional' => 'LNU / Institutional'];

$pageTitle = 'Scholarships'; $active = 'scholarships';
include __DIR__ . '/includes/header.php';
?>
<section class="hero">
  <div>
    <h1 class="serif">Available Scholarships</h1>
    <p class="muted">Explore scholarship programs and educational assistance managed by LNU, government agencies and private sponsors.</p>
  </div>
</section>

<div class="filters" id="schFilters">
  <input type="search" id="fSearch" placeholder="Search by scholarship or sponsor" aria-label="Search scholarships">
  <select id="fType" aria-label="Category">
    <option value="">All categories</option>
    <?php foreach ($typeLabel as $k => $v): ?><option value="<?= $k ?>"><?= e($v) ?></option><?php endforeach; ?>
  </select>
  <select id="fSponsor" aria-label="Sponsor">
    <option value="">All sponsors</option>
    <?php foreach ($sponsors as $s): ?><option value="<?= e($s) ?>"><?= e($s) ?></option><?php endforeach; ?>
  </select>
  <select id="fElig" aria-label="Eligibility">
    <option value="">Any eligibility</option>
    <option value="income">Needs proof of income or indigency</option>
    <option value="none">No income documents required</option>
  </select>
  <select id="fState" aria-label="Application status">
    <option value="">Any application status</option>
    <option value="open">Open</option>
    <option value="upcoming">Opens soon</option>
    <option value="closed">Closed</option>
    <option value="applied">Already applied</option>
  </select>
</div>

<div class="cards" id="schCards">
<?php foreach ($programs as $p):
    if ($p['period_id']) { [$state, $days] = period_state($p['open_date'], $p['close_date'], $p['period_status']); } else { $state = 'closed'; $days = 0; }
    $list = $reqs[$p['scholarship_program_id']] ?? [];
    $req = array_values(array_filter($list, fn($r) => $r['is_required']));
    $income = false; foreach ($list as $r) if (in_array((int)$r['document_type_id'], [3, 4])) $income = true;
    $benefit = trim(($p['benefit_amount'] !== null ? money($p['benefit_amount']) : '') . ($p['benefit_description'] ? ' ' . $p['benefit_description'] : ''));
    $applied = (bool)$p['my_status'];
    $stateKey = $applied ? $state . ' applied' : $state;
?>
  <article class="card sch" data-text="<?= e(strtolower($p['program_name'] . ' ' . $p['provider_name'] . ' ' . $p['program_code'])) ?>"
           data-type="<?= e($p['provider_type']) ?>" data-sponsor="<?= e($p['provider_name']) ?>"
           data-elig="<?= $income ? 'income' : 'none' ?>" data-state="<?= e($stateKey) ?>">
    <div class="sch-head">
      <div class="ico"><?= e(prog_initials($p['program_name'])) ?></div>
      <div class="grow"><b><?= e($p['program_name']) ?></b><small><?= e($p['provider_name'] ?: '—') ?></small></div>
      <span class="tag"><?= e($typeLabel[$p['provider_type']] ?? $p['provider_type']) ?></span>
    </div>
    <p class="desc"><?= e($p['description'] ?: 'The Scholarship Office will post the full description of this program soon.') ?></p>
    <dl class="meta">
      <div><dt>Benefit</dt><dd><?= $benefit ? e($benefit) : 'To be announced' ?></dd></div>
      <div><dt>Eligibility</dt><dd><?= e($p['eligibility_notes'] ?: 'See program details') ?></dd></div>
      <div><dt>Documents</dt><dd><?= $req ? e(implode(', ', array_slice(array_column($req, 'type_name'), 0, 3))) . (count($req) > 3 ? ' +' . (count($req) - 3) . ' more' : '') : 'None listed yet' ?></dd></div>
      <div><dt>Deadline</dt><dd><?= $p['period_id'] ? fdate($p['close_date']) : 'No application period' ?>
        <span class="days <?= days_class($state, $days) ?>"><?= $p['period_id'] ? e(days_text($state, $days)) : 'Closed' ?></span></dd></div>
    </dl>
    <div class="actions">
      <a class="btn btn-alt btn-sm" href="scholarship_view.php?id=<?= (int)$p['scholarship_program_id'] ?>">View Details</a>
      <?php if ($applied): ?>
        <?= badge($p['my_status']) ?>
      <?php elseif ($state === 'open'): ?>
        <a class="btn btn-sm" href="scholarship_view.php?id=<?= (int)$p['scholarship_program_id'] ?>#apply">Apply Now</a>
      <?php else: ?>
        <span class="tag"><?= $state === 'upcoming' ? 'Opens soon' : 'Closed' ?></span>
      <?php endif; ?>
    </div>
  </article>
<?php endforeach; ?>
</div>
<p class="empty" id="schEmpty" hidden>No scholarships match your filters. Clear a filter to see more.</p>
<?php if (!$programs): ?><p class="empty">No scholarship programs are available yet.</p><?php endif; ?>
<?php include __DIR__ . '/includes/footer.php'; ?>
