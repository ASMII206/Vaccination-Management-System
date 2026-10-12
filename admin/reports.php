<?php
require_once __DIR__ . '/includes/bootstrap.php';

$pageTitle = 'Vaccination Reports';
$active = 'reports.php';

$from = clean_date($_GET['from'] ?? '');
$to = clean_date($_GET['to'] ?? '');
$search = trim($_GET['q'] ?? '');
$vaccineId = (int)($_GET['vaccine'] ?? 0);
$hospitalId = (int)($_GET['hospital'] ?? 0);
$outcome = $_GET['outcome'] ?? '';
if (!in_array($outcome, ['Vaccinated', 'Not Vaccinated', 'Pending'], true)) $outcome = '';

$vaccines = $pdo->query('SELECT id, name FROM vaccines ORDER BY name')->fetchAll();
$hospitals = $pdo->query('SELECT id, name FROM hospitals ORDER BY name')->fetchAll();

$hasRemarks = has_column($pdo, 'appointments', 'remarks');

// Date-wise report of child / vaccination (approved bookings only)
$sql = "SELECT a.id, a.appointment_date, a.time_slot, a.vaccination_status, " . ($hasRemarks ? 'a.remarks' : 'NULL') . " AS remarks,
               c.id AS child_id, c.name AS child_name, c.dob, v.name AS vaccine_name, h.name AS hospital, p.full_name AS parent_name
        FROM appointments a
        JOIN child_vaccinations cv ON cv.id = a.child_vaccination_id
        JOIN children c ON c.id = cv.child_id
        JOIN vaccines v ON v.id = cv.vaccine_id
        JOIN hospitals h ON h.id = a.hospital_id
        JOIN parents p ON p.id = a.parent_id
        WHERE a.status = 'Approved'";
$params = [];
if ($from !== '') { $sql .= ' AND a.appointment_date >= ?'; $params[] = $from; }
if ($to !== '')   { $sql .= ' AND a.appointment_date <= ?'; $params[] = $to; }
if ($vaccineId)   { $sql .= ' AND cv.vaccine_id = ?'; $params[] = $vaccineId; }
if ($hospitalId)  { $sql .= ' AND a.hospital_id = ?'; $params[] = $hospitalId; }
if ($outcome !== '') { $sql .= ' AND a.vaccination_status = ?'; $params[] = $outcome; }
if ($search !== '') {
    $sql .= ' AND (c.name LIKE ? OR p.full_name LIKE ?)';
    $like = '%' . $search . '%';
    array_push($params, $like, $like);
}
$sql .= ' ORDER BY a.appointment_date DESC, c.name, a.id';
$q = $pdo->prepare($sql);
$q->execute($params);
$rows = $q->fetchAll();

if (isset($_GET['export'])) {
    $out = [];
    foreach ($rows as $r) {
        $out[] = [fmt_date($r['appointment_date']), $r['time_slot'], $r['child_name'], $r['parent_name'], $r['vaccine_name'], $r['hospital'], outcome_text($r['vaccination_status']), $r['remarks']];
    }
    csv_download('vaccination-report-' . date('Ymd') . '.csv', ['Date', 'Time Slot', 'Child', 'Parent', 'Vaccine', 'Hospital', 'Status', 'Remarks'], $out);
}

$counts = ['Vaccinated' => 0, 'Not Vaccinated' => 0, 'Pending' => 0];
$byDate = [];
foreach ($rows as $r) {
    $counts[$r['vaccination_status']]++;
    $byDate[$r['appointment_date']][] = $r;
}


 // Overall progress of every child — no database view required
$progress = $pdo->query("
    SELECT
        c.id,
        c.name,
        c.dob,
        p.full_name AS parent_name,

        (
            SELECT COUNT(*)
            FROM child_vaccinations cv
            WHERE cv.child_id = c.id
        ) AS total,

        (
            SELECT COUNT(*)
            FROM child_vaccinations cv
            WHERE cv.child_id = c.id
              AND EXISTS (
                  SELECT 1
                  FROM appointments a
                  WHERE a.child_vaccination_id = cv.id
                    AND a.vaccination_status = 'Vaccinated'
              )
        ) AS done

    FROM children c
    JOIN parents p ON p.id = c.parent_id
    ORDER BY c.name
")->fetchAll();


$filtered = ($from !== '' || $to !== '' || $search !== '' || $vaccineId || $hospitalId || $outcome !== '');
$exportUrl = 'reports.php' . qs(['from' => $from, 'to' => $to, 'q' => $search, 'vaccine' => $vaccineId, 'hospital' => $hospitalId, 'outcome' => $outcome, 'export' => 1]);

include __DIR__ . '/includes/layout_top.php';
?>

<div class="stats">
    <div class="stat"><div class="stat-icon"><i class="fa-solid fa-list"></i></div><div><b><?= count($rows) ?></b><span>Records</span></div></div>
    <div class="stat"><div class="stat-icon"><i class="fa-solid fa-syringe"></i></div><div><b><?= $counts['Vaccinated'] ?></b><span>Vaccinated</span></div></div>
    <div class="stat"><div class="stat-icon"><i class="fa-regular fa-clock"></i></div><div><b><?= $counts['Pending'] ?></b><span>Scheduled</span></div></div>
    <div class="stat <?= $counts['Not Vaccinated'] ? 'alert-tile' : '' ?>"><div class="stat-icon"><i class="fa-solid fa-circle-xmark"></i></div><div><b><?= $counts['Not Vaccinated'] ?></b><span>Not vaccinated</span></div></div>
</div>

<div class="card">
    <h2 class="card-title"><i class="fa-solid fa-file-medical"></i>Child &amp; Vaccination Report (Date Wise)<small><?= count($rows) ?> record<?= count($rows) === 1 ? '' : 's' ?></small></h2>

    <form method="get" action="reports.php" class="filter">
        <label class="mini">From<input class="field" type="date" name="from" value="<?= e($from) ?>"></label>
        <label class="mini">To<input class="field" type="date" name="to" value="<?= e($to) ?>"></label>
        <label class="mini">Child / parent<input class="field" type="search" name="q" value="<?= e($search) ?>" placeholder="Search"></label>
        <label class="mini">Vaccine
            <select class="field" name="vaccine"><option value="0">All vaccines</option>
                <?php foreach ($vaccines as $v): ?><option value="<?= (int)$v['id'] ?>" <?= $vaccineId === (int)$v['id'] ? 'selected' : '' ?>><?= e($v['name']) ?></option><?php endforeach; ?>
            </select></label>
        <label class="mini">Hospital
            <select class="field" name="hospital"><option value="0">All hospitals</option>
                <?php foreach ($hospitals as $h): ?><option value="<?= (int)$h['id'] ?>" <?= $hospitalId === (int)$h['id'] ? 'selected' : '' ?>><?= e($h['name']) ?></option><?php endforeach; ?>
            </select></label>
        <label class="mini">Status
            <select class="field" name="outcome"><option value="">All</option>
                <option value="Vaccinated" <?= $outcome === 'Vaccinated' ? 'selected' : '' ?>>Vaccinated</option>
                <option value="Pending" <?= $outcome === 'Pending' ? 'selected' : '' ?>>Scheduled</option>
                <option value="Not Vaccinated" <?= $outcome === 'Not Vaccinated' ? 'selected' : '' ?>>Not Vaccinated</option>
            </select></label>
        <button class="pbtn">Generate</button>
        <?php if ($filtered): ?><a class="pbtn outline" href="reports.php">Clear</a><?php endif; ?>
    </form>

    <?php if (!$rows): ?>
        <div class="empty"><i class="fa-solid fa-file-medical"></i>No records match these filters.</div>
    <?php else: ?>
    <div class="table-wrap"><table>
        <thead><tr><th>Date</th><th>Time Slot</th><th>Child</th><th>Parent</th><th>Vaccine</th><th>Hospital</th><th>Status</th></tr></thead>
        <tbody>
        <?php foreach ($byDate as $date => $list): foreach ($list as $i => $r): ?>
            <tr>
                <td><?= $i === 0 ? '<b>' . e(fmt_date($date)) . '</b>' : '' ?></td>
                <td><?= e($r['time_slot']) ?></td>
                <td><a href="child-view.php?id=<?= (int)$r['child_id'] ?>"><b><?= e($r['child_name']) ?></b></a><span class="sub"><?= e(age_text($r['dob'])) ?></span></td>
                <td><?= e($r['parent_name']) ?></td>
                <td><?= e($r['vaccine_name']) ?></td>
                <td><?= e($r['hospital']) ?></td>
                <td><?= outcome_badge($r['vaccination_status']) ?><?php if ($r['remarks']): ?><span class="sub"><?= e($r['remarks']) ?></span><?php endif; ?></td>
            </tr>
        <?php endforeach; endforeach; ?>
        </tbody>
    </table></div>
    <div class="form-actions no-print">
        <a class="pbtn outline" href="<?= e($exportUrl) ?>"><i class="fa-solid fa-download"></i>Download CSV</a>
        <button type="button" class="pbtn outline" onclick="window.print()"><i class="fa-solid fa-print"></i>Print report</button>
    </div>
    <?php endif; ?>
</div>

<div class="card">
    <h2 class="card-title"><i class="fa-solid fa-child"></i>Vaccination Progress of Every Child<small><?= count($progress) ?> children</small></h2>
    <?php if (!$progress): ?>
        <p class="muted">No children are registered yet.</p>
    <?php else: ?>
    <div class="table-wrap"><table>
        <thead><tr><th>Child</th><th>Age</th><th>Parent</th><th>Vaccines Completed</th></tr></thead>
        <tbody>
        <?php foreach ($progress as $c): $pct = $c['total'] ? round($c['done'] / $c['total'] * 100) : 0; ?>
            <tr>
                <td><a href="child-view.php?id=<?= (int)$c['id'] ?>"><b><?= e($c['name']) ?></b></a></td>
                <td><?= e(age_text($c['dob'])) ?></td>
                <td><?= e($c['parent_name']) ?></td>
                <td><span class="bar"><span style="width:<?= $pct ?>%"></span></span><?= (int)$c['done'] ?> of <?= (int)$c['total'] ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/includes/layout_bottom.php'; ?>
