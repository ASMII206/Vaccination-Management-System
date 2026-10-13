<?php
require_once __DIR__ . '/includes/bootstrap.php';

$pageTitle = 'Appointments';
$pageSub = 'Bookings approved by the administrator';
$active = 'appointments.php';
$today = date('Y-m-d');

$search = trim($_GET['q'] ?? '');
$filter = $_GET['status'] ?? '';
if (!in_array($filter, ['Scheduled', 'Vaccinated', 'Not Vaccinated'], true)) $filter = '';

$hasRemarks = has_column($pdo, 'appointments', 'remarks');

$sql = 'SELECT a.id, a.appointment_date, a.time_slot, a.vaccination_status,
               ' . ($hasRemarks ? 'a.remarks' : 'NULL') . ' AS remarks,
               c.name AS child_name, c.dob, p.full_name AS parent_name, v.name AS vaccine_name
        FROM appointments a
        JOIN child_vaccinations cv ON cv.id = a.child_vaccination_id
        JOIN children c ON c.id = cv.child_id
        JOIN parents p ON p.id = a.parent_id
        JOIN vaccines v ON v.id = cv.vaccine_id
        WHERE a.hospital_id = ? AND a.status = \'Approved\'';
$params = [$hid];

if ($filter !== '') {
    $sql .= ' AND a.vaccination_status = ?';
    $params[] = $filter === 'Scheduled' ? 'Pending' : $filter;
}
if ($search !== '') {
    
    $refId = preg_match('/^(?:vms-?)?0*(\d+)$/i', $search, $m) ? (int)$m[1] : 0;
    $sql .= ' AND (c.name LIKE ? OR p.full_name LIKE ? OR v.name LIKE ? OR a.id = ?)';
    $like = '%' . $search . '%';
    array_push($params, $like, $like, $like, $refId);
}
$sql .= ' ORDER BY (a.vaccination_status = \'Pending\') DESC, a.appointment_date, a.id';

$q = $pdo->prepare($sql);
$q->execute($params);
$rows = $q->fetchAll();


function tab_link($status, $search) {
    $qs = array_filter(['q' => $search, 'status' => $status], function ($v) { return $v !== ''; });
    return 'appointments.php' . ($qs ? '?' . http_build_query($qs) : '');
}

include __DIR__ . '/includes/layout_top.php';
?>

<div class="tb">
  <form method="get" action="appointments.php">
    <?php if ($filter !== ''): ?><input type="hidden" name="status" value="<?= e($filter) ?>"><?php endif; ?>
    <input type="search" name="q" value="<?= e($search) ?>" placeholder="Search by child, parent, vaccine or reference" aria-label="Search appointments">
  </form>
  <div class="seg">
    <a href="<?= e(tab_link('', $search)) ?>" class="<?= $filter === '' ? 'on' : '' ?>">All</a>
    <a href="<?= e(tab_link('Scheduled', $search)) ?>" class="<?= $filter === 'Scheduled' ? 'on' : '' ?>">Scheduled</a>
    <a href="<?= e(tab_link('Vaccinated', $search)) ?>" class="<?= $filter === 'Vaccinated' ? 'on' : '' ?>">Vaccinated</a>
    <a href="<?= e(tab_link('Not Vaccinated', $search)) ?>" class="<?= $filter === 'Not Vaccinated' ? 'on' : '' ?>">Not Vaccinated</a>
  </div>
</div>

<div class="tw">
  <table>
    <thead>
      <tr><th>Reference</th><th>Child</th><th>Parent</th><th>Vaccine</th><th>Date and time</th><th>Status</th><th></th></tr>
    </thead>
    <tbody>
    <?php if (!$rows): ?>
      <tr><td colspan="7" class="empty">
        <?= ($search !== '' || $filter !== '') ? 'No appointments match your search.' : 'No appointments yet. Bookings approved by the administrator will appear here.' ?>
      </td></tr>
    <?php endif; ?>
    <?php foreach ($rows as $r):
        $isScheduled = $r['vaccination_status'] === 'Pending';
        $label = outcome_label($r['vaccination_status']); ?>
      <tr>
        <td><b><?= e(appt_ref($r['id'])) ?></b></td>
        <td><?= e($r['child_name']) ?><small><?= e(age_text($r['dob'])) ?></small></td>
        <td><?= e($r['parent_name']) ?></td>
        <td><?= e($r['vaccine_name']) ?></td>
        <td>
          <?= fmt_date($r['appointment_date']) ?>
          <?php if ($isScheduled && $r['appointment_date'] < $today): ?><em class="tg od">Overdue</em>
          <?php elseif ($isScheduled && $r['appointment_date'] === $today): ?><em class="tg">Today</em><?php endif; ?>
          <small><?= e($r['time_slot']) ?></small>
        </td>
        <td><?= outcome_chip($r['vaccination_status']) ?><?php if ($r['remarks']): ?><small title="<?= e($r['remarks']) ?>">Has remarks</small><?php endif; ?></td>
        <td>
          <button type="button" class="btn <?= $isScheduled ? '' : 'ghost' ?> sm" data-status-open
                  data-id="<?= (int)$r['id'] ?>"
                  data-child="<?= e($r['child_name'] . ' (' . age_text($r['dob']) . ')') ?>"
                  data-details="<?= e($r['vaccine_name'] . ', ' . fmt_date($r['appointment_date']) . ' - ' . $r['time_slot']) ?>"
                  data-status="<?= e($label) ?>"
                  data-remarks="<?= e($r['remarks']) ?>"><?= $isScheduled ? 'Update status' : 'Change' ?></button>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>

<?php ob_start(); ?>

<div class="ov" id="statusModal">
  <form class="mod" method="post" action="update_status.php" role="dialog" aria-modal="true" aria-label="Update vaccination status">
    <?= csrf_field() ?>
    <input type="hidden" name="id" value="">
    <input type="hidden" name="ret_q" value="<?= e($search) ?>">
    <input type="hidden" name="ret_status" value="<?= e($filter) ?>">
    <h2>Update vaccination status</h2>
    <p class="mut"><span id="m-child"></span><br><span id="m-details"></span></p>
    <label class="opt"><input type="radio" name="status" value="Vaccinated" required><span><b>Vaccinated</b><small>The dose was administered.</small></span></label>
    <label class="opt"><input type="radio" name="status" value="Not Vaccinated"><span><b>Not vaccinated</b><small>The child did not receive the dose.</small></span></label>
    <label for="remarks">Remarks (optional)</label>
    <textarea id="remarks" name="remarks" rows="3" maxlength="255"></textarea>
    <div class="acts">
      <button type="button" class="btn ghost" data-close>Cancel</button>
      <button class="btn">Save status</button>
    </div>
  </form>
</div>
<?php $extraBottom = ob_get_clean(); ?>

<?php include __DIR__ . '/includes/layout_bottom.php'; ?>
