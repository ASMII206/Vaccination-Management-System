<?php
require_once __DIR__ . '/includes/bootstrap.php';
$pageTitle = 'Booking Details';
$active = 'bookings.php';
$search = trim($_GET['q'] ?? '');
$hospitalId = (int)($_GET['hospital'] ?? 0);
$status = $_GET['status'] ?? '';
if (!in_array($status, ['Pending', 'Approved', 'Rejected', 'Cancelled'], true)) $status = '';
$from = clean_date($_GET['from'] ?? '');
$to = clean_date($_GET['to'] ?? '');
$result = mysqli_query($conn, "SELECT id, name FROM hospitals ORDER BY name");
$hospitals = mysqli_fetch_all($result, MYSQLI_ASSOC);
$sql = 'SELECT a.id, a.appointment_date, a.time_slot, a.status, a.vaccination_status, a.created_at,
               c.id AS child_id, c.name AS child_name, c.dob, v.name AS vaccine_name,
               h.name AS hospital, p.full_name AS parent_name, p.phone AS parent_phone
        FROM appointments a
        JOIN child_vaccinations cv ON cv.id = a.child_vaccination_id
        JOIN children c ON c.id = cv.child_id
        JOIN vaccines v ON v.id = cv.vaccine_id
        JOIN hospitals h ON h.id = a.hospital_id
        JOIN parents p ON p.id = a.parent_id
        WHERE 1=1';
if ($hospitalId) { $sql .= " AND a.hospital_id = $hospitalId"; }
if ($status !== '') { $sql .= " AND a.status = '" . esc($status) . "'"; }
if ($from !== '') { $sql .= " AND a.appointment_date >= '" . esc($from) . "'"; }
if ($to !== '') { $sql .= " AND a.appointment_date <= '" . esc($to) . "'"; }
if ($search !== '') {
    $refId = preg_match('/^(?:vms-?)?0*(\d+)$/i', $search, $m) ? (int)$m[1] : 0;
    $like = esc($search);
    $sql .= " AND (c.name LIKE '%$like%' OR p.full_name LIKE '%$like%' OR v.name LIKE '%$like%' OR a.id = $refId)";
}
$sql .= ' ORDER BY a.appointment_date DESC, a.id DESC';
$result = mysqli_query($conn, $sql);
$rows = mysqli_fetch_all($result, MYSQLI_ASSOC);
if (isset($_GET['export'])) {
    $out = [];
    foreach ($rows as $r) {
        $out[] = [booking_ref($r['id']), $r['child_name'], $r['parent_name'], $r['parent_phone'], $r['vaccine_name'], $r['hospital'],
                  fmt_date($r['appointment_date']), $r['time_slot'], $r['status'], $r['status'] === 'Approved' ? outcome_text($r['vaccination_status']) : ''];
    }
    csv_download('bookings-' . date('Ymd') . '.csv', ['Reference', 'Child', 'Parent', 'Phone', 'Vaccine', 'Hospital', 'Date', 'Time Slot', 'Request Status', 'Vaccination'], $out);
}

$filtered = ($search !== '' || $hospitalId || $status !== '' || $from !== '' || $to !== '');
$exportUrl = 'bookings.php' . qs(['q' => $search, 'hospital' => $hospitalId, 'status' => $status, 'from' => $from, 'to' => $to, 'export' => 1]);

include __DIR__ . '/includes/layout_top.php';
?>
<div class="card">
    <h2 class="card-title"><i class="fa-solid fa-book-medical"></i>Hospital Bookings from Parents<small><?= count($rows) ?> found</small></h2>

    <form method="get" action="bookings.php" class="filter">
        <input class="field" type="search" name="q" value="<?= e($search) ?>" placeholder="Search child, parent, vaccine or reference">
        <select class="field" name="hospital" aria-label="Hospital">
            <option value="0">All hospitals</option>
            <?php foreach ($hospitals as $h): ?><option value="<?= (int)$h['id'] ?>" <?= $hospitalId === (int)$h['id'] ? 'selected' : '' ?>><?= e($h['name']) ?></option><?php endforeach; ?>
        </select>
        <select class="field" name="status" aria-label="Status">
            <option value="">All statuses</option>
            <?php foreach (['Pending', 'Approved', 'Rejected', 'Cancelled'] as $s): ?><option <?= $status === $s ? 'selected' : '' ?>><?= $s ?></option><?php endforeach; ?>
        </select>
        <label class="mini">From<input class="field" type="date" name="from" value="<?= e($from) ?>"></label>
        <label class="mini">To<input class="field" type="date" name="to" value="<?= e($to) ?>"></label>
        <button class="pbtn">Filter</button>
        <?php if ($filtered): ?><a class="pbtn outline" href="bookings.php">Clear</a><?php endif; ?>
    </form>

    <?php if (!$rows): ?>
        <div class="empty"><i class="fa-solid fa-book-medical"></i>No bookings found.</div>
    <?php else: ?>
    <div class="table-wrap"><table>
        <thead><tr><th>Reference</th><th>Child</th><th>Parent</th><th>Vaccine</th><th>Hospital</th><th>Date &amp; Slot</th><th>Request</th><th>Vaccination</th></tr></thead>
        <tbody>
        <?php foreach ($rows as $r): ?>
            <tr>
                <td><b><?= e(booking_ref($r['id'])) ?></b><span class="sub">Booked <?= fmt_date($r['created_at']) ?></span></td>
                <td><a href="child-view.php?id=<?= (int)$r['child_id'] ?>"><b><?= e($r['child_name']) ?></b></a><span class="sub"><?= e(age_text($r['dob'])) ?></span></td>
                <td><?= e($r['parent_name']) ?><span class="sub"><?= e($r['parent_phone'] ?: '') ?></span></td>
                <td><?= e($r['vaccine_name']) ?></td>
                <td><?= e($r['hospital']) ?></td>
                <td><?= fmt_date($r['appointment_date']) ?><span class="sub"><?= e($r['time_slot']) ?></span></td>
                <td><?= badge($r['status']) ?></td>
                <td><?= $r['status'] === 'Approved' ? outcome_badge($r['vaccination_status']) : '-' ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
    <div class="form-actions no-print">
        <a class="pbtn outline" href="<?= e($exportUrl) ?>"><i class="fa-solid fa-download"></i>Download CSV</a>
        <button type="button" class="pbtn outline" onclick="window.print()"><i class="fa-solid fa-print"></i>Print</button>
    </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/includes/layout_bottom.php'; ?>
