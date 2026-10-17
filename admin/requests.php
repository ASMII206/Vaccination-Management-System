<?php
require_once __DIR__ . '/includes/bootstrap.php';

$pageTitle = 'Parent Requests';
$active = 'requests.php';

$filter = $_GET['status'] ?? 'Pending';
if (!in_array($filter, ['Pending', 'Approved', 'Rejected', 'Cancelled', 'All'], true)) $filter = 'Pending';
$search = trim($_GET['q'] ?? '');
$back = 'requests.php' . qs(['status' => $filter, 'q' => $search]);


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $id = (int)($_POST['id'] ?? 0);
    $action = $_POST['action'] ?? '';
    $back = 'requests.php' . qs(['status' => $_POST['ret_status'] ?? '', 'q' => trim($_POST['ret_q'] ?? '')]);

    $result = mysqli_query($conn, "SELECT a.id, a.status, a.child_vaccination_id, v.name AS vaccine_name, v.availability
        FROM appointments a
        JOIN child_vaccinations cv ON cv.id = a.child_vaccination_id
        JOIN vaccines v ON v.id = cv.vaccine_id
        WHERE a.id = $id");
    $req = mysqli_fetch_assoc($result);

    if (!$req) {
        flash('error', 'Request not found.');
    } elseif ($req['status'] !== 'Pending') {
        flash('error', booking_ref($id) . ' has already been ' . strtolower($req['status']) . '.');
    } elseif ($action === 'approve') {
        $cvId = (int)$req['child_vaccination_id'];
        $dup = mysqli_query($conn, "SELECT 1 FROM appointments WHERE child_vaccination_id = $cvId AND id <> $id AND (status = 'Approved' OR vaccination_status = 'Vaccinated') LIMIT 1");
        if ($req['availability'] === 'Unavailable') {
            flash('error', $req['vaccine_name'] . ' is currently unavailable, so this request cannot be approved. Mark the vaccine available first.');
        } elseif (mysqli_num_rows($dup) > 0) {
            flash('error', 'This dose already has an approved booking or is already vaccinated.');
        } else {
            mysqli_query($conn, "UPDATE appointments SET status = 'Approved' WHERE id = $id AND status = 'Pending'");
            flash('success', booking_ref($id) . ' approved. It is now visible to the hospital.');
        }
    } elseif ($action === 'reject') {
        mysqli_query($conn, "UPDATE appointments SET status = 'Rejected' WHERE id = $id AND status = 'Pending'");
        flash('success', booking_ref($id) . ' rejected.');
    } else {
        flash('error', 'Unknown action.');
    }
    redirect($back);
}

$result = mysqli_query($conn, "SELECT status, COUNT(*) AS n FROM appointments GROUP BY status");
$counts = [];
while ($row = mysqli_fetch_assoc($result)) {
    $counts[$row['status']] = $row['n'];
}

$sql = 'SELECT a.id, a.appointment_date, a.time_slot, a.status, a.created_at,
               c.name AS child_name, c.dob, v.name AS vaccine_name, v.availability,
               h.name AS hospital, p.full_name AS parent_name, p.phone AS parent_phone
        FROM appointments a
        JOIN child_vaccinations cv ON cv.id = a.child_vaccination_id
        JOIN children c ON c.id = cv.child_id
        JOIN vaccines v ON v.id = cv.vaccine_id
        JOIN hospitals h ON h.id = a.hospital_id
        JOIN parents p ON p.id = a.parent_id
        WHERE 1=1';
if ($filter !== 'All') { $sql .= " AND a.status = '$filter'"; }
if ($search !== '') {
    $refId = preg_match('/^(?:vms-?)?0*(\d+)$/i', $search, $m) ? (int)$m[1] : 0;
    $like = esc($search);
    $sql .= " AND (c.name LIKE '%$like%' OR p.full_name LIKE '%$like%' OR v.name LIKE '%$like%' OR h.name LIKE '%$like%' OR a.id = $refId)";
}
$sql .= ' ORDER BY a.created_at DESC, a.id DESC';
$result = mysqli_query($conn, $sql);
$rows = mysqli_fetch_all($result, MYSQLI_ASSOC);

$tab = function ($s) use ($search) { return 'requests.php' . qs(['status' => $s, 'q' => $search]); };

include __DIR__ . '/includes/layout_top.php';
?>

<div class="seg">
    <?php foreach (['Pending', 'Approved', 'Rejected', 'Cancelled', 'All'] as $s): ?>
        <a href="<?= e($tab($s)) ?>" class="<?= $filter === $s ? 'on' : '' ?>"><?= $s ?><small><?= $s === 'All' ? array_sum($counts) : (int)($counts[$s] ?? 0) ?></small></a>
    <?php endforeach; ?>
</div>

<div class="card">
    <h2 class="card-title"><i class="fa-solid fa-clipboard-list"></i>Appointment Requests from Parents<small><?= count($rows) ?> shown</small></h2>

    <form method="get" action="requests.php" class="filter">
        <input type="hidden" name="status" value="<?= e($filter) ?>">
        <input class="field" type="search" name="q" value="<?= e($search) ?>" placeholder="Search child, parent, vaccine, hospital or reference">
        <button class="pbtn">Search</button>
        <?php if ($search !== ''): ?><a class="pbtn outline" href="<?= e($tab($filter)) ?>">Clear</a><?php endif; ?>
    </form>

    <?php if (!$rows): ?>
        <div class="empty"><i class="fa-solid fa-clipboard-list"></i><?= $filter === 'Pending' && $search === '' ? 'No requests are waiting for approval.' : 'No requests found.' ?></div>
    <?php else: ?>
    <div class="table-wrap"><table>
        <thead><tr><th>Reference</th><th>Child</th><th>Parent</th><th>Vaccine</th><th>Hospital</th><th>Date &amp; Slot</th><th>Requested</th><th>Status</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($rows as $r): ?>
            <tr>
                <td><b><?= e(booking_ref($r['id'])) ?></b></td>
                <td><b><?= e($r['child_name']) ?></b><span class="sub"><?= e(age_text($r['dob'])) ?></span></td>
                <td><?= e($r['parent_name']) ?><span class="sub"><?= e($r['parent_phone'] ?: '') ?></span></td>
                <td><?= e($r['vaccine_name']) ?><?php if ($r['availability'] === 'Unavailable'): ?><span class="sub"><?= badge('Unavailable') ?></span><?php endif; ?></td>
                <td><?= e($r['hospital']) ?></td>
                <td><?= fmt_date($r['appointment_date']) ?><span class="sub"><?= e($r['time_slot']) ?></span></td>
                <td><?= fmt_date($r['created_at']) ?></td>
                <td><?= badge($r['status']) ?></td>
                <td class="actions">
                    <?php if ($r['status'] === 'Pending'): ?>
                    <form method="post" action="requests.php" class="inline">
                        <?= csrf_field() ?>
                        <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                        <input type="hidden" name="action" value="approve">
                        <input type="hidden" name="ret_status" value="<?= e($filter) ?>">
                        <input type="hidden" name="ret_q" value="<?= e($search) ?>">
                        <button class="pbtn sm good">Approve</button>
                    </form>
                    <form method="post" action="requests.php" class="inline" onsubmit="return confirm('Reject this request?');">
                        <?= csrf_field() ?>
                        <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                        <input type="hidden" name="action" value="reject">
                        <input type="hidden" name="ret_status" value="<?= e($filter) ?>">
                        <input type="hidden" name="ret_q" value="<?= e($search) ?>">
                        <button class="pbtn sm danger">Reject</button>
                    </form>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/includes/layout_bottom.php'; ?>
