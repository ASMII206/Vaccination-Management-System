<?php
require_once __DIR__ . '/includes/bootstrap.php';

$pageTitle = 'Admin Dashboard';
$active = 'dashboard.php';
$today = date('Y-m-d');
$soon = date('Y-m-d', strtotime('+30 days'));


function count_rows($sql) {
    global $conn;
    $result = mysqli_query($conn, $sql);
    $row = mysqli_fetch_row($result);
    return (int)$row[0];
}

$children    = count_rows('SELECT COUNT(*) FROM children');
$parents     = count_rows('SELECT COUNT(*) FROM parents');
$hospitals   = count_rows('SELECT COUNT(*) FROM hospitals');
$pendingReq  = count_rows("SELECT COUNT(*) FROM appointments WHERE status = 'Pending'");
$approved    = count_rows("SELECT COUNT(*) FROM appointments WHERE status = 'Approved'");
$vaccinated  = count_rows("SELECT COUNT(*) FROM appointments WHERE status = 'Approved' AND vaccination_status = 'Vaccinated'");
$unavailable = count_rows("SELECT COUNT(*) FROM vaccines WHERE availability = 'Unavailable'");

$dueSoon = count_rows(
    "SELECT COUNT(*)
     FROM child_vaccinations cv
     JOIN children c ON c.id = cv.child_id
     JOIN vaccines v ON v.id = cv.vaccine_id
     WHERE NOT EXISTS (
         SELECT 1
         FROM appointments a
         WHERE a.child_vaccination_id = cv.id
           AND a.vaccination_status = 'Vaccinated'
     )
     AND cv.due_date BETWEEN '$today' AND '$soon'"
);

$overdue = count_rows(
    "SELECT COUNT(*)
     FROM child_vaccinations cv
     JOIN children c ON c.id = cv.child_id
     JOIN vaccines v ON v.id = cv.vaccine_id
     WHERE NOT EXISTS (
         SELECT 1
         FROM appointments a
         WHERE a.child_vaccination_id = cv.id
           AND a.vaccination_status = 'Vaccinated'
     )
     AND cv.due_date < '$today'"
);



$result = mysqli_query($conn, "SELECT a.id, a.appointment_date, a.time_slot, h.name AS hospital, c.name AS child_name, v.name AS vaccine_name, p.full_name AS parent_name
    FROM appointments a
    JOIN hospitals h ON h.id = a.hospital_id
    JOIN child_vaccinations cv ON cv.id = a.child_vaccination_id
    JOIN children c ON c.id = cv.child_id
    JOIN vaccines v ON v.id = cv.vaccine_id
    JOIN parents p ON p.id = a.parent_id
    WHERE a.status = 'Pending' ORDER BY a.created_at DESC, a.id DESC LIMIT 5");
$latest = mysqli_fetch_all($result, MYSQLI_ASSOC);



 
$result = mysqli_query($conn, "SELECT
        c.name AS child_name,
        v.name AS vaccine_name,
        cv.due_date
     FROM child_vaccinations cv
     JOIN children c ON c.id = cv.child_id
     JOIN vaccines v ON v.id = cv.vaccine_id
     WHERE NOT EXISTS (
         SELECT 1
         FROM appointments a
         WHERE a.child_vaccination_id = cv.id
           AND a.vaccination_status = 'Vaccinated'
     )
     AND cv.due_date >= '$today'
     ORDER BY cv.due_date, c.name
     LIMIT 6");
$upcoming = mysqli_fetch_all($result, MYSQLI_ASSOC);



$result = mysqli_query($conn, "SELECT name, availability FROM vaccines ORDER BY name");
$vaccines = mysqli_fetch_all($result, MYSQLI_ASSOC);


$result = mysqli_query($conn, "SELECT status, COUNT(*) AS n FROM appointments GROUP BY status");
$mix = [];
while ($row = mysqli_fetch_assoc($result)) {
    $mix[$row['status']] = $row['n'];
}
$mixTotal = array_sum($mix);

include __DIR__ . '/includes/layout_top.php';
?>

<div class="stats">
    <div class="stat"><div class="stat-icon"><i class="fa-solid fa-child"></i></div><div><b><?= $children ?></b><span>Children</span></div></div>
    <div class="stat"><div class="stat-icon"><i class="fa-solid fa-users"></i></div><div><b><?= $parents ?></b><span>Parents</span></div></div>
    <div class="stat"><div class="stat-icon"><i class="fa-solid fa-hospital"></i></div><div><b><?= $hospitals ?></b><span>Hospitals</span></div></div>
    <div class="stat"><div class="stat-icon"><i class="fa-regular fa-clock"></i></div><div><b><?= $pendingReq ?></b><span>Pending requests</span></div></div>
    <div class="stat"><div class="stat-icon"><i class="fa-solid fa-syringe"></i></div><div><b><?= $vaccinated ?></b><span>Vaccinations done</span></div></div>
    <div class="stat"><div class="stat-icon"><i class="fa-regular fa-calendar"></i></div><div><b><?= $dueSoon ?></b><span>Due in 30 days</span></div></div>
    <div class="stat <?= $overdue ? 'alert-tile' : '' ?>"><div class="stat-icon"><i class="fa-solid fa-triangle-exclamation"></i></div><div><b><?= $overdue ?></b><span>Overdue doses</span></div></div>
    <div class="stat <?= $unavailable ? 'alert-tile' : '' ?>"><div class="stat-icon"><i class="fa-solid fa-ban"></i></div><div><b><?= $unavailable ?></b><span>Vaccines unavailable</span></div></div>
</div>

<div class="grid-2">
    <div class="card">
        <h2 class="card-title"><i class="fa-solid fa-clipboard-list"></i>Requests Awaiting Approval<small><a href="requests.php">View all</a></small></h2>
        <?php if (!$latest): ?><p class="muted">No parent requests are waiting for a decision.</p><?php endif; ?>
        <?php foreach ($latest as $r): ?>
            <div class="row-item">
                <div>
                    <b><?= e($r['child_name']) ?></b> &ndash; <?= e($r['vaccine_name']) ?>
                    <div class="meta"><?= e($r['parent_name']) ?> &middot; <?= e($r['hospital']) ?> &middot; <?= fmt_date($r['appointment_date']) ?></div>
                </div>
                <a class="pbtn sm" href="requests.php?status=Pending">Review</a>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="card">
        <h2 class="card-title"><i class="fa-regular fa-calendar"></i>Upcoming Vaccination Dates<small><a href="schedule.php">View all</a></small></h2>
        <?php if (!$upcoming): ?><p class="muted">No upcoming vaccinations.</p><?php endif; ?>
        <?php foreach ($upcoming as $r): ?>
            <div class="row-item">
                <div><b><?= e($r['child_name']) ?></b><div class="meta"><?= e($r['vaccine_name']) ?></div></div>
                <?= badge('Upcoming') ?> <span class="muted"><?= fmt_date($r['due_date']) ?></span>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<div class="grid-2" style="margin-top:26px">
    <div class="card">
        <h2 class="card-title"><i class="fa-solid fa-syringe"></i>Vaccine Availability<small><a href="vaccines.php">Manage</a></small></h2>
        <?php if (!$vaccines): ?><p class="muted">No vaccines have been added.</p><?php endif; ?>
        <?php foreach ($vaccines as $v): ?>
            <div class="row-item"><b><?= e($v['name']) ?></b><?= badge($v['availability']) ?></div>
        <?php endforeach; ?>
    </div>

    <div class="card">
        <h2 class="card-title"><i class="fa-solid fa-chart-simple"></i>Booking Requests by Status<small><?= $mixTotal ?> total</small></h2>
        <?php foreach (['Pending', 'Approved', 'Rejected', 'Cancelled'] as $st):
            $n = (int)($mix[$st] ?? 0); $pct = $mixTotal ? round($n / $mixTotal * 100) : 0; ?>
            <div class="chart-row"><span class="lab"><?= $st ?></span><span class="bar"><span style="width:<?= $pct ?>%"></span></span><b><?= $n ?></b></div>
        <?php endforeach; ?>
        <p class="tot"><?= $approved ?> approved booking<?= $approved === 1 ? '' : 's' ?> sent to hospitals.</p>
    </div>
</div>

<?php include __DIR__ . '/includes/layout_bottom.php'; ?>
