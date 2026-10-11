<?php
require_once __DIR__ . '/includes/bootstrap.php';

$pageTitle = 'Vaccination Dates';
$active = 'schedule.php';
$today = date('Y-m-d');

$view = $_GET['view'] ?? 'upcoming';
if (!in_array($view, ['upcoming', 'overdue', 'all'], true)) $view = 'upcoming';
$search = trim($_GET['q'] ?? '');
$from = clean_date($_GET['from'] ?? '');
$to = clean_date($_GET['to'] ?? '');

/* ---- due dates of every child ---- */
$sql = 'SELECT s.*, c.dob, p.full_name AS parent_name
        FROM v_child_schedule s
        JOIN children c ON c.id = s.child_id
        JOIN parents p ON p.id = c.parent_id
        WHERE s.is_vaccinated = 0';
$params = [];
if ($view === 'upcoming') { $sql .= ' AND s.due_date >= ?'; $params[] = $today; }
if ($view === 'overdue')  { $sql .= ' AND s.due_date < ?';  $params[] = $today; }
if ($from !== '') { $sql .= ' AND s.due_date >= ?'; $params[] = $from; }
if ($to !== '')   { $sql .= ' AND s.due_date <= ?'; $params[] = $to; }
if ($search !== '') {
    $sql .= ' AND (s.child_name LIKE ? OR s.vaccine_name LIKE ? OR p.full_name LIKE ?)';
    $like = '%' . $search . '%';
    array_push($params, $like, $like, $like);
}
$sql .= ' ORDER BY s.due_date, s.child_name LIMIT 500';
$q = $pdo->prepare($sql);
$q->execute($params);
$due = $q->fetchAll();

/* ---- date & time of booked vaccinations ---- */
$sql = "SELECT a.id, a.appointment_date, a.time_slot, a.status, a.vaccination_status,
               c.name AS child_name, v.name AS vaccine_name, h.name AS hospital, p.full_name AS parent_name
        FROM appointments a
        JOIN child_vaccinations cv ON cv.id = a.child_vaccination_id
        JOIN children c ON c.id = cv.child_id
        JOIN vaccines v ON v.id = cv.vaccine_id
        JOIN hospitals h ON h.id = a.hospital_id
        JOIN parents p ON p.id = a.parent_id
        WHERE a.status IN ('Pending', 'Approved') AND a.vaccination_status = 'Pending'";
$params = [];
if ($view === 'upcoming') { $sql .= ' AND a.appointment_date >= ?'; $params[] = $today; }
if ($view === 'overdue')  { $sql .= ' AND a.appointment_date < ?';  $params[] = $today; }
if ($from !== '') { $sql .= ' AND a.appointment_date >= ?'; $params[] = $from; }
if ($to !== '')   { $sql .= ' AND a.appointment_date <= ?'; $params[] = $to; }
if ($search !== '') {
    $sql .= ' AND (c.name LIKE ? OR v.name LIKE ? OR p.full_name LIKE ? OR h.name LIKE ?)';
    $like = '%' . $search . '%';
    array_push($params, $like, $like, $like, $like);
}
$sql .= ' ORDER BY a.appointment_date, a.id LIMIT 500';
$q = $pdo->prepare($sql);
$q->execute($params);
$booked = $q->fetchAll();

$tab = function ($v) use ($search, $from, $to) { return 'schedule.php' . qs(['view' => $v, 'q' => $search, 'from' => $from, 'to' => $to]); };

include __DIR__ . '/includes/layout_top.php';
?>

<div class="seg">
    <a href="<?= e($tab('upcoming')) ?>" class="<?= $view === 'upcoming' ? 'on' : '' ?>">Upcoming</a>
    <a href="<?= e($tab('overdue')) ?>" class="<?= $view === 'overdue' ? 'on' : '' ?>">Overdue</a>
    <a href="<?= e($tab('all')) ?>" class="<?= $view === 'all' ? 'on' : '' ?>">All</a>
</div>

<form method="get" action="schedule.php" class="filter">
    <input type="hidden" name="view" value="<?= e($view) ?>">
    <input class="field" type="search" name="q" value="<?= e($search) ?>" placeholder="Search child, parent, vaccine or hospital">
    <label class="mini">From<input class="field" type="date" name="from" value="<?= e($from) ?>"></label>
    <label class="mini">To<input class="field" type="date" name="to" value="<?= e($to) ?>"></label>
    <button class="pbtn">Filter</button>
    <?php if ($search !== '' || $from !== '' || $to !== ''): ?><a class="pbtn outline" href="<?= e('schedule.php' . qs(['view' => $view])) ?>">Clear</a><?php endif; ?>
</form>

<div class="card">
    <h2 class="card-title"><i class="fa-regular fa-clock"></i>Booked Date &amp; Time<small><?= count($booked) ?> found</small></h2>
    <?php if (!$booked): ?>
        <div class="empty"><i class="fa-regular fa-calendar"></i>No booked vaccinations in this view.</div>
    <?php else: ?>
    <div class="table-wrap"><table>
        <thead><tr><th>Reference</th><th>Date</th><th>Time Slot</th><th>Child</th><th>Vaccine</th><th>Hospital</th><th>Parent</th><th>Request</th></tr></thead>
        <tbody>
        <?php foreach ($booked as $r): ?>
            <tr>
                <td><b><?= e(booking_ref($r['id'])) ?></b></td>
                <td><?= fmt_date($r['appointment_date']) ?></td>
                <td><?= e($r['time_slot']) ?></td>
                <td><b><?= e($r['child_name']) ?></b></td>
                <td><?= e($r['vaccine_name']) ?></td>
                <td><?= e($r['hospital']) ?></td>
                <td><?= e($r['parent_name']) ?></td>
                <td><?= badge($r['status']) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
    <?php endif; ?>
</div>

<div class="card">
    <h2 class="card-title"><i class="fa-regular fa-calendar"></i>Due Dates of All Children<small><?= count($due) ?> found</small></h2>
    <?php if (!$due): ?>
        <div class="empty"><i class="fa-regular fa-calendar"></i>No vaccination due dates in this view.</div>
    <?php else: ?>
    <div class="table-wrap"><table>
        <thead><tr><th>Due Date</th><th>Child</th><th>Age</th><th>Vaccine</th><th>Availability</th><th>Parent</th><th>Status</th></tr></thead>
        <tbody>
        <?php foreach ($due as $r): ?>
            <tr>
                <td><b><?= fmt_date($r['due_date']) ?></b></td>
                <td><a href="child-view.php?id=<?= (int)$r['child_id'] ?>"><b><?= e($r['child_name']) ?></b></a></td>
                <td><?= e(age_text($r['dob'])) ?></td>
                <td><?= e($r['vaccine_name']) ?></td>
                <td><?= badge($r['availability']) ?></td>
                <td><?= e($r['parent_name']) ?></td>
                <td><?= badge(schedule_status($r)) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/includes/layout_bottom.php'; ?>
