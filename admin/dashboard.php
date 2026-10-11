<?php
require_once __DIR__ . '/includes/bootstrap.php';

$pageTitle = 'Admin Dashboard';
$active = 'dashboard.php';
$today = date('Y-m-d');
$soon = date('Y-m-d', strtotime('+30 days'));

$count = function ($sql, $params = []) use ($pdo) {
    $q = $pdo->prepare($sql);
    $q->execute($params);
    return (int)$q->fetchColumn();
};

$children    = $count('SELECT COUNT(*) FROM children');
$parents     = $count('SELECT COUNT(*) FROM parents');
$hospitals   = $count('SELECT COUNT(*) FROM hospitals');
$pendingReq  = $count("SELECT COUNT(*) FROM appointments WHERE status = 'Pending'");
$approved    = $count("SELECT COUNT(*) FROM appointments WHERE status = 'Approved'");
$vaccinated  = $count("SELECT COUNT(*) FROM appointments WHERE status = 'Approved' AND vaccination_status = 'Vaccinated'");
$unavailable = $count("SELECT COUNT(*) FROM vaccines WHERE availability = 'Unavailable'");
$dueSoon     = $count('SELECT COUNT(*) FROM v_child_schedule WHERE is_vaccinated = 0 AND due_date BETWEEN ? AND ?', [$today, $soon]);
$overdue     = $count('SELECT COUNT(*) FROM v_child_schedule WHERE is_vaccinated = 0 AND due_date < ?', [$today]);

// Newest requests waiting for a decision
$latest = $pdo->query("SELECT a.id, a.appointment_date, a.time_slot, h.name AS hospital, c.name AS child_name, v.name AS vaccine_name, p.full_name AS parent_name
    FROM appointments a
    JOIN hospitals h ON h.id = a.hospital_id
    JOIN child_vaccinations cv ON cv.id = a.child_vaccination_id
    JOIN children c ON c.id = cv.child_id
    JOIN vaccines v ON v.id = cv.vaccine_id
    JOIN parents p ON p.id = a.parent_id
    WHERE a.status = 'Pending' ORDER BY a.created_at DESC, a.id DESC LIMIT 5")->fetchAll();

// Next vaccination dates
$q = $pdo->prepare('SELECT child_name, vaccine_name, due_date FROM v_child_schedule WHERE is_vaccinated = 0 AND due_date >= ? ORDER BY due_date, child_name LIMIT 6');
$q->execute([$today]);
$upcoming = $q->fetchAll();

// Vaccine availability
$vaccines = $pdo->query('SELECT name, availability FROM vaccines ORDER BY name')->fetchAll();

// Request status mix
$mix = $pdo->query("SELECT status, COUNT(*) AS n FROM appointments GROUP BY status")->fetchAll(PDO::FETCH_KEY_PAIR);
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
